<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Discord OAuth2 (Authorization Code + PKCE)。
 * 7.4節のネイティブフロー、およびBlade側の通常ブラウザログインの両方から利用する共通サービス。
 * 参照: https://docs.discord.com/developers/topics/oauth2
 */
class DiscordOAuthService
{
    public function buildAuthorizeUrl(string $redirectUri, string $state, ?string $codeChallenge = null): string
    {
        $params = [
            'client_id' => config('relayfleet.discord_client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'identify email',
            'state' => $state,
        ];

        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
        }

        return 'https://discord.com/oauth2/authorize?'.http_build_query($params);
    }

    /**
     * @return array{access_token:string, discord_user:array}
     */
    public function exchangeCode(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        $payload = [
            'client_id' => config('relayfleet.discord_client_id'),
            'client_secret' => config('relayfleet.discord_client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];
        if ($codeVerifier !== null) {
            $payload['code_verifier'] = $codeVerifier;
        }

        $tokenResponse = Http::asForm()->post('https://discord.com/api/oauth2/token', $payload);
        if ($tokenResponse->failed()) {
            throw new ApiException(401, 'discord_oauth_failed', 'Discord認証に失敗しました');
        }

        $accessToken = $tokenResponse->json('access_token');

        $userResponse = Http::withToken($accessToken)->get('https://discord.com/api/users/@me');
        if ($userResponse->failed()) {
            throw new ApiException(401, 'discord_oauth_failed', 'Discordユーザー情報の取得に失敗しました');
        }

        return ['access_token' => $accessToken, 'discord_user' => $userResponse->json()];
    }

    public function findOrCreateUser(array $discordUser): User
    {
        $avatarUrl = $discordUser['avatar']
            ? "https://cdn.discordapp.com/avatars/{$discordUser['id']}/{$discordUser['avatar']}.png"
            : null;

        return User::updateOrCreate(
            ['discord_id' => $discordUser['id']],
            [
                'discord_username' => $discordUser['username'],
                'discord_avatar_url' => $avatarUrl,
                'email' => $discordUser['email'] ?? null,
            ],
        );
    }
}
