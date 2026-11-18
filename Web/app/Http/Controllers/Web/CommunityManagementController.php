<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Community;
use App\Models\CommunityLink;
use App\Services\CommunityManagementService;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommunityManagementController extends Controller
{
    public function __construct(
        private readonly CommunityManagementService $management,
        private readonly MembershipService $memberships,
    ) {
    }

    public function edit(Request $request, Community $community)
    {
        $this->assertManager($request, $community);

        $aircraft = $community->aircraft()->with('airline')->orderBy('registration')->get();
        $links = $community->links;
        $airlines = $community->airlines;

        return view('communities.manage', [
            'community' => $community,
            'aircraft' => $aircraft,
            'links' => $links,
            'airlines' => $airlines,
        ]);
    }

    public function update(Request $request, Community $community)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon_url' => ['nullable', 'url', 'max:255'],
            'icon' => ['nullable', 'image', 'max:2048'], // 2MBまで。アップロード時はicon_urlより優先。
            'discord_guild_id' => ['nullable', 'string', 'max:32'],
            'visibility' => ['required', 'in:public,private'],
            'join_policy' => ['required', 'in:open,request,invite_only'],
            'is_recruiting' => ['nullable', 'boolean'],
            'recruiting_message' => ['nullable', 'string', 'max:255'],
        ]);
        $data['is_recruiting'] = $request->boolean('is_recruiting');

        if ($request->hasFile('icon')) {
            $path = $request->file('icon')->store('community-icons', 'public');
            $data['icon_url'] = Storage::disk('public')->url($path);
        }
        unset($data['icon']);

        try {
            $this->management->updateSettings($request->user(), $community, $data);

            return redirect()->route('communities.manage', $community)->with('status', '設定を更新しました');
        } catch (ApiException $e) {
            return redirect()->route('communities.manage', $community)->with('error', $e->getMessage());
        }
    }

    public function storeAirline(Request $request, Community $community)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'icao_code' => ['nullable', 'string', 'max:3'],
            'iata_code' => ['nullable', 'string', 'max:2'],
        ]);

        try {
            $airline = $this->management->addAirline($request->user(), $community, $data);

            return redirect()->route('communities.manage', $community)
                ->with('status', "航空会社「{$airline->name}」を追加しました");
        } catch (ApiException $e) {
            return redirect()->route('communities.manage', $community)->with('error', $e->getMessage());
        }
    }

    public function destroyAirline(Request $request, Community $community, Airline $airline)
    {
        try {
            $this->management->removeAirline($request->user(), $community, $airline);

            return redirect()->route('communities.manage', $community)->with('status', '航空会社を削除しました');
        } catch (ApiException $e) {
            return redirect()->route('communities.manage', $community)->with('error', $e->getMessage());
        }
    }

    public function storeLink(Request $request, Community $community)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'url' => ['required', 'url', 'max:255'],
        ]);

        try {
            $this->management->addLink($request->user(), $community, $data['label'], $data['url']);

            return redirect()->route('communities.manage', $community)->with('status', 'リンクを追加しました');
        } catch (ApiException $e) {
            return redirect()->route('communities.manage', $community)->with('error', $e->getMessage());
        }
    }

    public function destroyLink(Request $request, Community $community, CommunityLink $link)
    {
        try {
            $this->management->removeLink($request->user(), $community, $link);

            return redirect()->route('communities.manage', $community)->with('status', 'リンクを削除しました');
        } catch (ApiException $e) {
            return redirect()->route('communities.manage', $community)->with('error', $e->getMessage());
        }
    }

    public function storeAircraft(Request $request, Community $community)
    {
        $data = $request->validate([
            'registration' => ['required', 'string', 'max:10'],
            'icao_type' => ['required', 'string', 'size:4'],
            'livery_name' => ['nullable', 'string', 'max:100'],
            'sim_platform' => ['required', 'in:ANY,MSFS2020,MSFS2024,XP12'],
            'home_base_icao' => ['required', 'string', 'size:4'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'airline_id' => ['nullable', 'integer'],
        ]);

        try {
            $aircraft = $this->management->addAircraft($request->user(), $community, $data);

            return redirect()->route('communities.manage', $community)
                ->with('status', "{$aircraft->registration} を機体一覧に追加しました");
        } catch (ApiException $e) {
            return redirect()->route('communities.manage', $community)->with('error', $e->getMessage());
        }
    }

    public function updateAircraft(Request $request, Community $community, Aircraft $aircraft)
    {
        $data = $request->validate([
            'status' => ['required', 'in:available,reserved,in_flight,maintenance,retired'],
            'current_location_icao' => ['required', 'string', 'size:4'],
            'airline_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->management->updateAircraft($request->user(), $community, $aircraft, $data);

            return redirect()->route('communities.manage', $community)
                ->with('status', "{$aircraft->registration} を更新しました");
        } catch (ApiException $e) {
            return redirect()->route('communities.manage', $community)->with('error', $e->getMessage());
        }
    }

    public function retireAircraft(Request $request, Community $community, Aircraft $aircraft)
    {
        try {
            $this->management->retireAircraft($request->user(), $community, $aircraft);

            return redirect()->route('communities.manage', $community)
                ->with('status', "{$aircraft->registration} を退役にしました");
        } catch (ApiException $e) {
            return redirect()->route('communities.manage', $community)->with('error', $e->getMessage());
        }
    }

    public function updateMaintenanceInterval(Request $request, Community $community, Aircraft $aircraft)
    {
        $data = $request->validate([
            'maintenance_interval_enabled' => ['nullable', 'boolean'],
            'maintenance_interval_minutes' => ['nullable', 'integer', 'min:1'],
        ]);
        $enabled = $request->boolean('maintenance_interval_enabled');

        try {
            $this->management->updateMaintenanceInterval(
                $request->user(), $community, $aircraft, $enabled, $data['maintenance_interval_minutes'] ?? null,
            );

            return redirect()->route('communities.manage', $community)
                ->with('status', "{$aircraft->registration} の整備インターバル設定を更新しました");
        } catch (ApiException $e) {
            return redirect()->route('communities.manage', $community)->with('error', $e->getMessage());
        }
    }

    public function auditLog(Request $request, Community $community)
    {
        $this->assertManager($request, $community);

        $logs = $community->auditLogs()->with('actor')->latest('id')->paginate(50);

        return view('communities.audit-log', ['community' => $community, 'logs' => $logs]);
    }

    private function assertManager(Request $request, Community $community): void
    {
        if (! $this->memberships->hasRole($request->user()->id, $community->id, ['owner', 'admin'])) {
            throw new ApiException(403, 'forbidden', '権限がありません');
        }
    }
}
