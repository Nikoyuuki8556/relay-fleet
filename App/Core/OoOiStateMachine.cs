namespace RelayFleetTracker.Core;

/// <summary>
/// 7.3節「OOOI状態機械（疑似コード）」の忠実な実装。
/// Out(駐機場出発)-Off(離陸)-On(着陸)-In(駐機場到着)の4イベントを内部で3状態(Idle/AirbornePending/InFlight)
/// として扱い、In確定時にPirepReady イベントで呼び出し側（UI確認ダイアログ）に通知する。
///
/// 呼び出し側は各シムアダプタから来る FlightDataSnapshot を毎ティック Update() に渡すだけでよい。
/// シム固有の差異（SimConnect/X-Plane UDP）はSimAdapters層に閉じ込め、ここには一切持ち込まない
/// （設計原則4: シム非依存のデータモデルを維持する）。
/// </summary>
public sealed class OoOiStateMachine
{
    private const double GroundSpeedStoppedThresholdKt = 5.0;

    private readonly AirportLookup _airports;
    private readonly Func<DateTimeOffset> _clock;

    public OoOiState State { get; private set; } = OoOiState.Idle;

    private DateTimeOffset _departureTimeUtc;
    private string? _departureIcao;
    private bool _wasEngineRunning;
    private bool _wasParkingBrakeSet = true;

    public event Action<PirepDraft>? PirepReady;

    public OoOiStateMachine(AirportLookup airports, Func<DateTimeOffset>? clock = null)
    {
        _airports = airports;
        _clock = clock ?? (() => DateTimeOffset.UtcNow);
    }

    public void Update(FlightDataSnapshot snap)
    {
        switch (State)
        {
            case OoOiState.Idle:
                // ON (engine_started AND parking_brake_released):
                var engineJustStarted = snap.EngineRunning && !_wasEngineRunning;
                var brakeJustReleased = _wasParkingBrakeSet && !snap.ParkingBrakeSet;
                if (snap.EngineRunning && !snap.ParkingBrakeSet && (engineJustStarted || brakeJustReleased))
                {
                    _departureTimeUtc = _clock();
                    _departureIcao = _airports.FindNearest(snap.Latitude, snap.Longitude);
                    State = OoOiState.AirbornePending;
                }
                break;

            case OoOiState.AirbornePending:
                // ON (on_ground == false):
                if (!snap.OnGround)
                {
                    State = OoOiState.InFlight;
                }
                break;

            case OoOiState.InFlight:
                // ON (on_ground == true AND ground_speed < 5):
                if (snap.OnGround && snap.GroundSpeedKnots < GroundSpeedStoppedThresholdKt)
                {
                    var arrivalTimeUtc = _clock();
                    var arrivalIcao = _airports.FindNearest(snap.Latitude, snap.Longitude);

                    var draft = new PirepDraft
                    {
                        DepartureIcao = _departureIcao!,
                        ArrivalIcao = arrivalIcao,
                        DepartureTimeUtc = _departureTimeUtc,
                        ArrivalTimeUtc = arrivalTimeUtc,
                    };

                    // SHOW confirm_dialog(summary) はUI層の責務。ここではイベントを発火するだけ。
                    PirepReady?.Invoke(draft);

                    State = OoOiState.Idle;
                }
                break;
        }

        _wasEngineRunning = snap.EngineRunning;
        _wasParkingBrakeSet = snap.ParkingBrakeSet;
    }

    public void Reset()
    {
        State = OoOiState.Idle;
        _departureIcao = null;
    }
}
