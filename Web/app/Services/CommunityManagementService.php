<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Aircraft;
use App\Models\AircraftSchedule;
use App\Models\Airline;
use App\Models\Community;
use App\Models\CommunityLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 【設計書からの拡張】コミュニティの詳細管理系ユースケースをまとめたサービス。
 * MembershipServiceは「所属・役割」を、こちらは「コミュニティの中身（設定・関連リンク・機体・運用予定）」を担当する。
 *
 * 権限方針（設計書に明記が無いため、このバージョンで決めた運用ルール）:
 *   - コミュニティ設定変更・関連リンクCRUD・機体の退役       : owner/admin のみ
 *   - 機体の新規登録・ステータス編集・整備予定の登録          : owner/admin/dispatcher
 *   - イベント予定の登録                                     : アクティブなメンバーなら誰でも
 */
class CommunityManagementService
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly AuditLogService $auditLog,
    ) {
    }

    // --- コミュニティ設定 ---

    public function updateSettings(User $actor, Community $community, array $data): Community
    {
        $this->assertRole($actor, $community, ['owner', 'admin']);

        $community->update($data);

        $this->auditLog->record(
            $community->id, $actor->id, 'community.settings_updated',
            "コミュニティ設定を更新しました",
        );

        return $community;
    }

    // --- 関連リンク ---

    public function addLink(User $actor, Community $community, string $label, string $url): CommunityLink
    {
        $this->assertRole($actor, $community, ['owner', 'admin']);

        $nextOrder = (int) $community->links()->max('sort_order') + 1;

        $link = CommunityLink::create([
            'community_id' => $community->id,
            'label' => $label,
            'url' => $url,
            'sort_order' => $nextOrder,
        ]);

        $this->auditLog->record(
            $community->id, $actor->id, 'link.created',
            "関連リンク「{$label}」を追加しました", 'CommunityLink', $link->id,
        );

        return $link;
    }

    public function removeLink(User $actor, Community $community, CommunityLink $link): void
    {
        $this->assertRole($actor, $community, ['owner', 'admin']);

        if ($link->community_id !== $community->id) {
            throw new ApiException(404, 'not_found', 'リンクが見つかりません');
        }

        $label = $link->label;
        $link->delete();

        $this->auditLog->record(
            $community->id, $actor->id, 'link.deleted',
            "関連リンク「{$label}」を削除しました", 'CommunityLink', $link->id,
        );
    }

    // --- 航空会社（エアライン） ---

    public function addAirline(User $actor, Community $community, array $data): Airline
    {
        $this->assertRole($actor, $community, ['owner', 'admin', 'dispatcher']);

        $airline = Airline::create([
            'community_id' => $community->id,
            'name' => $data['name'],
            'icao_code' => $data['icao_code'] ?? null,
            'iata_code' => $data['iata_code'] ?? null,
        ]);

        $this->auditLog->record(
            $community->id, $actor->id, 'airline.created',
            "航空会社「{$airline->name}」を追加しました", 'Airline', $airline->id,
        );

        return $airline;
    }

    public function removeAirline(User $actor, Community $community, Airline $airline): void
    {
        $this->assertRole($actor, $community, ['owner', 'admin']);

        if ($airline->community_id !== $community->id) {
            throw new ApiException(404, 'not_found', '航空会社が見つかりません');
        }
        if ($airline->aircraft()->exists()) {
            throw new ApiException(409, 'airline_in_use', 'この航空会社に所属する機体があるため削除できません');
        }

        $name = $airline->name;
        $airline->delete();

        $this->auditLog->record(
            $community->id, $actor->id, 'airline.deleted',
            "航空会社「{$name}」を削除しました", 'Airline', $airline->id,
        );
    }

    // --- 機体 ---

    public function addAircraft(User $actor, Community $community, array $data): Aircraft
    {
        $this->assertRole($actor, $community, ['owner', 'admin', 'dispatcher']);
        $airlineId = $this->resolveAirlineId($community, $data['airline_id'] ?? null);

        $aircraft = Aircraft::create([
            'community_id' => $community->id,
            'airline_id' => $airlineId,
            'registration' => $data['registration'],
            'icao_type' => $data['icao_type'],
            'livery_name' => $data['livery_name'] ?? null,
            'sim_platform' => $data['sim_platform'] ?? 'ANY',
            'home_base_icao' => $data['home_base_icao'],
            'current_location_icao' => $data['home_base_icao'],
            'status' => 'available',
            'image_url' => $data['image_url'] ?? null,
        ]);

        $this->auditLog->record(
            $community->id, $actor->id, 'aircraft.created',
            "機体 {$aircraft->registration} を追加しました", 'Aircraft', $aircraft->id,
        );

        return $aircraft;
    }

    public function updateAircraft(User $actor, Community $community, Aircraft $aircraft, array $data): Aircraft
    {
        $this->assertRole($actor, $community, ['owner', 'admin', 'dispatcher']);
        $this->assertBelongsToCommunity($aircraft, $community);

        if (array_key_exists('airline_id', $data)) {
            $data['airline_id'] = $this->resolveAirlineId($community, $data['airline_id']);
        }

        // 予約中/飛行中の機体を管理者判断で強制的にステータス変更すると
        // 進行中のPIREP提出との整合が崩れるため、行ロックを取ったうえでversionを上げる
        // （3.1節の楽観的ロックと同じ流儀に合わせる）。
        DB::transaction(function () use ($aircraft, $data) {
            $locked = Aircraft::query()->where('id', $aircraft->id)->lockForUpdate()->first();
            $locked->fill($data);
            $locked->version += 1;
            $locked->save();
        });

        $this->auditLog->record(
            $community->id, $actor->id, 'aircraft.updated',
            "機体 {$aircraft->registration} を更新しました", 'Aircraft', $aircraft->id, $data,
        );

        return $aircraft->fresh();
    }

    public function retireAircraft(User $actor, Community $community, Aircraft $aircraft): void
    {
        $this->assertRole($actor, $community, ['owner', 'admin']);
        $this->assertBelongsToCommunity($aircraft, $community);

        if (in_array($aircraft->status, ['reserved', 'in_flight'], true)) {
            throw new ApiException(409, 'aircraft_in_use', '使用中の機体は退役できません。予約解放後に行ってください');
        }

        $aircraft->update(['status' => 'retired']);

        $this->auditLog->record(
            $community->id, $actor->id, 'aircraft.retired',
            "機体 {$aircraft->registration} を退役にしました", 'Aircraft', $aircraft->id,
        );
    }

    // --- 整備インターバル ---

    public function updateMaintenanceInterval(User $actor, Community $community, Aircraft $aircraft, bool $enabled, ?int $minutes): Aircraft
    {
        $this->assertRole($actor, $community, ['owner', 'admin', 'dispatcher']);
        $this->assertBelongsToCommunity($aircraft, $community);

        if ($enabled && ($minutes === null || $minutes < 1)) {
            throw new ApiException(422, 'invalid_interval', '整備インターバルを有効にする場合は分数を指定してください');
        }

        $aircraft->maintenance_interval_enabled = $enabled;
        $aircraft->maintenance_interval_minutes = $enabled ? $minutes : null;
        $aircraft->save();

        $this->auditLog->record(
            $community->id, $actor->id, 'aircraft.maintenance_interval_updated',
            $enabled
                ? "{$aircraft->registration} の整備インターバルを有効化しました（{$minutes}分ごと）"
                : "{$aircraft->registration} の整備インターバルを無効化しました",
            'Aircraft', $aircraft->id,
        );

        return $aircraft;
    }

    // --- 機体運用予定 ---

    public function addSchedule(User $actor, Community $community, Aircraft $aircraft, array $data): AircraftSchedule
    {
        $this->assertBelongsToCommunity($aircraft, $community);

        $type = $data['type'] ?? 'event';
        if ($type === 'maintenance') {
            $this->assertRole($actor, $community, ['owner', 'admin', 'dispatcher']);
        } elseif (! $this->memberships->hasActiveMembership($actor->id, $community->id)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }

        $schedule = AircraftSchedule::create([
            'aircraft_id' => $aircraft->id,
            'created_by_user_id' => $actor->id,
            'type' => $type,
            'title' => $data['title'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        if ($type === 'maintenance') {
            $this->auditLog->record(
                $community->id, $actor->id, 'schedule.maintenance_created',
                "{$aircraft->registration} に整備予定「{$schedule->title}」を登録しました", 'AircraftSchedule', $schedule->id,
            );
        }

        return $schedule;
    }

    public function removeSchedule(User $actor, Community $community, AircraftSchedule $schedule): void
    {
        $isManager = $this->memberships->hasRole($actor->id, $community->id, ['owner', 'admin', 'dispatcher']);
        $isCreator = $schedule->created_by_user_id === $actor->id;

        if (! $isManager && ! $isCreator) {
            throw new ApiException(403, 'forbidden', '削除できるのは登録者か管理者のみです');
        }

        $type = $schedule->type;
        $title = $schedule->title;
        $schedule->delete();

        if ($type === 'maintenance') {
            $this->auditLog->record(
                $community->id, $actor->id, 'schedule.maintenance_deleted',
                "整備予定「{$title}」を削除しました", 'AircraftSchedule', $schedule->id,
            );
        }
    }

    // --- 内部ヘルパー ---

    private function assertRole(User $actor, Community $community, array $roles): void
    {
        if (! $this->memberships->hasRole($actor->id, $community->id, $roles)) {
            throw new ApiException(403, 'forbidden', '権限がありません');
        }
    }

    private function assertBelongsToCommunity(Aircraft $aircraft, Community $community): void
    {
        if ($aircraft->community_id !== $community->id) {
            throw new ApiException(404, 'not_found', '機体が見つかりません');
        }
    }

    private function resolveAirlineId(Community $community, ?int $airlineId): ?int
    {
        if ($airlineId === null) {
            return null;
        }

        $belongs = Airline::query()->where('id', $airlineId)->where('community_id', $community->id)->exists();
        if (! $belongs) {
            throw new ApiException(422, 'invalid_airline', '指定した航空会社が見つかりません');
        }

        return $airlineId;
    }
}
