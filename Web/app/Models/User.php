<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    // password / password_reset_tokens は存在しない（Discord/VATSIM OAuthのみ）。
    protected $fillable = [
        'discord_id',
        'discord_username',
        'discord_avatar_url',
        'vatsim_cid',
        'vatsim_rating',
        'vatsim_full_name',
        'email',
    ];

    protected $hidden = [];

    public function memberships(): HasMany
    {
        return $this->hasMany(CommunityMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active');
    }

    public function pireps(): HasMany
    {
        return $this->hasMany(Pirep::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(AircraftReservation::class, 'reserved_by_user_id');
    }

    public function displayName(): string
    {
        return $this->discord_username ?? $this->vatsim_full_name ?? ('User #'.$this->id);
    }
}
