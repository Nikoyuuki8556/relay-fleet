<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\AircraftReservation;
use App\Services\MembershipService;
use App\Services\ReservationService;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly MembershipService $memberships,
    ) {
    }

    // POST /aircraft/{id}/reserve
    public function store(Request $request, Aircraft $aircraft)
    {
        if (! $this->memberships->hasActiveMembership($request->user()->id, $aircraft->community_id)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }

        $reservation = $this->reservations->reserve($request->user(), $aircraft);

        return response()->json([
            'reservation_id' => $reservation->id,
            'expires_at' => $reservation->expires_at->toIso8601String(),
        ], 201);
    }

    // POST /reservations/{id}/release
    public function release(Request $request, AircraftReservation $reservation)
    {
        $this->reservations->releaseVoluntarily($request->user(), $reservation);

        return response()->json(['status' => 'released']);
    }
}
