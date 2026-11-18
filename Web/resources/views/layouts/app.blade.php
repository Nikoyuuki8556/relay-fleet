<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>@yield('title', 'RelayFleet')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /*
          テーマ参考: https://www.dream-v.co.jp/ / https://only-no.co.jp/
          白基調 + 水色アクセント（only-no.co.jpのブランドカラー #8fdbe1 に寄せた水色を採用）。
        */
        body { font-family: 'Inter', 'Noto Sans JP', sans-serif; }
        .bg-grid {
            background-image: radial-gradient(circle, rgba(56,189,248,0.10) 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
    @stack('head')
</head>
<body class="bg-white text-slate-800 min-h-screen bg-grid">
    <nav class="sticky top-0 z-20 border-b border-slate-200 bg-white/85 backdrop-blur px-6 py-3">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="{{ route('welcome') }}" class="flex items-center gap-2 font-extrabold text-lg tracking-tight text-slate-900">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-sky-300 to-sky-500 text-white text-sm shadow-md shadow-sky-200">✈</span>
                RelayFleet
            </a>
            @auth
                <div class="flex items-center gap-5 text-sm">
                    <a href="{{ route('dashboard') }}" class="text-slate-500 hover:text-sky-600 transition">ダッシュボード</a>
                    <a href="{{ route('communities.directory') }}" class="text-slate-500 hover:text-sky-600 transition">コミュニティを探す</a>
                    <a href="{{ route('communities.create') }}" class="text-slate-500 hover:text-sky-600 transition">＋ 作成</a>
                    <div class="flex items-center gap-3 pl-4 border-l border-slate-200">
                        <span class="text-slate-400">{{ auth()->user()->discord_username ?? auth()->user()->vatsim_full_name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="text-slate-400 hover:text-rose-500 transition">ログアウト</button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-6 py-10">
        @if (session('status'))
            <div class="mb-6 flex items-start gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                <span>✓</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 flex items-start gap-3 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                <span>⚠</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                <p class="font-semibold mb-1">入力内容を確認してください</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="max-w-6xl mx-auto px-6 py-10 text-xs text-slate-400">
        RelayFleet — コミュニティ機体シェアシステム
    </footer>
    @stack('scripts')
</body>
</html>
