@extends('layouts.app')
@section('title', 'ダッシュボード — RelayFleet')
@section('content')

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">所属コミュニティ</h1>
        <p class="text-sm text-slate-500 mt-1">あなたが所属している、または承認待ちのコミュニティ一覧です。</p>
    </div>
    <div class="flex gap-3">
        <a href="{{ route('communities.directory') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition">
            コミュニティを探す
        </a>
        <a href="{{ route('communities.create') }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-sky-200 transition">
            ＋ コミュニティを作成
        </a>
    </div>
</div>

<div class="grid gap-4 mb-10">
    @forelse ($memberships as $m)
        <a href="{{ route('communities.show', $m->community) }}"
           class="group block rounded-2xl border border-slate-200 bg-white hover:border-sky-300 hover:shadow-md px-6 py-5 shadow-sm transition">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-semibold text-slate-900 text-lg group-hover:text-sky-600 transition">{{ $m->community->name }}</div>
                    <div class="mt-1.5 flex items-center gap-2 text-xs">
                        @php
                            $roleColors = [
                                'owner' => 'bg-fuchsia-50 text-fuchsia-600 border-fuchsia-200',
                                'admin' => 'bg-indigo-50 text-indigo-600 border-indigo-200',
                                'dispatcher' => 'bg-sky-50 text-sky-600 border-sky-200',
                                'pilot' => 'bg-slate-100 text-slate-600 border-slate-200',
                            ];
                        @endphp
                        <span class="rounded-full border px-2 py-0.5 {{ $roleColors[$m->role] ?? $roleColors['pilot'] }}">{{ $m->role }}</span>
                        @if ($m->status === 'pending')
                            <span class="rounded-full border border-amber-200 bg-amber-50 text-amber-600 px-2 py-0.5">承認待ち</span>
                        @endif
                    </div>
                </div>
                <span class="text-slate-300 group-hover:text-sky-400 transition">→</span>
            </div>
        </a>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 px-6 py-10 text-center text-slate-500">
            まだどのコミュニティにも所属していません。右上から新しく作成するか、招待コードを受け取って参加してください。
        </div>
    @endforelse
</div>

<div class="rounded-2xl border border-slate-200 bg-white p-6 max-w-md shadow-sm">
    <h2 class="font-semibold text-slate-900 mb-1">招待コードで参加</h2>
    <p class="text-sm text-slate-500 mb-4">既存メンバーから受け取ったコードを入力してください。</p>
    <form method="POST" action="{{ route('communities.join-by-code') }}" class="flex gap-2">
        @csrf
        <input type="text" name="code" placeholder="例: ABC12345" maxlength="16" required
               class="flex-1 rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
        <button type="submit"
                class="rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white transition">参加</button>
    </form>
</div>
@endsection
