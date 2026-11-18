using RelayFleetTracker.Core;

namespace RelayFleetTracker.SimAdapters;

/// <summary>
/// シムに接続せずOOOI状態機械やUIの動作確認を行うための開発用アダプタ。
/// UIの「テストフライト」ボタン等から手動でスナップショットを注入できる。
/// </summary>
public sealed class MockFlightDataSource : IFlightDataSource
{
    private FlightDataSnapshot? _current;

    public bool IsConnected { get; private set; }

    public Task<bool> ConnectAsync(CancellationToken ct)
    {
        IsConnected = true;
        return Task.FromResult(true);
    }

    public void Push(FlightDataSnapshot snapshot) => _current = snapshot;

    public FlightDataSnapshot? TryGetLatest() => _current;

    public ValueTask DisposeAsync()
    {
        IsConnected = false;
        return ValueTask.CompletedTask;
    }
}
