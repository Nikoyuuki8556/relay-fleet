<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use App\Services\DiscordSignatureVerifier;
use Illuminate\Http\Request;

/**
 * 6.2節「スラッシュコマンド（Interactions Endpoint）」。
 * 重要: X-Signature-Ed25519 / X-Signature-Timestamp の検証をしないとDiscord側からエンドポイントとして
 * 認可されない。type:1(PING)にはtype:1(PONG)を即返す実装がDiscord側の疎通確認で必須。
 */
class DiscordInteractionController extends Controller
{
    public function __construct(private readonly DiscordSignatureVerifier $verifier)
    {
    }

    public function handle(Request $request)
    {
        $signature = $request->header('X-Signature-Ed25519', '');
        $timestamp = $request->header('X-Signature-Timestamp', '');
        $rawBody = $request->getContent();
        $publicKey = config('relayfleet.discord_application_public_key');

        if (! $this->verifier->verify($signature, $timestamp, $rawBody, $publicKey)) {
            abort(401, 'invalid request signature');
        }

        $payload = json_decode($rawBody, true);

        // type: 1 (PING) -> type: 1 (PONG) を即返す（Discord側の疎通確認で必須）。
        if (($payload['type'] ?? null) === 1) {
            return response()->json(['type' => 1]);
        }

        // type: 2 (APPLICATION_COMMAND)
        if (($payload['type'] ?? null) === 2) {
            return $this->handleCommand($payload);
        }

        return response()->json(['type' => 4, 'data' => ['content' => '未対応のインタラクションです']]);
    }

    private function handleCommand(array $payload)
    {
        $name = $payload['data']['name'] ?? null;

        if ($name === 'aircraft') {
            $registration = collect($payload['data']['options'] ?? [])
                ->firstWhere('name', 'registration')['value'] ?? null;

            $aircraft = Aircraft::query()->where('registration', $registration)->first();

            $content = $aircraft
                ? "{$aircraft->registration}: {$aircraft->current_location_icao} / {$aircraft->status}"
                : "登録記号 {$registration} の機体は見つかりませんでした";

            return response()->json(['type' => 4, 'data' => ['content' => $content]]);
        }

        return response()->json(['type' => 4, 'data' => ['content' => '未知のコマンドです']]);
    }
}
