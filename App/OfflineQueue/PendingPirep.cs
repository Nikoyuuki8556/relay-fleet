namespace RelayFleetTracker.OfflineQueue;

public sealed class PendingPirep
{
    public long Id { get; set; }
    public required string PayloadJson { get; set; }
    public required DateTimeOffset CreatedAt { get; set; }
    public int RetryCount { get; set; }
}
