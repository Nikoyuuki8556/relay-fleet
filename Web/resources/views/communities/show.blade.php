@extends('layouts.app')
@section('title', $community->name.' — RelayFleet')
@section('content')

@php
    $statusStyles = [
        'available' => ['空きあり', 'bg-emerald-50 text-emerald-600 border-emerald-200'],
        'reserved' => ['予約済み', 'bg-amber-50 text-amber-600 border-amber-200'],
        'in_flight' => ['飛行中', 'bg-sky-50 text-sky-600 border-sky-200'],
        'maintenance' => ['整備中', 'bg-rose-50 text-rose-600 border-rose-200'],
        'retired' => ['退役', 'bg-slate-100 text-slate-500 border-slate-200'],
    ];
    $isManager = $myMembership && in_array($myMembership->role, ['owner', 'admin']);
@endphp

<div class="mb-8 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
    <div>
        <div class="flex items-center gap-3">
            @if ($community->icon_url)
                <img src="{{ $community->icon_url }}" alt="" class="w-10 h-10 rounded-full object-cover border border-slate-200">
            @endif
            <h1 class="text-2xl font-bold text-slate-900">{{ $community->name }}</h1>
            @if ($community->visibility === 'public')
                <span class="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-600 text-xs px-2 py-0.5">公開</span>
            @else
                <span class="rounded-full border border-slate-200 bg-slate-100 text-slate-500 text-xs px-2 py-0.5">非公開</span>
            @endif
        </div>
        <p class="text-slate-500 mt-2 max-w-xl">{{ $community->description ?: '説明はまだありません' }}</p>

        @if ($community->links->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-3 text-sm">
                @foreach ($community->links as $link)
                    <a href="{{ $link->url }}" target="_blank" rel="noopener" class="text-sky-600 hover:underline">{{ $link->label }} ↗</a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="flex flex-col sm:items-end gap-2">
        <div class="flex gap-2">
            <a href="{{ route('communities.ranking', $community) }}"
               class="whitespace-nowrap rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition">今月のランキング</a>
            @if ($isManager)
                <a href="{{ route('communities.manage', $community) }}"
                   class="whitespace-nowrap rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition">管理</a>
                <a href="{{ route('communities.members', $community) }}"
                   class="whitespace-nowrap rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition">メンバー</a>
            @endif
        </div>
        @if ($isManager)
            <form method="POST" action="{{ route('communities.invites.store', $community) }}">
                @csrf
                <button type="submit"
                        class="whitespace-nowrap rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-sky-200 transition">
                    招待コードを発行
                </button>
            </form>
        @endif
    </div>
</div>

@if ($isManager && $pendingMembers && $pendingMembers->isNotEmpty())
    <div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50/60 p-5">
        <h2 class="font-semibold text-amber-700 mb-3">参加リクエスト（承認待ち: {{ $pendingMembers->count() }}件）</h2>
        <div class="space-y-2">
            @foreach ($pendingMembers as $pm)
                <div class="flex items-center justify-between rounded-lg bg-white px-4 py-2.5 shadow-sm">
                    <span class="text-sm text-slate-700">{{ $pm->user->discord_username ?? $pm->user->vatsim_full_name ?? ('User #'.$pm->user_id) }}</span>
                    <form method="POST" action="{{ route('communities.memberships.approve', [$community, $pm->user_id]) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-emerald-500 hover:bg-emerald-400 px-3 py-1.5 text-xs font-semibold text-white transition">承認する</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if ($myActiveReservation)
    <div class="mb-8 rounded-2xl border border-sky-200 bg-sky-50/60 p-6">
        <h2 class="font-semibold text-slate-900 mb-1">現在の予約: {{ $myActiveReservation->aircraft->registration }}</h2>
        <p class="text-sm text-slate-500 mb-4">
            {{ $myActiveReservation->expires_at?->format('Y/m/d H:i') }} まで有効。フライト後はPIREPを提出してください。
        </p>

        <div class="grid sm:grid-cols-2 gap-4">
            <form method="POST" action="{{ route('pireps.store') }}" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
                @csrf
                <input type="hidden" name="reservation_id" value="{{ $myActiveReservation->id }}">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">PIREP提出</p>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="departure_icao" placeholder="出発 (RJTT)" maxlength="4" required
                           value="{{ $myActiveReservation->aircraft->home_base_icao }}"
                           class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 uppercase placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                    <input type="text" name="arrival_icao" placeholder="到着 (RJOO)" maxlength="4" required
                           class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 uppercase placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                </div>
                <input type="text" name="arrival_spot" placeholder="到着スポット番号（任意・例: SPOT 12）" maxlength="20"
                       class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs text-slate-500">出発時刻
                        <div class="mt-1 flex gap-1">
                            <input type="datetime-local" name="departure_time" id="pirep-departure-time" required
                                   class="w-full rounded-lg bg-white border border-slate-200 px-2 py-1.5 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                            <button type="button" onclick="rfSetNow('pirep-departure-time')"
                                    class="shrink-0 rounded-lg border border-sky-200 bg-sky-50 hover:bg-sky-100 px-2 text-xs text-sky-600 transition">現在</button>
                        </div>
                    </label>
                    <label class="text-xs text-slate-500">到着時刻
                        <div class="mt-1 flex gap-1">
                            <input type="datetime-local" name="arrival_time" id="pirep-arrival-time" required
                                   class="w-full rounded-lg bg-white border border-slate-200 px-2 py-1.5 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                            <button type="button" onclick="rfSetNow('pirep-arrival-time')"
                                    class="shrink-0 rounded-lg border border-sky-200 bg-sky-50 hover:bg-sky-100 px-2 text-xs text-sky-600 transition">現在</button>
                        </div>
                    </label>
                </div>
                <input type="number" name="flight_time_min" placeholder="飛行時間（分）" min="1" required
                       class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <select name="network" id="pirep-network" onchange="rfToggleCallsign()"
                        class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                    <option value="offline">オフライン</option>
                    <option value="vatsim">VATSIM</option>
                </select>
                <div id="pirep-callsign-wrap" class="hidden">
                    <input type="text" name="vatsim_callsign" id="pirep-callsign" placeholder="VATSIMコールサイン（例: JAS207）" maxlength="16"
                           class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                </div>
                <button type="submit" class="w-full rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white transition">提出する</button>
            </form>
            <script>
                function rfSetNow(id) {
                    const el = document.getElementById(id);
                    if (!el) return;
                    const d = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
                    el.value = d.toISOString().slice(0, 16);
                }
                function rfToggleCallsign() {
                    const network = document.getElementById('pirep-network');
                    const wrap = document.getElementById('pirep-callsign-wrap');
                    const input = document.getElementById('pirep-callsign');
                    if (!network || !wrap || !input) return;
                    const isVatsim = network.value === 'vatsim';
                    wrap.classList.toggle('hidden', !isVatsim);
                    input.required = isVatsim;
                }
                document.addEventListener('DOMContentLoaded', rfToggleCallsign);
            </script>

            <div class="rounded-xl bg-white p-4 shadow-sm flex flex-col justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">予約のキャンセル</p>
                    <p class="text-sm text-slate-500">フライトしない場合は予約を解放し、他のメンバーが使えるようにしてください。</p>
                </div>
                <form method="POST" action="{{ route('reservations.release', $myActiveReservation) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full rounded-lg border border-slate-200 hover:bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600 transition">予約を解放する</button>
                </form>
            </div>
        </div>
    </div>
@endif

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h2 class="text-lg font-semibold text-slate-900">機体一覧</h2>
    <div class="flex items-center gap-2">
        <a href="{{ route('communities.map', $community) }}" class="text-sm text-sky-600 hover:text-sky-500 transition">🗺 マップで見る</a>
        @php $airports = $aircraft->pluck('current_location_icao')->unique()->sort()->values(); @endphp
        @if ($airports->count() > 1)
            <select id="rf-airport-filter" onchange="rfFilterByAirport(this.value)"
                    class="rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-sm text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <option value="">空港で絞り込み（すべて）</option>
                @foreach ($airports as $icao)
                    <option value="{{ $icao }}">{{ $icao }}</option>
                @endforeach
            </select>
        @endif
    </div>
</div>
<div id="rf-aircraft-grid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse ($aircraft as $a)
        @php [$statusLabel, $statusClass] = $statusStyles[$a->status] ?? $statusStyles['maintenance']; @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-rf-airport="{{ $a->current_location_icao }}">
            <div class="flex items-start justify-between">
                <div>
                    <a href="{{ route('communities.aircraft.show', [$community, $a]) }}" class="font-mono font-semibold text-slate-900 text-lg hover:text-sky-600 transition">{{ $a->registration }}</a>
                    <div class="text-sm text-slate-500">{{ $a->icao_type }} @if($a->livery_name) · {{ $a->livery_name }} @endif</div>
                    @if ($a->airline)
                        <div class="text-xs text-sky-600 mt-0.5">{{ $a->airline->name }}</div>
                    @endif
                </div>
                <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
            <div class="mt-3 text-sm text-slate-500 font-mono">📍 {{ $a->current_location_icao }}@if($a->current_spot) <span class="text-slate-400">/ {{ $a->current_spot }}</span>@endif</div>

            @php
                $myQueueEntry = $myQueueEntries[$a->id] ?? null;
                $queueCount = $queueCounts[$a->id] ?? 0;
            @endphp
            @if ($a->status === 'available' && ! $myActiveReservation)
                <form method="POST" action="{{ route('aircraft.reserve', $a) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white transition">予約する</button>
                </form>
            @elseif ($myActiveReservation && $myActiveReservation->aircraft_id == $a->id)
                <div class="mt-4 text-center rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 text-sm text-sky-600">あなたが予約中</div>
            @elseif ($myQueueEntry)
                <div class="mt-4 space-y-2">
                    <div class="text-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-600">順番待ち中（{{ $queueCount }}人待ち）</div>
                    <form method="POST" action="{{ route('aircraft.queue.leave', $myQueueEntry) }}">
                        @csrf
                        <button type="submit" class="w-full text-xs text-slate-400 hover:text-rose-500 transition">順番待ちをやめる</button>
                    </form>
                </div>
            @elseif (! $myActiveReservation)
                <form method="POST" action="{{ route('aircraft.queue.join', $a) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full rounded-lg border border-sky-200 bg-sky-50 hover:bg-sky-100 px-4 py-2 text-sm font-semibold text-sky-600 transition">
                        順番待ちに登録する@if($queueCount > 0)（現在{{ $queueCount }}人待ち）@endif
                    </button>
                </form>
            @else
                <div class="mt-4 text-center rounded-lg border border-slate-100 px-4 py-2 text-sm text-slate-400">操作不可</div>
            @endif
        </div>
    @empty
        <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 px-6 py-10 text-center text-slate-500">
            まだ機体が登録されていません。
        </div>
    @endforelse
</div>

@push('scripts')
<script>
    function rfFilterByAirport(icao) {
        document.querySelectorAll('#rf-aircraft-grid [data-rf-airport]').forEach((card) => {
            card.style.display = (! icao || card.dataset.rfAirport === icao) ? '' : 'none';
        });
    }
</script>
@endpush
@endsection
