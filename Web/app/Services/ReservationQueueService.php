<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Aircraft;
use App\Models\AircraftReservation;
use App\Models\AircraftReservationQueue;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 【設計書からの拡張】機体が使用中のときの順番待ちキュー。
 * join/leaveはユーザー操作、promoteNextIfWaiting()は予約解放のタイミングで
 * ReservationService/PirepServiceから呼ばれるシステム内部処理。
 *
 * promoteNextIfWaiting()は「呼び出し元がすでに$aircraftの行ロック(lockForUpdate)を
 * 取得済みのトランザクション内」から呼ぶことを前提にしている（二重ロック回避のため）。
 */
class ReservationQueueService
{
    public function __construct(private readonly MembershipService $memberships)
    {
    }

    public function join(User $user, Aircraft $aircraftRef): AircraftReservationQueue
    {
        if (! $this->memberships->hasActiveMembership($user->id, $aircraftRef->community_id)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }

        return DB::transaction(function () use ($user, $aircraftRef) {
            /** @var Aircraft $aircraft */
            $aircraft = Aircraft::query()->where('id', $aircraftRef->id)->lockForUpdate()->firstOrFail();

            if ($aircraft->status === 'available') {
                throw new ApiException(409, 'aircraft_available', 'この機体は現在空いています。直接予約してください');
            }

            $already = AircraftReservationQueue::query()
                ->where('aircraft_id', $aircraft->id)
                ->where('user_id', $user->id)
                ->where('status', 'waiting')
                ->exists();
            if ($already) {
                throw new ApiException(409, 'already_queued', 'すでに順番待ちに登録されています');
            }

            return AircraftReservationQueue::create([
                'aircraft_id' => $aircraft->id,
                'user_id' => $user->id,
                'requested_at' => now(),
                'status' => 'waiting',
            ]);
        });
    }

    public function leave(User $user, AircraftReservationQueue $entry): void
    {
        if ($entry->user_id !== $user->id) {
            throw new ApiException(403, 'forbidden', '自分の順番待ちのみ解除できます');
        }
        if ($entry->status !== 'waiting') {
            throw new ApiException(410, 'not_waiting', 'すでに処理済みの順番待ちです');
        }

        $entry->update(['status' => 'cancelled']);
    }

    public function promoteNextIfWaiting(Aircraft $aircraft): ?AircraftReservation
    {
        $next = AircraftReservationQueue::query()
            ->where('aircraft_id', $aircraft->id)
            ->where('status', 'waiting')
            ->oldest('requested_at')
            ->lockForUpdate()
            ->first();

        if ($next === null) {
            return null;
        }

        $hours = (int) config('relayfleet.reservation_default_hours', 12);

        $reservation = AircraftReservation::create([
            'aircraft_id' => $aircraft->id,
            'reserved_by_user_id' => $next->user_id,
            'reserved_at' => now(),
            'expires_at' => now()->addHours($hours),
        ]);

        $aircraft->status = 'reserved';
        $aircraft->version += 1;
        $aircraft->save();

        $next->update(['status' => 'claimed', 'claimed_at' => now()]);

        DiscordNotifier::notifyQueueClaimedAsync($aircraft, $next->user);

        return $reservation;
    }
}
