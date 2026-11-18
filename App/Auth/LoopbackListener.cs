using System.Net;

namespace RelayFleetTracker.Auth;

/// <summary>
/// 7.4節 a/d: ローカルポート(例: 51823)でHttpListenerを起動し、ブラウザでの認可後に
/// リダイレクトされてくる code をキャプチャする。RFC 8252準拠（ループバックアドレスのみ、
/// カスタムURLスキームは複数アプリでの衝突リスクがあるため不採用 — 7.4節末尾）。
/// </summary>
public sealed class LoopbackListener : IDisposable
{
    private readonly HttpListener _listener = new();

    public int Port { get; }

    // 重要: HttpListenerのPrefixesは末尾"/"必須で、これはURLの末尾"/"有無まで含めた完全一致でしか
    // リクエストを拾わない。以前はRedirectUriが末尾"/"無し・Prefixesが末尾"/"有りでズレており、
    // ブラウザからのリダイレクトがリスナーに届かず「ログインできない（無反応のまま固まる）」原因になっていた。
    // 必ずこの2つは同じ文字列にすること。
    public string RedirectUri => $"http://127.0.0.1:{Port}/callback/";

    public LoopbackListener(int preferredPort = 51823)
    {
        Port = preferredPort;
        _listener.Prefixes.Add(RedirectUri);
    }

    /// <summary>認可後のリダイレクトを待ち受け、クエリ文字列から code (またはerror) を取り出す。</summary>
    public async Task<(string? Code, string? State, string? Error)> WaitForCallbackAsync(CancellationToken ct)
    {
        _listener.Start();
        try
        {
            using var registration = ct.Register(() => _listener.Stop());

            var context = await _listener.GetContextAsync();
            var query = context.Request.QueryString;
            var code = query["code"];
            var state = query["state"];
            var error = query["error"];

            const string html = "<html><body style='font-family:sans-serif'>" +
                                 "<h2>RelayFleetTrackerの認証が完了しました。このタブは閉じて構いません。</h2>" +
                                 "</body></html>";
            var buffer = System.Text.Encoding.UTF8.GetBytes(html);
            context.Response.ContentType = "text/html; charset=utf-8";
            context.Response.ContentLength64 = buffer.Length;
            await context.Response.OutputStream.WriteAsync(buffer, ct);
            context.Response.Close();

            return (code, state, error);
        }
        finally
        {
            if (_listener.IsListening) _listener.Stop();
        }
    }

    public void Dispose() => ((IDisposable)_listener).Dispose();
}
