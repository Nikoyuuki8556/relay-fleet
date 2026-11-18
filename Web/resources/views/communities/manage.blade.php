@extends('layouts.app')
@section('title', $community->name.' の管理 — RelayFleet')
@section('content')

@php
    $statusStyles = [
        'available' => ['空きあり', 'bg-emerald-50 text-emerald-600 border-emerald-200'],
        'reserved' => ['予約済み', 'bg-amber-50 text-amber-600 border-amber-200'],
        'in_flight' => ['飛行中', 'bg-sky-50 text-sky-600 border-sky-200'],
        'maintenance' => ['整備中', 'bg-rose-50 text-rose-600 border-rose-200'],
        'retired' => ['退役', 'bg-slate-100 text-slate-500 border-slate-200'],
    ];
@endphp

<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ $community->name }} の管理</h1>
        <p class="text-sm text-slate-500 mt-1">オーナー/管理者だけがこの画面を開けます。</p>
    </div>
    <div class="flex gap-3 text-sm">
        <a href="{{ route('communities.show', $community) }}" class="text-slate-500 hover:text-slate-900 transition">← コミュニティに戻る</a>
        <a href="{{ route('communities.audit-log', $community) }}" class="text-sky-600 hover:text-sky-500 transition">監査ログ</a>
        <a href="{{ route('communities.members', $community) }}" class="text-sky-600 hover:text-sky-500 transition">メンバー管理 →</a>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    {{-- 基本設定 --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-semibold text-slate-900 mb-4">基本設定</h2>
        <form method="POST" action="{{ route('communities.settings.update', $community) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm text-slate-600 mb-1.5">コミュニティ名</label>
                <input type="text" name="name" value="{{ old('name', $community->name) }}" required maxlength="100"
                       class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1.5">説明</label>
                <textarea name="description" rows="3" maxlength="500"
                          class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">{{ old('description', $community->description) }}</textarea>
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1.5">アイコン</label>
                <div class="flex items-center gap-3 mb-2">
                    @if ($community->icon_url)
                        <img src="{{ $community->icon_url }}" alt="" class="w-12 h-12 rounded-full object-cover border border-slate-200">
                    @else
                        <div class="w-12 h-12 rounded-full bg-slate-100 border border-slate-200"></div>
                    @endif
                    <input type="file" name="icon" accept="image/*"
                           class="flex-1 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-50 file:text-sky-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold hover:file:bg-sky-100">
                </div>
                <input type="url" name="icon_url" value="{{ old('icon_url', $community->icon_url) }}" maxlength="255" placeholder="またはURLを直接指定"
                       class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <p class="text-xs text-slate-400 mt-1">画像をアップロードした場合はそちらが優先されます（2MBまで）。</p>
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1.5">Discordギルド ID</label>
                <input type="text" name="discord_guild_id" value="{{ old('discord_guild_id', $community->discord_guild_id) }}" maxlength="32"
                       class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-slate-600 mb-1.5">公開範囲</label>
                    <select name="visibility" class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                        <option value="private" @selected($community->visibility === 'private')>非公開</option>
                        <option value="public" @selected($community->visibility === 'public')>公開</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1.5">参加ポリシー</label>
                    <select name="join_policy" class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                        <option value="invite_only" @selected($community->join_policy === 'invite_only')>招待制</option>
                        <option value="request" @selected($community->join_policy === 'request')>承認制</option>
                        <option value="open" @selected($community->join_policy === 'open')>即時参加</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1.5">募集メッセージ</label>
                <input type="text" name="recruiting_message" value="{{ old('recruiting_message', $community->recruiting_message) }}" maxlength="255"
                       class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="is_recruiting" value="1" @checked($community->is_recruiting)
                       class="rounded border-slate-300 text-sky-500 focus:ring-sky-400">
                募集中として表示する
            </label>
            <button type="submit" class="w-full rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white transition">保存する</button>
        </form>
    </section>

    <div class="space-y-6">
        {{-- 関連リンク --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-slate-900 mb-4">関連リンク</h2>
            <div class="space-y-2 mb-4">
                @forelse ($links as $link)
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-2 text-sm">
                        <a href="{{ $link->url }}" target="_blank" rel="noopener" class="text-sky-600 hover:underline">{{ $link->label }}</a>
                        <form method="POST" action="{{ route('communities.links.destroy', [$community, $link]) }}">
                            @csrf
                            <button type="submit" class="text-slate-400 hover:text-rose-500 transition">削除</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">まだリンクがありません。</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('communities.links.store', $community) }}" class="flex gap-2">
                @csrf
                <input type="text" name="label" placeholder="表示名" maxlength="50" required
                       class="w-28 rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <input type="url" name="url" placeholder="https://discord.gg/..." required
                       class="flex-1 rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <button type="submit" class="rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white transition">追加</button>
            </form>
        </section>

        {{-- 航空会社 --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-slate-900 mb-4">航空会社</h2>
            <div class="space-y-2 mb-4">
                @forelse ($airlines as $al)
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-2 text-sm">
                        <span class="text-slate-700">{{ $al->name }} @if($al->icao_code) <span class="text-slate-400 font-mono text-xs">{{ $al->icao_code }}</span> @endif</span>
                        <form method="POST" action="{{ route('communities.airlines.destroy', [$community, $al]) }}">
                            @csrf
                            <button type="submit" class="text-slate-400 hover:text-rose-500 transition">削除</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">まだ航空会社がありません。</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('communities.airlines.store', $community) }}" class="flex gap-2">
                @csrf
                <input type="text" name="name" placeholder="会社名（例: 日本エアシステムズ）" maxlength="100" required
                       class="flex-1 rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <input type="text" name="icao_code" placeholder="ICAO" maxlength="3"
                       class="w-20 rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 uppercase placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <button type="submit" class="rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white transition">追加</button>
            </form>
        </section>

        {{-- 機体の新規登録 --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-slate-900 mb-4">機体を追加</h2>
            <form method="POST" action="{{ route('communities.aircraft.store', $community) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="registration" placeholder="登録記号 (JA207A)" maxlength="10" required
                           class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 uppercase placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                    <input type="text" name="icao_type" placeholder="機種 (B738)" maxlength="4" required
                           class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 uppercase placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                </div>
                <select name="airline_id" class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                    <option value="">航空会社なし</option>
                    @foreach ($airlines as $al)
                        <option value="{{ $al->id }}">{{ $al->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="livery_name" placeholder="塗装名（任意）" maxlength="100"
                       class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <div class="grid grid-cols-2 gap-2">
                    <select name="sim_platform" class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                        <option value="ANY">全対応</option>
                        <option value="MSFS2020">MSFS2020</option>
                        <option value="MSFS2024">MSFS2024</option>
                        <option value="XP12">X-Plane12</option>
                    </select>
                    <input type="text" name="home_base_icao" placeholder="拠点空港 (RJTT)" maxlength="4" required
                           class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 uppercase placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                </div>
                <button type="submit" class="w-full rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white transition">追加する</button>
            </form>
        </section>
    </div>
</div>

{{-- 機体の管理 --}}
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="font-semibold text-slate-900 mb-4">機体の管理</h2>
    <div class="space-y-3">
        @forelse ($aircraft as $a)
            @php [$statusLabel, $statusClass] = $statusStyles[$a->status] ?? $statusStyles['maintenance']; @endphp
            <div class="rounded-lg bg-slate-50 px-4 py-3 space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('communities.aircraft.show', [$community, $a]) }}" class="font-mono font-semibold text-slate-900 hover:text-sky-600">{{ $a->registration }}</a>
                    <span class="text-xs text-slate-400">{{ $a->icao_type }}</span>
                    @if ($a->airline)
                        <span class="text-xs text-sky-600">{{ $a->airline->name }}</span>
                    @endif
                    <span class="rounded-full border px-2 py-0.5 text-xs {{ $statusClass }}">{{ $statusLabel }}</span>

                    <form method="POST" action="{{ route('communities.aircraft.update', [$community, $a]) }}" class="flex items-center gap-2 ml-auto">
                        @csrf
                        <select name="airline_id" class="rounded-lg bg-white border border-slate-200 px-2 py-1 text-xs text-slate-800 focus:outline-none focus:border-sky-400">
                            <option value="">会社なし</option>
                            @foreach ($airlines as $al)
                                <option value="{{ $al->id }}" @selected($a->airline_id === $al->id)>{{ $al->name }}</option>
                            @endforeach
                        </select>
                        <select name="status" class="rounded-lg bg-white border border-slate-200 px-2 py-1 text-xs text-slate-800 focus:outline-none focus:border-sky-400">
                            @foreach (['available','reserved','in_flight','maintenance','retired'] as $s)
                                <option value="{{ $s }}" @selected($a->status === $s)>{{ $statusStyles[$s][0] }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="current_location_icao" value="{{ $a->current_location_icao }}" maxlength="4"
                               class="w-20 rounded-lg bg-white border border-slate-200 px-2 py-1 text-xs text-slate-800 uppercase focus:outline-none focus:border-sky-400">
                        <button type="submit" class="rounded-lg bg-slate-100 hover:bg-slate-200 px-3 py-1 text-xs text-slate-700 transition">更新</button>
                    </form>

                    @if ($a->status !== 'retired')
                        <form method="POST" action="{{ route('communities.aircraft.retire', [$community, $a]) }}"
                              onsubmit="return confirm('{{ $a->registration }} を退役にしますか？')">
                            @csrf
                            <button type="submit" class="rounded-lg border border-rose-200 text-rose-500 hover:bg-rose-50 px-3 py-1 text-xs transition">退役</button>
                        </form>
                    @endif
                </div>

                <form method="POST" action="{{ route('communities.aircraft.maintenance-interval', [$community, $a]) }}" class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-200/70">
                    @csrf
                    <label class="flex items-center gap-1.5 text-xs text-slate-500">
                        <input type="checkbox" name="maintenance_interval_enabled" value="1" @checked($a->maintenance_interval_enabled)
                               class="rounded border-slate-300 text-sky-500 focus:ring-sky-400">
                        整備インターバル
                    </label>
                    <input type="number" name="maintenance_interval_minutes" min="1" placeholder="分（例: 600）"
                           value="{{ $a->maintenance_interval_minutes }}"
                           class="w-32 rounded-lg bg-white border border-slate-200 px-2 py-1 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400">
                    @if ($a->maintenance_interval_enabled && $a->maintenance_interval_minutes)
                        <span class="text-xs text-slate-400">累積 {{ $a->accumulated_minutes_since_maintenance }} / {{ $a->maintenance_interval_minutes }} 分</span>
                    @endif
                    <button type="submit" class="rounded-lg bg-slate-100 hover:bg-slate-200 px-3 py-1 text-xs text-slate-700 transition">保存</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-slate-400">まだ機体がありません。</p>
        @endforelse
    </div>
</section>
@endsection
