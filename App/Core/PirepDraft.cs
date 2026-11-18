namespace RelayFleetTracker.Core;

/// <summary>OOOI状態機械が確定させたPIREP候補。UI確認ダイアログを経てAPIへPOSTされる。</summary>
public sealed class PirepDraft
{
    public required string DepartureIcao { get; init; }
    public required string ArrivalIcao { get; init; }
    public required DateTimeOffset DepartureTimeUtc { get; init; }
    public required DateTimeOffset ArrivalTimeUtc { get; init; }
    public int FlightTimeMin => (int)(ArrivalTimeUtc - DepartureTimeUtc).TotalMinutes;
    public string Network { get; set; } = "offline";
    public string? VatsimCallsign { get; set; }
    public string? ArrivalSpot { get; set; }
}
