@extends('layouts.app')
@section('title', 'コミュニティを探す — RelayFleet')
@section('content')

<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-900">公開コミュニティ一覧</h1>
    <p class="text-sm text-slate-500 mt-1">公開設定になっているコミュニティです。招待コード制のコミュニティはここには表示されません。</p>
</div>

<div class="grid sm:grid-cols-2 gap-4">
    @forelse ($communities as $c)
        @php
            $policyLabels = [
                'open' => ['即時参加', 'bg-emerald-50 text-emerald-600 border-emerald-200'],
                'request' => ['承認制', 'bg-amber-50 text-amber-600 border-amber-200'],
                'invite_only' => ['招待制', 'bg-slate-100 text-slate-500 border-slate-200'],
            ];
            [$policyLabel, $policyClass] = $policyLabels[$c->join_policy] ?? $policyLabels['invite_only'];
            $alreadyMember = $myCommunityIds->contains($c->id);
        @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-6 flex flex-col shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="font-semibold text-slate-900 text-lg">{{ $c->name }}</div>
                <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs {{ $policyClass }}">{{ $policyLabel }}</span>
            </div>
            <p class="text-sm text-slate-500 mt-2 mb-4 flex-1">{{ $c->description ?: '説明はまだありません' }}</p>

            @if ($alreadyMember)
                <a href="{{ route('communities.show', $c) }}"
                   class="text-center rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600">
                    参加済み — 開く
                </a>
            @elseif ($c->join_policy === 'invite_only')
                <span class="text-center rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-400">招待コードが必要です</span>
            @else
                <form method="POST" action="{{ route('communities.join', $c) }}">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white transition">
                        {{ $c->join_policy === 'open' ? '参加する' : '参加リクエストを送る' }}
                    </button>
                </form>
            @endif
        </div>
    @empty
        <div class="col-span-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 px-6 py-10 text-center text-slate-500">
            公開コミュニティはまだありません。
        </div>
    @endforelse
</div>

<div class="mt-6">{{ $communities->links() }}</div>
@endsection
