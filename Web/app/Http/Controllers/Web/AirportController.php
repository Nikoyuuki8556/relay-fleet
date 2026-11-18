<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use Illuminate\Http\Request;

/**
 * 【設計書からの拡張】空港座標マスタ(airports)は特定コミュニティに属さないグローバルなデータ。
 * マップに表示できない（座標未登録の）空港をログイン済みユーザーなら誰でも補完できるようにする。
 * 誤った値が登録された場合も同じ画面から上書きできるので、性善説運用で許容する。
 */
class AirportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'icao' => ['required', 'string', 'size:4'],
            'name' => ['required', 'string', 'max:100'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'redirect_community' => ['required', 'integer', 'exists:communities,id'],
        ]);

        Airport::updateOrCreate(
            ['icao' => strtoupper($data['icao'])],
            ['name' => $data['name'], 'lat' => $data['lat'], 'lon' => $data['lon'], 'source' => 'manual'],
        );

        return redirect()->route('communities.map', $data['redirect_community'])
            ->with('status', strtoupper($data['icao']).' の座標を登録しました');
    }
}
