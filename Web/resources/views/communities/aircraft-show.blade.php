@extends('layouts.app')
@section('title', $aircraft->registration.' — '.$community->name.' — RelayFleet')
@section('content')

@php
    $statusStyles = [
        'available' => ['空きあり', 'bg-emerald-50 text-emerald-600 border-emerald-200'],
        'reserved' => ['予約済み', 'bg-amber-50 text-amber-600 border-amber-200'],
        'in_flight' => ['飛行中', 'bg-sky-50 text-sky-600 border-sky-200'],
        'maintenance' => ['整備中', 'bg-rose-50 text-rose-600 border-rose-200'],
        'retired' => ['退役', 'bg-slate-100 text-slate-500 border-slate-200'],
    ];
    [$statusLabel, $statusClass] = $statusStyles[$aircraft->status] ?? $statusStyles['maintenance'];
@endphp

<div class="mb-8 flex items-center justify-between">
    <div>
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-slate-900 font-mono">{{ $aircraft->registration }}</h1>
            <span class="rounded-full border px-2 py-0.5 text-xs {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>
        <p class="text-sm text-slate-500 mt-1">
            {{ $aircraft->icao_type }} @if($aircraft->livery_name) · {{ $aircraft->livery_name }} @endif
            @if($aircraft->airline) · <span class="text-sky-600">{{ $aircraft->airline->name }}</span> @endif
            · 現在地 {{ $aircraft->current_location_icao }}@if($aircraft->current_spot) <span class="text-slate-400">/ {{ $aircraft->current_spot }}</span>@endif
        </p>
        @if ($aircraft->maintenance_interval_enabled && $aircraft->maintenance_interval_minutes)
            @php
                $pct = min(100, (int) round($aircraft->accumulated_minutes_since_maintenance / $aircraft->maintenance_interval_minutes * 100));
                $barColor = $pct >= 90 ? 'bg-rose-400' : ($pct >= 70 ? 'bg-amber-400' : 'bg-sky-400');
            @endphp
            <div class="mt-2 max-w-xs">
                <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                    <span>次の整備まで</span>
                    <span>{{ $aircraft->accumulated_minutes_since_maintenance }} / {{ $aircraft->maintenance_interval_minutes }}分</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full {{ $barColor }}" style="width: {{ $pct }}%"></div>
                </div>
            </div>
        @endif
    </div>
    <a href="{{ route('communities.show', $community) }}" class="text-slate-500 hover:text-slate-900 transition text-sm">← {{ $community->name }} に戻る</a>
</div>

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="font-semibold text-slate-900 mb-4">運用予定</h2>

    <div class="space-y-2 mb-6">
        @forelse ($schedules as $s)
            @php
                $typeStyle = $s->type === 'maintenance'
                    ? 'bg-rose-50 text-rose-600 border-rose-200'
                    : 'bg-sky-50 text-sky-600 border-sky-200';
            @endphp
            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full border px-2 py-0.5 text-xs {{ $typeStyle }}">{{ $s->type === 'maintenance' ? '整備' : 'イベント' }}</span>
                        <span class="font-medium text-slate-700">{{ $s->title }}</span>
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        {{ $s->starts_at?->format('Y/m/d H:i') ?? '未定' }} 〜 {{ $s->ends_at?->format('Y/m/d H:i') ?? '未定' }}
                        @if ($s->note) · {{ $s->note }} @endif
                        · 登録: {{ $s->creator->discord_username ?? $s->creator->vatsim_full_name ?? '不明' }}
                    </div>
                </div>
                <form method="POST" action="{{ route('aircraft.schedules.destroy', [$community, $aircraft, $s]) }}">
                    @csrf
                    <button type="submit" class="text-slate-400 hover:text-rose-500 transition text-sm">削除</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-slate-400">予定はまだ登録されていません。</p>
        @endforelse
    </div>

    <form method="POST" action="{{ route('aircraft.schedules.store', [$community, $aircraft]) }}" class="space-y-3 rounded-xl bg-slate-50 p-4">
        @csrf
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">予定を追加</p>
        <div class="grid sm:grid-cols-2 gap-2">
            <select name="type" class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                <option value="event">イベント予定（誰でも登録可）</option>
                @if ($isManager)
                    <option value="maintenance">整備予定（管理者/dispatcher限定）</option>
                @endif
            </select>
            <input type="text" name="title" placeholder="タイトル（例: 定例グループフライト）" maxlength="100" required
                   class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
        </div>
        <div class="grid sm:grid-cols-2 gap-2">
            <label class="text-xs text-slate-500">開始
                <input type="datetime-local" name="starts_at"
                       class="mt-1 w-full rounded-lg bg-white border border-slate-200 px-2 py-1.5 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
            </label>
            <label class="text-xs text-slate-500">終了
                <input type="datetime-local" name="ends_at"
                       class="mt-1 w-full rounded-lg bg-white border border-slate-200 px-2 py-1.5 text-sm text-slate-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
            </label>
        </div>
        <input type="text" name="note" placeholder="メモ（任意）" maxlength="255"
               class="w-full rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
        <button type="submit" class="rounded-lg bg-sky-500 hover:bg-sky-400 px-4 py-2 text-sm font-semibold text-white transition">登録する</button>
    </form>
</div>

<div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="font-semibold text-slate-900 mb-4">順番待ちリスト</h2>
    @if ($queue->isEmpty())
        <p class="text-sm text-slate-400">現在、順番待ちはいません。</p>
    @else
        <div class="space-y-2">
            @foreach ($queue as $i => $q)
                <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-2.5 text-sm">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white border border-slate-200 text-xs text-slate-500">{{ $i + 1 }}</span>
                        <span class="text-slate-700">{{ $q->user->displayName() }}</span>
                        <span class="text-xs text-slate-400">{{ $q->requested_at?->format('n/j H:i') }}〜待機</span>
                    </div>
                    @if ($myQueueEntry && $myQueueEntry->id === $q->id)
                        <form method="POST" action="{{ route('aircraft.queue.leave', $q) }}">
                            @csrf
                            <button type="submit" class="text-xs text-slate-400 hover:text-rose-500 transition">やめる</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="font-semibold text-slate-900 mb-4">フライト履歴（直近30件）</h2>

    @if ($pireps->isEmpty())
        <p class="text-sm text-slate-400">この機体のフライト記録はまだありません。</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-500 border-b border-slate-100">
                    <tr>
                        <th class="py-2 pr-4 font-medium">区間</th>
                        <th class="py-2 pr-4 font-medium">到着スポット</th>
                        <th class="py-2 pr-4 font-medium">出発</th>
                        <th class="py-2 pr-4 font-medium">到着</th>
                        <th class="py-2 pr-4 font-medium">飛行時間</th>
                        <th class="py-2 pr-4 font-medium">パイロット</th>
                        <th class="py-2 pr-4 font-medium">ネットワーク</th>
                        <th class="py-2 font-medium">判定</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pireps as $p)
                        @php
                            $verifyStyle = match ($p->verification_status) {
                                'verified', 'auto_verified' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                'rejected' => 'bg-rose-50 text-rose-600 border-rose-200',
                                default => 'bg-amber-50 text-amber-600 border-amber-200',
                            };
                            $verifyLabel = match ($p->verification_status) {
                                'verified' => '検証済み',
                                'auto_verified' => '自動検証',
                                'rejected' => '却下',
                                default => '判定待ち',
                            };
                        @endphp
                        <tr class="border-b border-slate-50">
                            <td class="py-2 pr-4 font-mono text-slate-700">{{ $p->departure_icao }} → {{ $p->arrival_icao }}</td>
                            <td class="py-2 pr-4 text-slate-500">{{ $p->arrival_spot ?? '—' }}</td>
                            <td class="py-2 pr-4 text-slate-500">{{ $p->departure_time?->format('Y/m/d H:i') ?? '—' }}</td>
                            <td class="py-2 pr-4 text-slate-500">{{ $p->arrival_time?->format('Y/m/d H:i') ?? '—' }}</td>
                            <td class="py-2 pr-4 text-slate-500">{{ $p->flight_time_min }}分</td>
                            <td class="py-2 pr-4 text-slate-700">
                                {{ $p->user->discord_username ?? $p->user->vatsim_full_name ?? ('User #'.$p->user_id) }}
                                @if ($p->vatsim_callsign) <span class="text-xs text-slate-400 font-mono">({{ $p->vatsim_callsign }})</span> @endif
                            </td>
                            <td class="py-2 pr-4 text-slate-500">{{ $p->network === 'vatsim' ? 'VATSIM' : 'オフライン' }}</td>
                            <td class="py-2"><span class="rounded-full border px-2 py-0.5 text-xs {{ $verifyStyle }}">{{ $verifyLabel }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
