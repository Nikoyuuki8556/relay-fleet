<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\AircraftReservation;
use App\Models\AircraftReservationQueue;
use App\Models\AircraftSchedule;
use App\Models\Airport;
use App\Models\Community;
use App\Models\Pirep;
use App\Services\CommunityManagementService;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class CommunityWebController extends Controller
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly CommunityManagementService $management,
    ) {
    }

    public function directory(Request $request)
    {
        $communities = Community::where('visibility', 'public')->orderByDesc('id')->paginate(20);

        $myCommunityIds = $request->user()->memberships()->pluck('community_id');

        return view('communities.directory', ['communities' => $communities, 'myCommunityIds' => $myCommunityIds]);
    }

    public function create()
    {
        return view('communities.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'visibility' => ['required', 'in:public,private'],
            'join_policy' => ['required', 'in:open,request,invite_only'],
            'is_recruiting' => ['nullable', 'boolean'],
            'recruiting_message' => ['nullable', 'string', 'max:255'],
        ]);
        $data['is_recruiting'] = $request->boolean('is_recruiting');

        $community = $this->memberships->createCommunity($request->user(), $data);

        return redirect()->route('communities.show', $community)
            ->with('status', "「{$community->name}」を作成しました。あなたがオーナーです。");
    }

    public function join(Request $request, Community $community)
    {
        try {
            $result = $this->memberships->joinPublicCommunity($request->user(), $community);

            return redirect()->route('communities.directory')->with('status', $result['message']);
        } catch (ApiException $e) {
            return redirect()->route('communities.directory')->with('error', $e->getMessage());
        }
    }

    public function joinByCode(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);

        try {
            $membership = $this->memberships->joinViaInviteCode($request->user(), $data['code']);

            return redirect()->route('communities.show', $membership->community_id)
                ->with('status', 'コミュニティに参加しました');
        } catch (ApiException $e) {
            return redirect()->route('dashboard')->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, Community $community)
    {
        if (! $this->memberships->hasActiveMembership($request->user()->id, $community->id)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }

        $aircraft = $community->aircraft()->with('airline')->orderBy('registration')->get();
        $aircraftIds = $aircraft->pluck('id');

        $myActiveReservation = AircraftReservation::query()
            ->whereIn('aircraft_id', $aircraftIds)
            ->where('reserved_by_user_id', $request->user()->id)
            ->whereNull('released_at')
            ->latest('id')
            ->first();

        $myQueueEntries = AircraftReservationQueue::query()
            ->whereIn('aircraft_id', $aircraftIds)
            ->where('user_id', $request->user()->id)
            ->where('status', 'waiting')
            ->get()
            ->keyBy('aircraft_id');

        $queueCounts = AircraftReservationQueue::query()
            ->whereIn('aircraft_id', $aircraftIds)
            ->where('status', 'waiting')
            ->selectRaw('aircraft_id, count(*) as cnt')
            ->groupBy('aircraft_id')
            ->pluck('cnt', 'aircraft_id');

        $myMembership = $community->memberships()
            ->where('user_id', $request->user()->id)
            ->first();

        $pendingMembers = null;
        if ($myMembership !== null && in_array($myMembership->role, ['owner', 'admin'], true)) {
            $pendingMembers = $community->memberships()->where('status', 'pending')->with('user')->get();
        }

        return view('communities.show', [
            'community' => $community,
            'aircraft' => $aircraft,
            'myActiveReservation' => $myActiveReservation,
            'myQueueEntries' => $myQueueEntries,
            'queueCounts' => $queueCounts,
            'myMembership' => $myMembership,
            'pendingMembers' => $pendingMembers,
        ]);
    }

    public function ranking(Request $request, Community $community)
    {
        if (! $this->memberships->hasActiveMembership($request->user()->id, $community->id)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }

        $month = now();
        $ranking = Pirep::query()
            ->whereHas('aircraft', fn ($q) => $q->where('community_id', $community->id))
            ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->selectRaw('user_id, SUM(flight_time_min) as total_minutes, COUNT(*) as flight_count')
            ->groupBy('user_id')
            ->orderByDesc('total_minutes')
            ->with('user')
            ->get();

        return view('communities.ranking', [
            'community' => $community,
            'ranking' => $ranking,
            'month' => $month,
        ]);
    }

    public function map(Request $request, Community $community)
    {
        if (! $this->memberships->hasActiveMembership($request->user()->id, $community->id)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }

        $aircraft = $community->aircraft()->where('status', '!=', 'retired')->with('airline')->orderBy('registration')->get();

        $icaos = $aircraft->pluck('current_location_icao')->unique()->values();
        $airports = Airport::query()->whereIn('icao', $icaos)->get()->keyBy('icao');

        $markers = [];
        $unmapped = [];
        foreach ($aircraft->groupBy('current_location_icao') as $icao => $group) {
            $airport = $airports->get($icao);
            if ($airport !== null) {
                $markers[] = [
                    'icao' => $icao,
                    'name' => $airport->name,
                    'lat' => $airport->lat,
                    'lon' => $airport->lon,
                    'aircraft' => $group->map(fn ($a) => [
                        'registration' => $a->registration,
                        'icao_type' => $a->icao_type,
                        'status' => $a->status,
                        'spot' => $a->current_spot,
                        'airline' => $a->airline?->name,
                    ])->values(),
                ];
            } else {
                $unmapped[$icao] = $group->count();
            }
        }

        return view('communities.map', [
            'community' => $community,
            'markers' => $markers,
            'unmapped' => $unmapped,
        ]);
    }

    public function createInvite(Request $request, Community $community)
    {
        try {
            $invite = $this->memberships->createInvite($request->user(), $community, null, null);

            return redirect()->route('communities.show', $community)
                ->with('status', "招待コードを発行しました: {$invite->code}（無期限・無制限）");
        } catch (ApiException $e) {
            return redirect()->route('communities.show', $community)->with('error', $e->getMessage());
        }
    }

    public function approveMembership(Request $request, Community $community, int $userId)
    {
        try {
            $this->memberships->approveMembership($request->user(), $community, $userId);

            return redirect()->route('communities.show', $community)->with('status', '参加リクエストを承認しました');
        } catch (ApiException $e) {
            return redirect()->route('communities.show', $community)->with('error', $e->getMessage());
        }
    }

    public function aircraftShow(Request $request, Community $community, Aircraft $aircraft)
    {
        if (! $this->memberships->hasActiveMembership($request->user()->id, $community->id)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }
        if ($aircraft->community_id !== $community->id) {
            throw new ApiException(404, 'not_found', '機体が見つかりません');
        }

        // orWhereNullを素で使うとaircraft_idの絞り込みごとOR結合されてしまうため、
        // 必ずクロージャで括ってaircraft_id AND (ends_at>=now() OR ends_at IS NULL) にする。
        $schedules = $aircraft->schedules()->with('creator')
            ->where(function ($query) {
                $query->where('ends_at', '>=', now())->orWhereNull('ends_at');
            })
            ->get();
        $isManager = $this->memberships->hasRole($request->user()->id, $community->id, ['owner', 'admin', 'dispatcher']);

        // フライト履歴（直近30件）。どの時刻に飛んで、どのスポットに着いたかを機体単位で確認できるようにする。
        $pireps = $aircraft->pireps()->with('user')->limit(30)->get();

        $queue = $aircraft->waitingQueue()->with('user')->get();
        $myQueueEntry = $queue->firstWhere('user_id', $request->user()->id);

        return view('communities.aircraft-show', [
            'community' => $community,
            'aircraft' => $aircraft,
            'schedules' => $schedules,
            'isManager' => $isManager,
            'pireps' => $pireps,
            'queue' => $queue,
            'myQueueEntry' => $myQueueEntry,
        ]);
    }

    public function storeSchedule(Request $request, Community $community, Aircraft $aircraft)
    {
        $data = $request->validate([
            'type' => ['required', 'in:event,maintenance'],
            'title' => ['required', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->management->addSchedule($request->user(), $community, $aircraft, $data);

            return redirect()->route('communities.aircraft.show', [$community, $aircraft])->with('status', '運用予定を登録しました');
        } catch (ApiException $e) {
            return redirect()->route('communities.aircraft.show', [$community, $aircraft])->with('error', $e->getMessage());
        }
    }

    public function destroySchedule(Request $request, Community $community, Aircraft $aircraft, AircraftSchedule $schedule)
    {
        try {
            $this->management->removeSchedule($request->user(), $community, $schedule);

            return redirect()->route('communities.aircraft.show', [$community, $aircraft])->with('status', '運用予定を削除しました');
        } catch (ApiException $e) {
            return redirect()->route('communities.aircraft.show', [$community, $aircraft])->with('error', $e->getMessage());
        }
    }
}
