<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityMembership extends Model
{
    use HasFactory;

    // 役割(role)はユーザーではなくここに属する。「1ユーザー=複数コミュニティ、コミュニティごとに異なるロール」を表現する。
    protected $fillable = [
        'community_id', 'user_id', 'role', 'status', 'invited_by_user_id', 'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAtLeast(string $role): bool
    {
        // owner > admin > dispatcher > pilot
        $order = ['pilot' => 0, 'dispatcher' => 1, 'admin' => 2, 'owner' => 3];

        return ($order[$this->role] ?? -1) >= ($order[$role] ?? PHP_INT_MAX);
    }
}
