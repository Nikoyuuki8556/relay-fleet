@extends('layouts.app')
@section('title', $community->name.' の機体マップ — RelayFleet')

@push('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
    .rf-marker-badge {
        display: flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border-radius: 9999px;
        background: #0ea5e9; color: #fff; font-weight: 700; font-size: 13px;
        border: 2px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.25);
    }
    .rf-popup-row { padding: 2px 0; font-size: 12px; }
</style>
@endpush

@section('content')

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">機体マップ</h1>
        <p class="text-sm text-slate-500 mt-1">{{ $community->name }} の全機体（退役を除く）の現在位置です。</p>
    </div>
    <a href="{{ route('communities.show', $community) }}" class="text-slate-500 hover:text-slate-900 transition text-sm">← {{ $community->name }} に戻る</a>
</div>

<div id="rf-map" class="rounded-2xl border border-slate-200 shadow-sm" style="height: 560px;"></div>

@if (! empty($unmapped))
    <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50/60 p-5">
        <h2 class="font-semibold text-amber-700 mb-1">座標未登録の空港（{{ count($unmapped) }}件）</h2>
        <p class="text-sm text-amber-700/80 mb-4">この空港にいる機体はマップに表示されていません。座標を登録すると次回から表示されます。</p>
        <div class="space-y-3">
            @foreach ($unmapped as $icao => $count)
                <form method="POST" action="{{ route('airports.store') }}" class="flex flex-wrap items-center gap-2 rounded-lg bg-white px-4 py-3 shadow-sm">
                    @csrf
                    <input type="hidden" name="redirect_community" value="{{ $community->id }}">
                    <span class="font-mono font-semibold text-slate-800">{{ $icao }}</span>
                    <span class="text-xs text-slate-400">（{{ $count }}機）</span>
                    <input type="hidden" name="icao" value="{{ $icao }}">
                    <input type="text" name="name" placeholder="空港名" maxlength="100" required
                           class="flex-1 min-w-[140px] rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                    <input type="number" step="0.000001" name="lat" placeholder="緯度（例: 35.5494）" required
                           class="w-40 rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                    <input type="number" step="0.000001" name="lon" placeholder="経度（例: 139.7798）" required
                           class="w-40 rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400">
                    <button type="submit" class="rounded-lg bg-sky-500 hover:bg-sky-400 px-3 py-1.5 text-xs font-semibold text-white transition">登録</button>
                </form>
            @endforeach
        </div>
        <p class="text-xs text-amber-700/60 mt-3">緯度経度が分からない場合は、Google マップで空港を検索し、地図を右クリックして表示される数値（例: 35.549393, 139.779839）をそのまま使えます。</p>
    </div>
@endif

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
    (function () {
        const markers = @json($markers);
        const map = L.map('rf-map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 18,
        }).addTo(map);

        const statusLabels = {
            available: '空きあり', reserved: '予約済み', in_flight: '飛行中',
            maintenance: '整備中', retired: '退役',
        };

        const bounds = [];
        markers.forEach((m) => {
            bounds.push([m.lat, m.lon]);
            const icon = L.divIcon({
                html: '<div class="rf-marker-badge">' + m.aircraft.length + '</div>',
                className: '', iconSize: [32, 32], iconAnchor: [16, 16],
            });
            let popup = '<div class="text-sm"><div class="font-semibold mb-1">' + m.icao + ' ' + m.name + '</div>';
            m.aircraft.forEach((a) => {
                popup += '<div class="rf-popup-row">' + a.registration + ' (' + a.icao_type + ')'
                    + (a.airline ? ' · ' + a.airline : '')
                    + ' — ' + (statusLabels[a.status] || a.status)
                    + (a.spot ? ' · ' + a.spot : '')
                    + '</div>';
            });
            popup += '</div>';
            L.marker([m.lat, m.lon], { icon }).addTo(map).bindPopup(popup);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 9 });
        } else {
            map.setView([36.2048, 138.2529], 5); // 表示できる機体が無い場合は日本全体を表示
        }
    })();
</script>
@endpush
@endsection
