<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AircraftReservationQueue extends Model
{
    public $timestamps = false;

    protected $table = 'aircraft_reservation_queue';

    protected $fillable = [
        'aircraft_id', 'user_id', 'requested_at', 'status', 'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'claimed_at' => 'datetime',
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
}
