<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

// 3.1節 release_expired_reservations()。routes/console.phpのスケジューラから毎分呼ばれる。
class ReleaseExpiredReservations extends Command
{
    protected $signature = 'reservations:release-expired';
    protected $description = '期限切れ予約を解放し、機体をavailableに戻す';

    public function handle(ReservationService $service): int
    {
        $count = $service->releaseExpired();
        if ($count > 0) {
            $this->info("{$count}件の失効予約を解放しました");
        }

        return self::SUCCESS;
    }
}
