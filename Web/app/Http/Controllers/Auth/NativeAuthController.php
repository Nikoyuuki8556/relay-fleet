<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\DiscordOAuthService;
use App\Services\VatsimOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 7.4節「認証（ネイティブOAuth、RFC 8252準拠）」の e. に対応するトークン交換エンドポイント。
 * Windowsアプリはシステム既定ブラウザでDiscord/VATSIMの認可画面を開き、
 * ローカルループバック(http://127.0.0.1:xxxxx/callback)でcodeを受け取ったあと、
 * code + code_verifier をここに渡してSanctumの personal access token を受け取る。
 *
 * セキュリティ注意: redirect_uriはループバックアドレス(127.0.0.1)のみ許可する
 * （RFC 8252 8.3節、カスタムURLスキームは衝突リスクがあるため不採用 — 7.4節末尾）。
 */
class NativeAuthController extends Controller
{
    public function __construct(
        private readonly DiscordOAuthService $discord,
        private readonly VatsimOAuthService $vatsim,
    ) {
    }

    public function discordToken(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'code_verifier' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
        ]);

        $this->assertLoopbackRedirect($data['redirect_uri']);

        $result = $this->discord->exchangeCode($data['code'], $data['redirect_uri'], $data['code_verifier']);
        $user = $this->discord->findOrCreateUser($result['discord_user']);

        return $this->issueToken($user);
    }

    public function vatsimToken(Request $request)
    {
        // VATSIM Connect側のアプリ申請が承認されるまでの暫定措置。config('relayfleet.vatsim_login_enabled')参照。
        abort_unless(config('relayfleet.vatsim_login_enabled'), 503, 'VATSIMログインは現在申請中のため一時的に利用できません');

        $data = $request->validate([
            'code' => ['required', 'string'],
            'code_verifier' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
        ]);

        $this->assertLoopbackRedirect($data['redirect_uri']);

        $result = $this->vatsim->exchangeCode($data['code'], $data['redirect_uri'], $data['code_verifier']);
        $user = $this->vatsim->findOrCreateUser($result['vatsim_user']);

        return $this->issueToken($user);
    }

    private function assertLoopbackRedirect(string $redirectUri): void
    {
        $host = parse_url($redirectUri, PHP_URL_HOST);
        $allowed = config('relayfleet.discord_native_redirect_host', '127.0.0.1');
        abort_unless($host === $allowed || $host === 'localhost', 400, '不正なredirect_uriです');
    }

    private function issueToken($user)
    {
        $token = $user->createToken('RelayFleetTracker-'.Str::random(6));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user,
        ]);
    }
}
