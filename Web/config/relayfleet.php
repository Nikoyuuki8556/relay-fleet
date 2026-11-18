<?php

return [
    // 3.1節: 予約のデフォルト有効期間
    'reservation_default_hours' => (int) env('RESERVATION_DEFAULT_HOURS', 12),

    // 6.1節: PIREP確定・参加リクエスト時のWebhook（暫定でグローバル1本。運用時はコミュニティ別に拡張検討）
    'discord_webhook_url' => env('DISCORD_WEBHOOK_URL'),
    'discord_application_public_key' => env('DISCORD_APPLICATION_PUBLIC_KEY'),
    'discord_client_id' => env('DISCORD_CLIENT_ID'),
    'discord_client_secret' => env('DISCORD_CLIENT_SECRET'),
    'discord_redirect_uri' => env('DISCORD_REDIRECT_URI'),
    'discord_native_redirect_host' => env('DISCORD_NATIVE_REDIRECT_HOST', '127.0.0.1'),

    // 4章: VATSIM連携
    // VATSIM Connect側でのアプリ申請が承認されるまでの暫定フラグ。falseの間はログインボタンを隠し、
    // 直接URLを叩かれた場合もリダイレクトで弾く。承認され次第 .env で true に切り替えるだけで復活する。
    'vatsim_login_enabled' => env('VATSIM_LOGIN_ENABLED', false),
    'vatsim_connect_client_id' => env('VATSIM_CONNECT_CLIENT_ID'),
    'vatsim_connect_client_secret' => env('VATSIM_CONNECT_CLIENT_SECRET'),
    'vatsim_connect_domain' => env('VATSIM_CONNECT_DOMAIN', 'https://auth.vatsim.net'),
    'vatsim_connect_redirect_uri' => env('VATSIM_CONNECT_REDIRECT_URI'),
    'vatsim_data_api_url' => env('VATSIM_DATA_API_URL', 'https://data.vatsim.net/v3/vatsim-data.json'),
    'vatsim_poll_interval_seconds' => (int) env('VATSIM_POLL_INTERVAL_SECONDS', 60),
];
