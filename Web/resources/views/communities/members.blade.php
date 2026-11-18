@extends('layouts.app')
@section('title', $community->name.' のメンバー — RelayFleet')
@section('content')

@php
    $roleColors = [
        'owner' => 'bg-fuchsia-50 text-fuchsia-600 border-fuchsia-200',
        'admin' => 'bg-indigo-50 text-indigo-600 border-indigo-200',
        'dispatcher' => 'bg-sky-50 text-sky-600 border-sky-200',
        'pilot' => 'bg-slate-100 text-slate-600 border-slate-200',
    ];
    $statusColors = [
        'active' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
        'pending' => 'bg-amber-50 text-amber-600 border-amber-200',
        'banned' => 'bg-rose-50 text-rose-600 border-rose-200',
    ];
@endphp

<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ $community->name }} のメンバー</h1>
        <p class="text-sm text-slate-500 mt-1">ロールの変更・BAN／解除ができます。</p>
    </div>
    <a href="{{ route('communities.manage', $community) }}" class="text-slate-500 hover:text-slate-900 transition text-sm">← 管理画面に戻る</a>
</div>

<div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-5 py-3 font-medium">メンバー</th>
                <th class="px-5 py-3 font-medium">ロール</th>
                <th class="px-5 py-3 font-medium">ステータス</th>
                <th class="px-5 py-3 font-medium text-right">操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($members as $m)
                <tr class="border-t border-slate-100">
                    <td class="px-5 py-3 text-slate-700">
                        {{ $m->user->discord_username ?? $m->user->vatsim_full_name ?? ('User #'.$m->user_id) }}
                    </td>
                    <td class="px-5 py-3">
                        <span class="rounded-full border px-2 py-0.5 text-xs {{ $roleColors[$m->role] ?? $roleColors['pilot'] }}">{{ $m->role }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="rounded-full border px-2 py-0.5 text-xs {{ $statusColors[$m->status] ?? '' }}">{{ $m->status }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <form method="POST" action="{{ route('communities.members.update', [$community, $m->user_id]) }}" class="flex items-center gap-2">
                                @csrf
                                <select name="role" class="rounded-lg bg-white border border-slate-200 px-2 py-1 text-xs text-slate-800 focus:outline-none focus:border-sky-400">
                                    @foreach (['pilot','dispatcher','admin','owner'] as $r)
                                        <option value="{{ $r }}" @selected($m->role === $r)>{{ $r }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="rounded-lg bg-slate-100 hover:bg-slate-200 px-3 py-1 text-xs text-slate-700 transition">変更</button>
                            </form>

                            @if ($m->status === 'banned')
                                <form method="POST" action="{{ route('communities.members.update', [$community, $m->user_id]) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="active">
                                    <button type="submit" class="rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 px-3 py-1 text-xs transition">BAN解除</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('communities.members.update', [$community, $m->user_id]) }}"
                                      onsubmit="return confirm('BANしますか？')">
                                    @csrf
                                    <input type="hidden" name="status" value="banned">
                                    <button type="submit" class="rounded-lg border border-rose-200 text-rose-500 hover:bg-rose-50 px-3 py-1 text-xs transition">BAN</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
