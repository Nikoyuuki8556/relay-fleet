<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * 【設計書からの拡張】管理者操作の監査ログを記録する薄いサービス。
 * community_id/actor_user_idはID直渡しにして、呼び出し側で余計なモデルロードを発生させない。
 * actor_user_id=null は整備インターバル自動生成のようなシステム主導の記録を表す。
 */
class AuditLogService
{
    public function record(
        int $communityId,
        ?int $actorUserId,
        string $action,
        string $summary,
        ?string $targetType = null,
        ?int $targetId = null,
        array $meta = [],
    ): void {
        AuditLog::create([
            'community_id' => $communityId,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'summary' => $summary,
            'meta' => $meta,
        ]);
    }
}
