<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\DiscordOAuthService;
use App\Services\VatsimOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

// Bladeブラウザログイン用（通常のOAuth2 Authorization Codeフロー、セッション認証）。
class WebAuthController extends Controller
{
    public function __construct(
        private readonly DiscordOAuthService $discord,
        private readonly VatsimOAuthService $vatsim,
    ) {
    }

    public function discordRedirect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('oauth_state', $state);

        return redirect($this->discord->buildAuthorizeUrl(config('relayfleet.discord_redirect_uri'), $state));
    }

    public function discordCallback(Request $request)
    {
        abort_unless($request->query('state') === $request->session()->pull('oauth_state'), 419, 'stateが一致しません');

        $result = $this->discord->exchangeCode($request->query('code'), config('relayfleet.discord_redirect_uri'));
        $user = $this->discord->findOrCreateUser($result['discord_user']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function vatsimRedirect(Request $request)
    {
        // VATSIM Connect側のアプリ申請が承認されるまでの暫定措置。config('relayfleet.vatsim_login_enabled')参照。
        if (! config('relayfleet.vatsim_login_enabled')) {
            return redirect()->route('welcome')->with('error', 'VATSIMログインは現在申請中のため一時的に利用できません。Discordログインをご利用ください');
        }

        $state = Str::random(40);
        $request->session()->put('oauth_state', $state);

        return redirect($this->vatsim->buildAuthorizeUrl(config('relayfleet.vatsim_connect_redirect_uri'), $state));
    }

    public function vatsimCallback(Request $request)
    {
        if (! config('relayfleet.vatsim_login_enabled')) {
            return redirect()->route('welcome')->with('error', 'VATSIMログインは現在申請中のため一時的に利用できません');
        }

        abort_unless($request->query('state') === $request->session()->pull('oauth_state'), 419, 'stateが一致しません');

        $result = $this->vatsim->exchangeCode($request->query('code'), config('relayfleet.vatsim_connect_redirect_uri'));
        $user = $this->vatsim->findOrCreateUser($result['vatsim_user']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }
}
