<?php

use App\Console\Commands\PollVatsimData;
use App\Console\Commands\ReleaseExpiredReservations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 3.1節 release_expired_reservations() を毎分実行する。
// DBの行ロック運用のみに頼らず、失効予約を能動的に掃除するためのセーフティネット。
Schedule::command(ReleaseExpiredReservations::class)->everyMinute()->withoutOverlapping();

// 4.2節 VATSIM Data APIポーラー。
// 【Xserver共用レンタルサーバー向け変更】常駐プロセス（Supervisor管理のwhile(true)ループ）が
// 使えないホスティング環境のため、cron駆動のスケジューラ実行に変更した。
// サーバー側のcron設定で `* * * * * php /path/to/artisan schedule:run` を1分毎に登録すること
// （Web/README.mdの「Xserver共用レンタルサーバーへのデプロイ」参照）。
Schedule::command(PollVatsimData::class)->everyMinute()->withoutOverlapping();
