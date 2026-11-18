<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Jobs\VerifyPirepJob;
use App\Models\Aircraft;
use App\Models\AircraftReservation;
use App\Models\AircraftSchedule;
use App\Models\Pirep;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 設計書 3.1節 submit_pirep() の疑似コードをそのまま実装。
 * コミット後（トランザクション外）で非同期に Discord Webhook通知 と VATSIM自動検証ジョブ投入 を行う。
 *
 * 【設計書からの拡張】このサービスはPIREP確定時に機体を"手離す"唯一の場所でもあるため、
 * 整備インターバルの累積判定と、順番待ちキューへの引き継ぎもここで行う。
 */
class PirepService
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly ReservationQueueService $queue,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function submit(User $user, AircraftReservation $reservationRef, array $data): Pirep
    {
        if (! $this->memberships->hasActiveMembership($user->id, $reservationRef->aircraft->community_id)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }

        $pirep = DB::transaction(function () use ($user, $reservationRef, $data) {
            /** @var AircraftReservation $reservation */
            $reservation = AircraftReservation::query()->where('id', $reservationRef->id)->lockForUpdate()->firstOrFail();

            if ($reservation->reserved_by_user_id !== $user->id) {
                throw new ApiException(403, 'forbidden', '自分の予約に対してのみPIREPを提出できます');
            }
            if ($reservation->released_at !== null || $reservation->expires_at->isPast()) {
                throw new ApiException(410, 'reservation_expired', '予約が失効しています');
            }

            $pirep = Pirep::create([
                'aircraft_id' => $reservation->aircraft_id,
                'user_id' => $user->id,
                'reservation_id' => $reservation->id,
                'departure_icao' => $data['departure_icao'],
                'arrival_icao' => $data['arrival_icao'],
                'arrival_spot' => $data['arrival_spot'] ?? null,
                'route' => $data['route'] ?? null,
                'departure_time' => $data['departure_time'],
                'arrival_time' => $data['arrival_time'],
                'flight_time_min' => $data['flight_time_min'],
                'network' => $data['network'] ?? 'offline',
                'vatsim_callsign' => $data['vatsim_callsign'] ?? null,
                'verification_status' => 'pending',
            ]);

            $reservation->released_at = now();
            $reservation->save();

            $aircraft = Aircraft::query()->where('id', $reservation->aircraft_id)->lockForUpdate()->first();
            $aircraft->current_location_icao = $data['arrival_icao'];
            $aircraft->current_spot = $data['arrival_spot'] ?? null;
            $aircraft->status = 'available';
            $aircraft->version += 1;

            $this->applyMaintenanceInterval($aircraft, $data['flight_time_min'], $user);

            $aircraft->save();

            // 順番待ちキューがあれば自動的に次の人へ引き継ぐ（同一トランザクション内・同じロックを再利用）。
            $this->queue->promoteNextIfWaiting($aircraft);

            return $pirep;
        });

        // --- コミット後（トランザクション外）で非同期に実行 ---
        DiscordNotifier::notifyPirepConfirmedAsync($pirep);
        VerifyPirepJob::dispatch($pirep->id);

        return $pirep;
    }

    /**
     * 整備インターバルがONの機体について、累積飛行時間を加算し、
     * 閾値を超えていて開いている整備予定がまだ無ければ自動で整備予定を1件作る。
     * $aircraftはまだsave()されていない前提（呼び出し側でまとめて保存する）。
     */
    private function applyMaintenanceInterval(Aircraft $aircraft, int $flightTimeMin, User $pilot): void
    {
        if (! $aircraft->maintenance_interval_enabled || ! $aircraft->maintenance_interval_minutes) {
            return;
        }

        $aircraft->accumulated_minutes_since_maintenance += $flightTimeMin;

        if ($aircraft->accumulated_minutes_since_maintenance < $aircraft->maintenance_interval_minutes) {
            return;
        }

        $hasOpenMaintenance = AircraftSchedule::query()
            ->where('aircraft_id', $aircraft->id)
            ->where('type', 'maintenance')
            ->where(function ($query) {
                $query->where('ends_at', '>=', now())->orWhereNull('ends_at');
            })
            ->exists();

        if (! $hasOpenMaintenance) {
            $schedule = AircraftSchedule::create([
                'aircraft_id' => $aircraft->id,
                'created_by_user_id' => $pilot->id,
                'type' => 'maintenance',
                'title' => '整備予定（自動生成: 累積飛行時間超過）',
                'starts_at' => now(),
                'note' => "累積飛行時間が設定値（{$aircraft->maintenance_interval_minutes}分）を超えたため自動登録されました",
            ]);

            $this->auditLog->record(
                $aircraft->community_id, null, 'schedule.maintenance_auto_created',
                "{$aircraft->registration} が累積飛行時間の上限に達したため整備予定を自動登録しました",
                'AircraftSchedule', $schedule->id,
            );
        }

        // 通知済み/未通知に関わらず、閾値超過のたびに再通知されないようリセットする。
        $aircraft->accumulated_minutes_since_maintenance = 0;
    }
}
