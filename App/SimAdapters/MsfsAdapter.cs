using RelayFleetTracker.Core;

namespace RelayFleetTracker.SimAdapters;

#if RFT_MSFS_SDK
using Microsoft.FlightSimulator.SimConnect;
using System.Runtime.InteropServices;

/// <summary>
/// 7.2節: MSFS2020/2024共通アダプタ。Microsoft.FlightSimulator.SimConnect（公式マネージドラッパー）を
/// MSFS2020 SDKでビルドする。公式見解として2020向けビルドは2024側からも認識され動作する（後方互換）。
/// SIMCONNECT_DATA_FACILITY_AIRPORT構造体（Ident長6→9文字化）は使わない設計にしてあるため、
/// 両対応で問題が出ない（7.2節の注意通り）。
/// </summary>
public sealed class MsfsAdapter : IFlightDataSource
{
    private enum Requests { FlightData }
    private enum Definitions { FlightData }

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi, Pack = 1)]
    private struct FlightDataStruct
    {
        public double AltitudeFeet;
        public double GroundSpeedKnots;
        public double VerticalSpeedFpm;
        public double Latitude;
        public double Longitude;
        public double ParkingBrake;   // 0/1
        public double OnGround;       // 0/1
        public double EngineCombustion1; // 0/1
    }

    private SimConnect? _sc;
    private FlightDataSnapshot? _latest;
    private readonly nint _windowHandle;

    public bool IsConnected { get; private set; }

    public MsfsAdapter(nint windowHandle)
    {
        _windowHandle = windowHandle;
    }

    public Task<bool> ConnectAsync(CancellationToken ct)
    {
        try
        {
            _sc = new SimConnect("RelayFleetTracker", _windowHandle, 0x0402, null, 0);

            _sc.AddToDataDefinition(Definitions.FlightData, "PLANE ALTITUDE", "feet", SIMCONNECT_DATATYPE.FLOAT64, 0, SimConnect.SIMCONNECT_UNUSED);
            _sc.AddToDataDefinition(Definitions.FlightData, "GROUND VELOCITY", "knots", SIMCONNECT_DATATYPE.FLOAT64, 0, SimConnect.SIMCONNECT_UNUSED);
            _sc.AddToDataDefinition(Definitions.FlightData, "VERTICAL SPEED", "feet per minute", SIMCONNECT_DATATYPE.FLOAT64, 0, SimConnect.SIMCONNECT_UNUSED);
            _sc.AddToDataDefinition(Definitions.FlightData, "PLANE LATITUDE", "degrees", SIMCONNECT_DATATYPE.FLOAT64, 0, SimConnect.SIMCONNECT_UNUSED);
            _sc.AddToDataDefinition(Definitions.FlightData, "PLANE LONGITUDE", "degrees", SIMCONNECT_DATATYPE.FLOAT64, 0, SimConnect.SIMCONNECT_UNUSED);
            _sc.AddToDataDefinition(Definitions.FlightData, "BRAKE PARKING POSITION", "position", SIMCONNECT_DATATYPE.FLOAT64, 0, SimConnect.SIMCONNECT_UNUSED);
            _sc.AddToDataDefinition(Definitions.FlightData, "SIM ON GROUND", "bool", SIMCONNECT_DATATYPE.FLOAT64, 0, SimConnect.SIMCONNECT_UNUSED);
            _sc.AddToDataDefinition(Definitions.FlightData, "GENERAL ENG COMBUSTION:1", "bool", SIMCONNECT_DATATYPE.FLOAT64, 0, SimConnect.SIMCONNECT_UNUSED);
            _sc.RegisterDataDefineStruct<FlightDataStruct>(Definitions.FlightData);

            _sc.OnRecvSimobjectDataBytype += OnRecvSimobjectDataBytype;

            _sc.RequestDataOnSimObjectType(Requests.FlightData, Definitions.FlightData, 0, SIMCONNECT_SIMOBJECT_TYPE.USER);

            IsConnected = true;
            return Task.FromResult(true);
        }
        catch
        {
            IsConnected = false;
            return Task.FromResult(false);
        }
    }

    private void OnRecvSimobjectDataBytype(SimConnect sender, SIMCONNECT_RECV_SIMOBJECT_DATA_BYTYPE data)
    {
        if ((Requests)data.dwRequestID != Requests.FlightData) return;
        var d = (FlightDataStruct)data.dwData[0];

        _latest = new FlightDataSnapshot(
            AltitudeFeet: d.AltitudeFeet,
            GroundSpeedKnots: d.GroundSpeedKnots,
            VerticalSpeedFpm: d.VerticalSpeedFpm,
            Latitude: d.Latitude,
            Longitude: d.Longitude,
            ParkingBrakeSet: d.ParkingBrake > 0.5,
            OnGround: d.OnGround > 0.5,
            EngineRunning: d.EngineCombustion1 > 0.5
        );
    }

    /// <summary>SimConnectはメッセージポンプ(WM_USER)経由でイベントを配る。ポーリングタイマーから毎回呼ばれる。</summary>
    public void Pump() => _sc?.ReceiveMessage();

    public FlightDataSnapshot? TryGetLatest() => _latest;

    public ValueTask DisposeAsync()
    {
        IsConnected = false;
        _sc?.Dispose();
        return ValueTask.CompletedTask;
    }
}

#else

/// <summary>
/// MSFS SDKが未設定のためのスタブ実装。csproj冒頭のコメント参照。
/// MSFS_SDK環境変数を設定してビルドし直すと、RFT_MSFS_SDKブロックの実装に切り替わる。
/// </summary>
public sealed class MsfsAdapter : IFlightDataSource
{
    // RFT_MSFS_SDK版とコンストラクタ引数を合わせておく（呼び出し側=MainWindow.xaml.csをif分岐なしにするため）。
    public MsfsAdapter(nint windowHandle) { }

    public bool IsConnected => false;

    public Task<bool> ConnectAsync(CancellationToken ct)
    {
        throw new InvalidOperationException(
            "MSFS SimConnect SDKが設定されていません。MSFS_SDK環境変数を設定し、" +
            "RelayFleetTracker.csprojのSimConnect参照を有効化した状態で再ビルドしてください（7.2節参照）。");
    }

    public FlightDataSnapshot? TryGetLatest() => null;

    public ValueTask DisposeAsync() => ValueTask.CompletedTask;
}

#endif
