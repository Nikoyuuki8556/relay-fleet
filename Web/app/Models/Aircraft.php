<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Aircraft extends Model
{
    use HasFactory;

    protected $table = 'aircraft';

    protected $fillable = [
        'community_id', 'airline_id', 'registration', 'icao_type', 'livery_name', 'sim_platform',
        'home_base_icao', 'current_location_icao', 'current_spot', 'status', 'version', 'image_url',
        'maintenance_interval_enabled', 'maintenance_interval_minutes', 'accumulated_minutes_since_maintenance',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_interval_enabled' => 'boolean',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(AircraftReservation::class);
    }

    public function activeReservation(): HasOne
    {
        return $this->hasOne(AircraftReservation::class)->whereNull('released_at')->latestOfMany();
    }

    public function pireps(): HasMany
    {
        return $this->hasMany(Pirep::class)->orderByDesc('created_at');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(AircraftSchedule::class)->orderBy('starts_at');
    }

    public function queueEntries(): HasMany
    {
        return $this->hasMany(AircraftReservationQueue::class);
    }

    public function waitingQueue(): HasMany
    {
        return $this->hasMany(AircraftReservationQueue::class)->where('status', 'waiting')->orderBy('requested_at');
    }
}
