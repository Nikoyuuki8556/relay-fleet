<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * VATSIM Connect (OAuth2)。
 * 確度: 中〜低。設計書10章の未確定事項のとおり、PKCE対応可否は実装開始時に
 * https://vatsim.dev/services/connect/ の一次情報で必ず再確認すること。
 * 本実装は「PKCE対応済み」を仮定した骨組みであり、非対応と判明した場合は
 * code_verifier/code_challengeの送信を外し、confidential client（client_secret）方式に切り替える。
 */
class VatsimOAuthService
{
    public function buildAuthorizeUrl(string $redirectUri, string $state, ?string $codeChallenge = null): string
    {
        $domain = config('relayfleet.vatsim_connect_domain');
        $params = [
            'client_id' => config('relayfleet.vatsim_connect_client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'full_name email country vatsim_details',
            'state' => $state,
        ];
        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
        }

        return "{$domain}/oauth/authorize?".http_build_query($params);
    }

    public function exchangeCode(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        $domain = config('relayfleet.vatsim_connect_domain');

        $payload = [
            'client_id' => config('relayfleet.vatsim_connect_client_id'),
            'client_secret' => config('relayfleet.vatsim_connect_client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];
        if ($codeVerifier !== null) {
            $payload['code_verifier'] = $codeVerifier;
        }

        $tokenResponse = Http::asForm()->post("{$domain}/oauth/token", $payload);
        if ($tokenResponse->failed()) {
            throw new ApiException(401, 'vatsim_oauth_failed', 'VATSIM認証に失敗しました');
        }

        $accessToken = $tokenResponse->json('access_token');

        $userResponse = Http::withToken($accessToken)->get("{$domain}/api/user");
        if ($userResponse->failed()) {
            throw new ApiException(401, 'vatsim_oauth_failed', 'VATSIMユーザー情報の取得に失敗しました');
        }

        return ['access_token' => $accessToken, 'vatsim_user' => $userResponse->json('data', $userResponse->json())];
    }

    public function findOrCreateUser(array $vatsimUser): User
    {
        $cid = $vatsimUser['cid'] ?? null;
        if ($cid === null) {
            throw new ApiException(422, 'vatsim_cid_missing', 'VATSIM CIDを取得できませんでした');
        }

        return User::updateOrCreate(
            ['vatsim_cid' => (int) $cid],
            [
                'vatsim_rating' => $vatsimUser['vatsim']['rating']['short'] ?? null,
                'vatsim_full_name' => trim(($vatsimUser['personal']['name_first'] ?? '').' '.($vatsimUser['personal']['name_last'] ?? '')),
                'email' => $vatsimUser['personal']['email'] ?? null,
            ],
        );
    }
}
