<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Aircraft;
use App\Models\AircraftReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 設計書 3.1節「排他制御の実装アルゴリズム」の疑似コードをそのまま実装。
 * 楽観的ロック（versionカラム）と悲観的ロック（SELECT ... FOR UPDATE）を併用する二重防御。
 * どちらか一方に削ってはいけない（3.1節注意書き）。
 */
class ReservationService
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly ReservationQueueService $queue,
    ) {
    }

    public function reserve(User $user, Aircraft $aircraftRef): AircraftReservation
    {
        if (! $this->memberships->hasActiveMembership($user->id, $aircraftRef->community_id)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }

        return DB::transaction(function () use ($user, $aircraftRef) {
            /** @var Aircraft $aircraft */
            $aircraft = Aircraft::query()->where('id', $aircraftRef->id)->lockForUpdate()->firstOrFail();

            if ($aircraft->status !== 'available') {
                throw new ApiException(409, 'in_use', 'この機体は現在使用中です');
            }

            $hours = (int) config('relayfleet.reservation_default_hours', 12);

            $reservation = AircraftReservation::create([
                'aircraft_id' => $aircraft->id,
                'reserved_by_user_id' => $user->id,
                'reserved_at' => now(),
                'expires_at' => now()->addHours($hours),
            ]);

            // 楽観的ロックの二重チェック: version が変わっていなければ更新できる。
            $affected = Aircraft::query()
                ->where('id', $aircraft->id)
                ->where('version', $aircraft->version)
                ->update(['status' => 'reserved', 'version' => $aircraft->version + 1]);

            if ($affected === 0) {
                // ここに来るのはFOR UPDATEでロックした直後の理論上ほぼ起こらないケース。
                // 二重防御の意図通り、万一の競合検出時は409で再試行を促す。
                throw new ApiException(409, 'conflict', '競合が発生しました。再試行してください');
            }

            return $reservation;
        });
    }

    public function releaseVoluntarily(User $user, AircraftReservation $reservation): void
    {
        DB::transaction(function () use ($user, $reservation) {
            $reservation = AircraftReservation::query()->where('id', $reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->released_at !== null) {
                throw new ApiException(410, 'already_released', 'この予約はすでに解放されています');
            }
            if ($reservation->reserved_by_user_id !== $user->id) {
                throw new ApiException(403, 'forbidden', '自分の予約のみキャンセルできます');
            }

            $reservation->released_at = now();
            $reservation->save();

            $aircraft = Aircraft::query()->where('id', $reservation->aircraft_id)->lockForUpdate()->first();
            if ($aircraft !== null && $aircraft->status === 'reserved') {
                $aircraft->status = 'available';
                $aircraft->version += 1;
                $aircraft->save();

                // 順番待ちキューがあれば自動的に次の人へ引き継ぐ（同一トランザクション内・同じロックを再利用）。
                $this->queue->promoteNextIfWaiting($aircraft);
            }
        });
    }

    /**
     * release_expired_reservations() — スケジューラから毎分呼ばれるセーフティネット。
     * DoD(c): 予約の同時使用防止はreserve()側のロックで担保するが、
     * これは「取り忘れ・アプリクラッシュ後の失効予約」を能動的に片付ける役割。
     */
    public function releaseExpired(): int
    {
        return DB::transaction(function () {
            $expired = AircraftReservation::query()
                ->whereNull('released_at')
                ->where('expires_at', '<', now())
                ->lockForUpdate()
                ->get();

            foreach ($expired as $reservation) {
                $reservation->released_at = now();
                $reservation->save();

                $aircraft = Aircraft::query()->where('id', $reservation->aircraft_id)->lockForUpdate()->first();
                if ($aircraft !== null && $aircraft->status === 'reserved') {
                    $aircraft->status = 'available';
                    $aircraft->version += 1;
                    $aircraft->save();

                    $this->queue->promoteNextIfWaiting($aircraft);
                }
            }

            if ($expired->isNotEmpty()) {
                Log::info('失効予約を解放しました', ['count' => $expired->count()]);
            }

            return $expired->count();
        });
    }
}
