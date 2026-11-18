<?php

namespace App\Services;

use App\Models\Pirep;
use App\Models\VatsimSnapshot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 4.2節「検証（PIREP自動検証）」の実装。
 * - poll(): VATSIM Data APIを60秒間隔でGETし、vatsim_snapshotsにUPSERTする（常駐ワーカー側から呼ぶ）。
 * - verifyPirep(): PIREP提出後、非同期ジョブから呼ばれる照合ロジック。
 *
 * 確度メモ: VATSIM API利用規約（キャッシュ保持期間・再配布制限）は設計書10章時点で未確認。
 * 本番公開前に vatsim.dev の一次情報で必ず確認すること。
 */
class VatsimVerificationService
{
    public function poll(): int
    {
        $url = config('relayfleet.vatsim_data_api_url');
        $response = Http::timeout(15)->get($url);

        if ($response->failed()) {
            Log::warning('VATSIM Data API取得に失敗しました', ['status' => $response->status()]);

            return 0;
        }

        $pilots = $response->json('pilots', []);
        $now = now();
        $count = 0;

        foreach ($pilots as $pilot) {
            $cid = $pilot['cid'] ?? null;
            $callsign = $pilot['callsign'] ?? null;
            $logonTime = $pilot['logon_time'] ?? null;
            if (! $cid || ! $callsign || ! $logonTime) {
                continue;
            }

            VatsimSnapshot::updateOrCreate(
                ['cid' => $cid, 'callsign' => $callsign, 'logon_time' => $logonTime],
                [
                    'departure' => $pilot['flight_plan']['departure'] ?? null,
                    'arrival' => $pilot['flight_plan']['arrival'] ?? null,
                    'last_seen_at' => $now,
                    'raw_json' => $pilot,
                ],
            );
            $count++;
        }

        return $count;
    }

    public function verifyPirep(int $pirepId): void
    {
        /** @var Pirep|null $pirep */
        $pirep = Pirep::query()->with('user')->find($pirepId);
        if ($pirep === null) {
            return;
        }
        if ($pirep->network !== 'vatsim') {
            return; // offlineフライトは自動検証対象外、pendingのまま
        }

        $cid = $pirep->user->vatsim_cid;
        if ($cid === null || $pirep->vatsim_callsign === null) {
            return;
        }

        $match = VatsimSnapshot::query()
            ->where('cid', $cid)
            ->where('callsign', $pirep->vatsim_callsign)
            ->where('departure', $pirep->departure_icao)
            ->where('arrival', $pirep->arrival_icao)
            ->whereBetween('logon_time', [
                $pirep->departure_time->copy()->subMinutes(10),
                $pirep->departure_time->copy()->addMinutes(10),
            ])
            ->exists();

        if ($match) {
            $pirep->verification_status = 'auto_verified';
            $pirep->save();
        }
        // 不一致ならpendingのまま。人力レビュー(dispatcher/admin)に委ねる。
    }
}
