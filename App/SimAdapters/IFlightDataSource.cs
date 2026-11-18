using RelayFleetTracker.Core;

namespace RelayFleetTracker.SimAdapters;

/// <summary>
/// 7.1節: シム非依存のインターフェース。Altitude, GroundSpeed, VerticalSpeed, Lat/Lon,
/// ParkingBrake, OnGround等を返す。MSFS/X-Plane差異はこの実装側に閉じ込める。
/// </summary>
public interface IFlightDataSource : IAsyncDisposable
{
    /// <summary>接続確立。失敗時は例外ではなくfalseを返し、呼び出し側でリトライ判断させる。</summary>
    Task<bool> ConnectAsync(CancellationToken ct);

    /// <summary>最新のスナップショットを取得する。未接続/データ未着なら null。</summary>
    FlightDataSnapshot? TryGetLatest();

    bool IsConnected { get; }

    /// <summary>
    /// メッセージポンプ駆動のSDK（SimConnect等）向けのフック。ポーリングタイマーから毎回呼ぶ。
    /// UDP/モック実装では何もしなくてよいためデフォルト実装（no-op）にしてある。
    /// </summary>
    void Pump() { }
}
