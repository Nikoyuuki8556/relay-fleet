namespace RelayFleetTracker.Api;

/// <summary>5章「エラーレスポンス共通形式」{"error":"コード","message":"..."}をそのまま表現する例外。</summary>
public sealed class RelayFleetApiException(int statusCode, string errorCode, string message)
    : Exception(message)
{
    public int StatusCode { get; } = statusCode;
    public string ErrorCode { get; } = errorCode;
}
