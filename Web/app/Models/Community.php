<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Community extends Model
{
    use HasFactory;

    // owner_user_id は持たない。オーナーは memberships()->role='owner' の行で表現する（正規化・単一の真実源）。
    protected $fillable = [
        'name', 'slug', 'description', 'icon_url', 'discord_guild_id',
        'visibility', 'join_policy', 'is_recruiting', 'recruiting_message',
    ];

    protected function casts(): array
    {
        return [
            'is_recruiting' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CommunityMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active');
    }

    public function aircraft(): HasMany
    {
        return $this->hasMany(Aircraft::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(CommunityInvite::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(CommunityLink::class)->orderBy('sort_order');
    }

    public function airlines(): HasMany
    {
        return $this->hasMany(Airline::class)->orderBy('name');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function owner(): ?CommunityMembership
    {
        return $this->memberships()->where('role', 'owner')->first();
    }
}
