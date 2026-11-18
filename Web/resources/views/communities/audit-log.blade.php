@extends('layouts.app')
@section('title', $community->name.' の監査ログ — RelayFleet')
@section('content')

<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">監査ログ</h1>
        <p class="text-sm text-slate-500 mt-1">設定変更・機体操作・ロール変更などの管理者操作の記録です。</p>
    </div>
    <a href="{{ route('communities.manage', $community) }}" class="text-slate-500 hover:text-slate-900 transition text-sm">← 管理画面に戻る</a>
</div>

<div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
    @if ($logs->isEmpty())
        <p class="text-sm text-slate-400 px-6 py-10 text-center">まだ記録がありません。</p>
    @else
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-medium">日時</th>
                    <th class="px-5 py-3 font-medium">操作者</th>
                    <th class="px-5 py-3 font-medium">内容</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-t border-slate-100">
                        <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $log->created_at?->format('Y/m/d H:i') }}</td>
                        <td class="px-5 py-3 text-slate-700 whitespace-nowrap">
                            {{ $log->actor?->displayName() ?? 'システム' }}
                        </td>
                        <td class="px-5 py-3 text-slate-700">{{ $log->summary }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-6">{{ $logs->links() }}</div>
@endsection
