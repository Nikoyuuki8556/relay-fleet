<?php

namespace App\Console\Commands;

use App\Services\VatsimVerificationService;
use Illuminate\Console\Command;

/**
 * 4.2節: VATSIM Data APIポーラー。
 *
 * 【変更履歴】当初はSupervisor管理の常駐プロセス（while(true)+sleep）を想定していたが、
 * デプロイ先がXserver共用レンタルサーバー（root権限なし、常駐プロセス不可）と判明したため、
 * 「1回ポーリングしたら終了する」単発コマンドに変更した。
 * routes/console.php の Schedule::command(PollVatsimData::class)->everyMinute() から
 * 呼ばれる想定で、実行間隔の制御はcron（外部から毎分 `php artisan schedule:run` を叩く）側が担う。
 *
 * 4.2節の「15秒ではなく60秒間隔にする理由 = 公式ドキュメント上のreloadフィールド最小粒度が1分」
 * という判断とも矛盾しない（毎分実行は60秒間隔とほぼ同義）。
 *
 * 確度: 中。Xserverのcron最短間隔がプランによっては1分より粗い場合があるため、
 * 実際にコントロールパネルの「Cron設定」で設定可能な最短間隔を確認すること。
 * VPS/専用サーバーに移行する場合は、常駐ループ版に戻す方が取りこぼしなく安定する
 * （deployment/supervisor-relayfleet.conf 参照、その場合はこのコマンドをloop版に書き換える）。
 */
class PollVatsimData extends Command
{
    protected $signature = 'vatsim:poll';
    protected $description = 'VATSIM Data APIを1回ポーリングしてvatsim_snapshotsを更新する（cron/スケジューラから毎分呼ばれる想定）';

    public function handle(VatsimVerificationService $service): int
    {
        try {
            $count = $service->poll();
            $this->info(now()->toDateTimeString()." pilots={$count}");
        } catch (\Throwable $e) {
            $this->error('ポーリング中にエラー: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
