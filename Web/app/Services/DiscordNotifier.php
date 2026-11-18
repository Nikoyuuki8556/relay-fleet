<?php

namespace App\Services;

use App\Models\Aircraft;
use App\Models\Community;
use App\Models\Pirep;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 6.1節 Webhook通知。
 * 「コミュニティごとに設定されたIncoming Webhook URL」という要件のため、
 * webhook URLは communities テーブルに列を追加するか設定テーブルを別途持つ想定。
 * 本実装では communities.discord_guild_id とは別に、運用を簡潔にするため
 * config('relayfleet.discord_webhook_url') のグローバル既定値 + 将来的な
 * コミュニティ別webhook拡張ポイントとして notifyXxxAsync() を薄く分離してある。
 * 確度: 中。コミュニティ別webhook URL保持カラムはDDL(3章)に明記が無いため、
 * 運用開始前に community 側へ webhook_url カラムを追加するかどうか要確認。
 */
class DiscordNotifier
{
    public static function notifyPirepConfirmedAsync(Pirep $pirep): void
    {
        dispatch(function () use ($pirep) {
            $pirep->loadMissing(['aircraft', 'user']);
            self::post([
                'embeds' => [[
                    'title' => "{$pirep->aircraft->registration} が {$pirep->arrival_icao} に到着",
                    'description' => "@{$pirep->user->discord_username}",
                    'color' => 3066993,
                ]],
            ]);
        })->afterResponse();
    }

    public static function notifyJoinRequestAsync(Community $community, User $requester): void
    {
        dispatch(function () use ($community, $requester) {
            self::post([
                'embeds' => [[
                    'title' => "{$community->name} に新規参加リクエストがあります",
                    'description' => "申請者: @{$requester->discord_username}",
                    'color' => 15105570,
                ]],
            ]);
        })->afterResponse();
    }

    public static function notifyQueueClaimedAsync(Aircraft $aircraft, User $user): void
    {
        dispatch(function () use ($aircraft, $user) {
            self::post([
                'embeds' => [[
                    'title' => "{$aircraft->registration} の順番が回ってきました",
                    'description' => "@{$user->discord_username} さんに自動的に予約が割り当てられました",
                    'color' => 3447003,
                ]],
            ]);
        })->afterResponse();
    }

    private static function post(array $payload): void
    {
        $url = config('relayfleet.discord_webhook_url');
        if (! $url) {
            Log::info('Discord Webhook URL未設定のため通知をスキップしました', ['payload' => $payload]);

            return;
        }

        $response = Http::post($url, $payload);
        if ($response->failed()) {
            Log::warning('Discord Webhook送信に失敗しました', ['status' => $response->status()]);
        }
    }
}
