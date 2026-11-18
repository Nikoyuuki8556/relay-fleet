<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pirep extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'aircraft_id', 'user_id', 'reservation_id', 'departure_icao', 'arrival_icao', 'arrival_spot', 'route',
        'departure_time', 'arrival_time', 'flight_time_min', 'network', 'vatsim_callsign',
        'verification_status', 'volanta_flight_ref',
    ];

    protected function casts(): array
    {
        return [
            'departure_time' => 'datetime',
            'arrival_time' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(AircraftReservation::class, 'reservation_id');
    }
}
