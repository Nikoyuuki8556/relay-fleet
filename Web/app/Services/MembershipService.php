<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\CommunityMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 設計書 3.2節「コミュニティメンバーシップ設計（3通りの参加方法）」の疑似コードをそのまま実装したもの。
 * 認可の原則: aircraft / aircraft_reservations / pireps に関わる全APIは、
 * まず has_active_membership() を通すこと（3.2節末尾）。
 */
class MembershipService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function hasActiveMembership(int $userId, int $communityId): bool
    {
        return CommunityMembership::query()
            ->where('community_id', $communityId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    public function hasRole(int $userId, int $communityId, array $roles): bool
    {
        $membership = CommunityMembership::query()
            ->where('community_id', $communityId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        return $membership !== null && in_array($membership->role, $roles, true);
    }

    public function createCommunity(User $user, array $attributes): Community
    {
        return DB::transaction(function () use ($user, $attributes) {
            $community = Community::create([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'] ?? $this->generateUniqueSlug($attributes['name']),
                'description' => $attributes['description'] ?? null,
                'icon_url' => $attributes['icon_url'] ?? null,
                'visibility' => $attributes['visibility'] ?? 'private',
                'join_policy' => $attributes['join_policy'] ?? 'invite_only',
                'is_recruiting' => $attributes['is_recruiting'] ?? true,
                'recruiting_message' => $attributes['recruiting_message'] ?? null,
            ]);

            CommunityMembership::create([
                'community_id' => $community->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $community;
        });
    }

    public function joinViaInviteCode(User $user, string $code): CommunityMembership
    {
        return DB::transaction(function () use ($user, $code) {
            /** @var CommunityInvite|null $invite */
            $invite = CommunityInvite::query()->where('code', $code)->lockForUpdate()->first();

            if ($invite === null) {
                throw new ApiException(404, 'invite_not_found', '無効な招待コードです');
            }
            if ($invite->revoked_at !== null) {
                throw new ApiException(404, 'invite_not_found', '無効な招待コードです');
            }
            if ($invite->expires_at !== null && $invite->expires_at->isPast()) {
                throw new ApiException(410, 'invite_expired', '招待コードの期限が切れています');
            }
            if ($invite->max_uses !== null && $invite->uses_count >= $invite->max_uses) {
                throw new ApiException(410, 'invite_exhausted', '招待コードの使用回数上限に達しています');
            }

            $membership = CommunityMembership::query()
                ->where('community_id', $invite->community_id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership !== null) {
                // ON DUPLICATE KEY UPDATE 相当: 再参加のケースを許容する。
                $membership->status = 'active';
                $membership->joined_at = now();
                $membership->invited_by_user_id = $invite->created_by_user_id;
                $membership->save();
            } else {
                $membership = CommunityMembership::create([
                    'community_id' => $invite->community_id,
                    'user_id' => $user->id,
                    'role' => 'pilot',
                    'status' => 'active',
                    'invited_by_user_id' => $invite->created_by_user_id,
                    'joined_at' => now(),
                ]);
            }

            $invite->increment('uses_count');

            return $membership;
        });
    }

    public function joinPublicCommunity(User $user, Community $community): array
    {
        if ($community->visibility !== 'public') {
            throw new ApiException(403, 'community_private', 'このコミュニティは非公開です');
        }

        return match ($community->join_policy) {
            'open' => $this->joinOpenCommunity($user, $community),
            'request' => $this->requestJoinCommunity($user, $community),
            'invite_only' => throw new ApiException(403, 'invite_required', 'このコミュニティは招待コードが必要です'),
            default => throw new ApiException(500, 'unknown_join_policy', '不明な参加ポリシーです'),
        };
    }

    private function joinOpenCommunity(User $user, Community $community): array
    {
        DB::transaction(function () use ($user, $community) {
            CommunityMembership::updateOrCreate(
                ['community_id' => $community->id, 'user_id' => $user->id],
                ['role' => 'pilot', 'status' => 'active', 'joined_at' => now()],
            );
        });

        return ['status' => 'active', 'message' => '参加しました'];
    }

    private function requestJoinCommunity(User $user, Community $community): array
    {
        DB::transaction(function () use ($user, $community) {
            CommunityMembership::updateOrCreate(
                ['community_id' => $community->id, 'user_id' => $user->id],
                ['role' => 'pilot', 'status' => 'pending'],
            );
        });

        // NOTIFY admins (Discord Webhook等) "新規参加リクエストがあります"（非同期）
        DiscordNotifier::notifyJoinRequestAsync($community, $user);

        return ['status' => 'pending', 'message' => '参加リクエストを送信しました。承認をお待ちください'];
    }

    public function approveMembership(User $approver, Community $community, int $targetUserId): void
    {
        if (! $this->hasRole($approver->id, $community->id, ['owner', 'admin'])) {
            throw new ApiException(403, 'forbidden', '権限がありません');
        }

        CommunityMembership::query()
            ->where('community_id', $community->id)
            ->where('user_id', $targetUserId)
            ->where('status', 'pending')
            ->update(['status' => 'active', 'joined_at' => now()]);

        $this->auditLog->record(
            $community->id, $approver->id, 'membership.approved',
            "参加リクエスト（User #{$targetUserId}）を承認しました", 'CommunityMembership', $targetUserId,
        );
    }

    public function changeRoleOrBan(User $actor, Community $community, int $targetUserId, ?string $role, ?string $status): CommunityMembership
    {
        if (! $this->hasRole($actor->id, $community->id, ['owner', 'admin'])) {
            throw new ApiException(403, 'forbidden', '権限がありません');
        }

        $membership = CommunityMembership::query()
            ->where('community_id', $community->id)
            ->where('user_id', $targetUserId)
            ->firstOrFail();

        // オーナーは最低1人必要。最後のownerをownerでなくすことは禁止する。
        if ($membership->role === 'owner' && $role !== null && $role !== 'owner') {
            $ownerCount = CommunityMembership::where('community_id', $community->id)
                ->where('role', 'owner')->where('status', 'active')->count();
            if ($ownerCount <= 1) {
                throw new ApiException(422, 'last_owner', 'コミュニティには最低1人のオーナーが必要です');
            }
        }

        $oldRole = $membership->role;
        $oldStatus = $membership->status;

        if ($role !== null) {
            $membership->role = $role;
        }
        if ($status !== null) {
            $membership->status = $status;
        }
        $membership->save();

        if ($role !== null && $role !== $oldRole) {
            $this->auditLog->record(
                $community->id, $actor->id, 'membership.role_changed',
                "User #{$targetUserId} のロールを {$oldRole} → {$role} に変更しました", 'CommunityMembership', $membership->id,
            );
        }
        if ($status !== null && $status !== $oldStatus) {
            $action = $status === 'banned' ? 'membership.banned' : 'membership.status_changed';
            $this->auditLog->record(
                $community->id, $actor->id, $action,
                "User #{$targetUserId} のステータスを {$oldStatus} → {$status} に変更しました", 'CommunityMembership', $membership->id,
            );
        }

        return $membership;
    }

    public function createInvite(User $actor, Community $community, ?int $maxUses, ?\DateTimeInterface $expiresAt): CommunityInvite
    {
        if (! $this->hasRole($actor->id, $community->id, ['owner', 'admin'])) {
            throw new ApiException(403, 'forbidden', '権限がありません');
        }

        return CommunityInvite::create([
            'community_id' => $community->id,
            'code' => $this->generateInviteCode(),
            'created_by_user_id' => $actor->id,
            'max_uses' => $maxUses,
            'uses_count' => 0,
            'expires_at' => $expiresAt,
        ]);
    }

    private function generateInviteCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (CommunityInvite::where('code', $code)->exists());

        return $code;
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 1;
        while (Community::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
