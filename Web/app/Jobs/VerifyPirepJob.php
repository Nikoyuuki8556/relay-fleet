<?php

namespace App\Jobs;

use App\Services\VatsimVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// 3.1節 submit_pirep() 末尾: 「VATSIM自動検証ジョブをキューに投入」を担うジョブ。
class VerifyPirepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public readonly int $pirepId)
    {
    }

    public function handle(VatsimVerificationService $service): void
    {
        $service->verifyPirep($this->pirepId);
    }
}
