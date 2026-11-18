namespace RelayFleetTracker.Core;

/// <summary>
/// 7.1節 IFlightDataSource が返す、シム非依存の1ティック分のデータ。
/// シムごとの差異（SimConnect構造体 / X-Plane RREF）はSimAdapters層で吸収し、
/// Core層はこの型だけを見て判断する（設計原則4: シム非依存のデータモデルを維持する）。
/// </summary>
public readonly record struct FlightDataSnapshot(
    double AltitudeFeet,
    double GroundSpeedKnots,
    double VerticalSpeedFpm,
    double Latitude,
    double Longitude,
    bool ParkingBrakeSet,
    bool OnGround,
    bool EngineRunning
);
