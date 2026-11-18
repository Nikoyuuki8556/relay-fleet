<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AircraftReservation;
use App\Services\PirepService;
use Illuminate\Http\Request;

class PirepController extends Controller
{
    public function __construct(private readonly PirepService $pireps)
    {
    }

    // POST /pireps {"reservation_id":123,"departure_icao":"RJTT",...}
    public function store(Request $request)
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
        $pirep = $this->pireps->submit($request->user(), $reservation, $data);

        return response()->json([
            'pirep_id' => $pirep->id,
            'verification_status' => $pirep->verification_status,
        ], 201);
    }

    // GET /pireps?user_id=me
    public function index(Request $request)
    {
        $query = \App\Models\Pirep::query()->with(['aircraft']);

        if ($request->query('user_id') === 'me') {
            $query->where('user_id', $request->user()->id);
        }

        return $query->orderByDesc('id')->paginate(20);
    }
}
