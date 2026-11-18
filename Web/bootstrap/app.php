<?php

use App\Exceptions\ApiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'membership' => \App\Http\Middleware\EnsureActiveCommunityMembership::class,
        ]);

        // Discord Interactions EndpointはCSRFトークンを持たない外部からのPOSTのため除外する。
        // その代わり DiscordInteractionController で Ed25519 署名検証を必須にしている（6.2節）。
        $middleware->validateCsrfTokens(except: [
            'discord/interactions',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 5章: エラーレスポンス共通形式 {"error": "コード", "message": "..."}
        // ただしBlade側（ブラウザ）からの通常アクセスでは生JSONを見せず、
        // 直前のページに戻してエラーメッセージをフラッシュする方が体験が良いためルートで分岐する。
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $e->render();
            }

            return redirect()->back()->with('error', $e->getMessage());
        });
    })->create();
