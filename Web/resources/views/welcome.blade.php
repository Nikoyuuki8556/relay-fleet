@extends('layouts.app')
@section('title', 'RelayFleet — コミュニティ機体シェアシステム')
@section('content')
<div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-b from-sky-50 to-white px-8 py-24 text-center shadow-sm">
    <div class="pointer-events-none absolute -top-24 left-1/2 h-72 w-[36rem] -translate-x-1/2 rounded-full bg-sky-200/50 blur-3xl"></div>

    <span class="relative inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-4 py-1 text-xs font-medium text-sky-700">
        MSFS2020 / MSFS2024 / X-Plane12 対応
    </span>

    <h1 class="relative mt-6 text-5xl sm:text-6xl font-extrabold tracking-tight text-slate-900">
        RelayFleet
    </h1>
    <p class="relative mt-5 max-w-xl mx-auto text-slate-500 leading-relaxed">
        「機体は今どこの空港にあるか」をコミュニティ全員で共有する、フライトシム向け機体シェアシステム。
        予約の排他制御とVATSIMによる実飛行の裏取りで、安心して機体を回せます。
    </p>

    <div class="relative mt-10 flex flex-col sm:flex-row justify-center gap-4">
        <a href="{{ route('auth.discord.redirect') }}"
           class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-500 hover:bg-sky-400 px-6 py-3 font-semibold text-white shadow-lg shadow-sky-200 transition">
            Discordでログイン
        </a>
        @if (config('relayfleet.vatsim_login_enabled'))
            <a href="{{ route('auth.vatsim.redirect') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 px-6 py-3 font-semibold text-slate-700 shadow-sm transition">
                VATSIMでログイン
            </a>
        @else
            <span title="VATSIM Connectのアプリ申請が承認され次第利用できます"
                  class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-50 border border-slate-200 px-6 py-3 font-semibold text-slate-400 cursor-not-allowed">
                VATSIMでログイン（申請中）
            </span>
        @endif
    </div>

    <div class="relative mt-16 grid grid-cols-1 sm:grid-cols-3 gap-4 text-left">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sky-500 text-xl mb-2">◎</div>
            <div class="font-semibold text-slate-900 mb-1">排他制御</div>
            <div class="text-sm text-slate-500">同じ機体を2人が同時に使うことを防止</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sky-500 text-xl mb-2">◎</div>
            <div class="font-semibold text-slate-900 mb-1">VATSIM自動検証</div>
            <div class="text-sm text-slate-500">自己申告PIREPを実飛行データで裏取り</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sky-500 text-xl mb-2">◎</div>
            <div class="font-semibold text-slate-900 mb-1">コミュニティ単位</div>
            <div class="text-sm text-slate-500">1人が複数コミュニティに、それぞれ別の役割で所属</div>
        </div>
    </div>
</div>
@endsection
