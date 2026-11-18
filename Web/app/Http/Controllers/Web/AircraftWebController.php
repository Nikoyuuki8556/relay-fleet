<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\AircraftReservation;
use App\Models\AircraftReservationQueue;
use App\Services\PirepService;
use App\Services\ReservationQueueService;
use App\Services\ReservationService;
use Illuminate\Http\Request;

// Blade側から機体の予約・解放・PIREP提出を行うコントローラ。
// REST API(Api\ReservationController/PirepController)と同じApp\Services\*を直接呼ぶ。
class AircraftWebController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly PirepService $pireps,
        private readonly ReservationQueueService $queue,
    ) {
    }

    public function reserve(Request $request, Aircraft $aircraft)
    {
        try {
            $this->reservations->reserve($request->user(), $aircraft);

            return redirect()->route('communities.show', $aircraft->community_id)
                ->with('status', "{$aircraft->registration} を予約しました");
        } catch (ApiException $e) {
            return redirect()->route('communities.show', $aircraft->community_id)->with('error', $e->getMessage());
        }
    }

    public function release(Request $request, AircraftReservation $reservation)
    {
        $communityId = $reservation->aircraft->community_id;

        try {
            $this->reservations->releaseVoluntarily($request->user(), $reservation);

            return redirect()->route('communities.show', $communityId)->with('status', '予約を解放しました');
        } catch (ApiException $e) {
            return redirect()->route('communities.show', $communityId)->with('error', $e->getMessage());
        }
    }

    public function joinQueue(Request $request, Aircraft $aircraft)
    {
        try {
            $this->queue->join($request->user(), $aircraft);

            return redirect()->route('communities.show', $aircraft->community_id)
                ->with('status', "{$aircraft->registration} の順番待ちに登録しました");
        } catch (ApiException $e) {
            return redirect()->route('communities.show', $aircraft->community_id)->with('error', $e->getMessage());
        }
    }

    public function leaveQueue(Request $request, AircraftReservationQueue $queueEntry)
    {
        $communityId = $queueEntry->aircraft->community_id;

        try {
            $this->queue->leave($request->user(), $queueEntry);

            return redirect()->route('communities.show', $communityId)->with('status', '順番待ちを解除しました');
        } catch (ApiException $e) {
            return redirect()->route('communities.show', $communityId)->with('error', $e->getMessage());
        }
    }

    public function submitPirep(Request $request)
    {
        $data = $request->validate([
            'reservation_id' => ['required', 'integer', 'exists:aircraft_reservations,id'],
            'departure_icao' => ['required', 'string', 'size:4'],
            'arrival_icao' => ['required', 'string', 'size:4'],
            'arrival_spot' => ['nullable', 'string', 'max:20'],
            'route' => ['nullable', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'arrival_time' => ['required', 'date', 'after:departure_time'],
            'flight_time_min' => ['required', 'integer', 'min:1'],
            'network' => ['nullable', 'in:vatsim,offline'],
            'vatsim_callsign' => ['nullable', 'string', 'max:16'],
        ]);

        $reservation = AircraftReservation::query()->with('aircraft')->findOrFail($data['reservation_id']);
        $communityId = $reservation->aircraft->community_id;

        try {
            $pirep = $this->pireps->submit($request->user(), $reservation, $data);

            return redirect()->route('communities.show', $communityId)
                ->with('status', "PIREPを提出しました（判定: {$pirep->verification_status}）");
        } catch (ApiException $e) {
            return redirect()->route('communities.show', $communityId)->with('error', $e->getMessage());
        }
    }
}
