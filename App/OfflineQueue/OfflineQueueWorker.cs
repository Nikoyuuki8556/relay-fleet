using System.Text.Json;
using RelayFleetTracker.Api;

namespace RelayFleetTracker.OfflineQueue;

/// <summary>
/// 7.5節: 「バックグラウンドタイマー（例: 5分毎、指数バックオフ）で再送を試みる」の実装。
/// </summary>
public sealed class OfflineQueueWorker(OfflineQueueRepository repo, ApiClient api)
{
    private static readonly TimeSpan BaseInterval = TimeSpan.FromMinutes(5);
    private const int MaxRetryBeforeGivingUpLog = 10; // これを超えても消さずログ警告のみ（データを失わない）

    public event Action<string>? LogMessage;

    public async Task RunForeverAsync(CancellationToken ct)
    {
        while (!ct.IsCancellationRequested)
        {
            await FlushOnceAsync(ct);

            try { await Task.Delay(BaseInterval, ct); }
            catch (OperationCanceledException) { return; }
        }
    }

    public async Task FlushOnceAsync(CancellationToken ct)
    {
        foreach (var pending in repo.GetAll())
        {
            // 指数バックオフ: retry_countが増えるほど間隔を空ける（ここでは対象抽出時にスキップする形で表現）。
            var backoff = TimeSpan.FromSeconds(Math.Min(3600, 30 * Math.Pow(2, pending.RetryCount)));
            if (DateTimeOffset.UtcNow - pending.CreatedAt < backoff && pending.RetryCount > 0)
            {
                continue;
            }

            try
            {
                var request = JsonSerializer.Deserialize<SubmitPirepRequest>(pending.PayloadJson)
                    ?? throw new InvalidOperationException("キュー内payloadの復元に失敗しました");

                await api.SubmitPirepAsync(request, ct);
                repo.Remove(pending.Id);
                LogMessage?.Invoke($"再送成功: pending_pireps.id={pending.Id}");
            }
            catch (Exception ex)
            {
                repo.IncrementRetry(pending.Id);
                LogMessage?.Invoke($"再送失敗（retry_count={pending.RetryCount + 1}）: {ex.Message}");

                if (pending.RetryCount + 1 >= MaxRetryBeforeGivingUpLog)
                {
                    LogMessage?.Invoke($"警告: pending_pireps.id={pending.Id} は{MaxRetryBeforeGivingUpLog}回再送に失敗しています。手動確認を推奨します。");
                }
            }
        }
    }
}
