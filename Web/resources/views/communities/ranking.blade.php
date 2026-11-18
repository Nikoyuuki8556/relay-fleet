@extends('layouts.app')
@section('title', $community->name.' の今月のランキング — RelayFleet')
@section('content')

<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">今月のフライトタイムランキング</h1>
        <p class="text-sm text-slate-500 mt-1">{{ $month->format('Y年n月') }}分の集計（{{ $month->startOfMonth()->format('n/j') }}〜{{ $month->copy()->endOfMonth()->format('n/j') }}）</p>
    </div>
    <a href="{{ route('communities.show', $community) }}" class="text-slate-500 hover:text-slate-900 transition text-sm">← {{ $community->name }} に戻る</a>
</div>

<div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
    @if ($ranking->isEmpty())
        <p class="text-sm text-slate-400 px-6 py-10 text-center">今月はまだフライト記録がありません。</p>
    @else
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-medium w-16">順位</th>
                    <th class="px-5 py-3 font-medium">パイロット</th>
                    <th class="px-5 py-3 font-medium">総飛行時間</th>
                    <th class="px-5 py-3 font-medium">フライト回数</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ranking as $i => $row)
                    @php
                        $rank = $i + 1;
                        $rankStyle = match (true) {
                            $rank === 1 => 'bg-amber-50 text-amber-600 border-amber-200',
                            $rank === 2 => 'bg-slate-100 text-slate-600 border-slate-300',
                            $rank === 3 => 'bg-orange-50 text-orange-600 border-orange-200',
                            default => 'bg-white text-slate-400 border-slate-200',
                        };
                        $hours = intdiv((int) $row->total_minutes, 60);
                        $mins = (int) $row->total_minutes % 60;
                    @endphp
                    <tr class="border-t border-slate-100">
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full border text-xs font-semibold {{ $rankStyle }}">{{ $rank }}</span>
                        </td>
                        <td class="px-5 py-3 text-slate-700">{{ $row->user?->displayName() ?? ('User #'.$row->user_id) }}</td>
                        <td class="px-5 py-3 text-slate-700 font-mono">{{ $hours }}時間{{ $mins }}分</td>
                        <td class="px-5 py-3 text-slate-500">{{ $row->flight_count }}回</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
