@extends('layouts.app')
@section('title', 'コミュニティを作成 — RelayFleet')
@section('content')

<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-slate-900 mb-1">コミュニティを作成</h1>
    <p class="text-sm text-slate-500 mb-8">作成すると、あなたが自動的にオーナーになります。</p>

    <form method="POST" action="{{ route('communities.store') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">コミュニティ名</label>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
                   class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400"
                   placeholder="例: 日本上空バーチャル航空">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">説明（任意）</label>
            <textarea name="description" rows="3" maxlength="500"
                      class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400"
                      placeholder="コミュニティの雰囲気や活動内容など">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">公開範囲</label>
            <select name="visibility" class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <option value="private" selected>非公開（招待コードのみで参加可能）</option>
                <option value="public">公開（コミュニティを探す一覧に表示）</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">参加ポリシー</label>
            <select name="join_policy" class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <option value="invite_only" selected>招待制（招待コードが必要）</option>
                <option value="request">承認制（参加リクエストをオーナー/管理者が承認）</option>
                <option value="open">即時参加（誰でもすぐ参加可能・公開時のみ意味を持つ）</option>
            </select>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_recruiting" value="1" checked
                   class="rounded border-slate-300 text-sky-500 focus:ring-sky-400">
            募集中として表示する
        </label>

        <button type="submit"
                class="w-full rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-200 transition">
            作成する
        </button>
    </form>
</div>
@endsection
