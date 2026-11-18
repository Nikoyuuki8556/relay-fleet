using System.Diagnostics;
using System.Net.Http;
using System.Net.Http.Json;
using System.Text.Json.Serialization;

namespace RelayFleetTracker.Auth;

public enum OAuthProvider { Discord, Vatsim }

/// <summary>
/// 7.4節「認証（ネイティブOAuth、RFC 8252準拠）」の1〜3をまとめて実行するオーケストレータ。
///   1a. ループバックHttpListener起動
///   1b. code_verifier / code_challenge(S256) 生成
///   1c. システム既定ブラウザで認可URLを開く
///   1d. ブラウザでの認可後、ローカルリスナーがcodeを受信
///   1e. code + code_verifier をWebサイト側のトークン交換エンドポイントに渡し、Sanctumのトークンを取得
///   1f. トークンをCredentialStoreへ暗号化保存
/// </summary>
public sealed class NativeOAuthClient(HttpClient http, string webBaseUrl)
{
    private sealed class TokenExchangeResponse
    {
        [JsonPropertyName("token")] public string Token { get; set; } = "";
    }

    public async Task<string> LoginAsync(OAuthProvider provider, CancellationToken ct)
    {
        using var listener = new LoopbackListener();
        var (verifier, challenge) = PkceHelper.Generate();
        var state = Guid.NewGuid().ToString("N");

        var authorizeUrl = BuildProviderAuthorizeUrl(provider, listener.RedirectUri, state, challenge);

        Process.Start(new ProcessStartInfo(authorizeUrl) { UseShellExecute = true });

        var (code, returnedState, error) = await listener.WaitForCallbackAsync(ct);

        if (error != null)
        {
            throw new InvalidOperationException($"認可がキャンセル/拒否されました: {error}");
        }
        if (code == null)
        {
            // ここに来るのは「リスナーには何かリクエストが届いたが、codeクエリが無かった」場合。
            // 典型例: ブラウザがcallback URLへのfaviconリクエスト等を先に拾ってしまった、
            // あるいはDiscord Developer Portal側にこのredirect_uriが登録されておらず
            // Discordが認可コードを付けずに別ページへ飛ばした、など。
            throw new InvalidOperationException(
                $"認可コードを受信できませんでした（受信したstate: {returnedState ?? "(なし)"}）。" +
                "Discord Developer PortalにこのアプリのRedirect URI " +
                $"({listener.RedirectUri}) が登録されているか確認してください。");
        }
        if (returnedState != state)
        {
            // 送信したstateと受信したstateが食い違うケース。
            // 多くは「ログインボタンを連打して複数回同時に認可フローが走った」
            // 「前回失敗したブラウザタブが残っていて、そちらで認可を完了してしまった」のいずれか。
            throw new InvalidOperationException(
                $"state検証に失敗しました（送信: {state} / 受信: {returnedState}）。" +
                "ブラウザに古い認証タブが残っていないか確認し、最新のタブだけで認可を完了してください。");
        }

        var tokenPath = provider == OAuthProvider.Discord ? "auth/discord/token" : "auth/vatsim/token";
        var res = await http.PostAsJsonAsync(
            $"{webBaseUrl.TrimEnd('/')}/api/v1/{tokenPath}",
            new { code, code_verifier = verifier, redirect_uri = listener.RedirectUri },
            ct);

        res.EnsureSuccessStatusCode();
        var payload = await res.Content.ReadFromJsonAsync<TokenExchangeResponse>(cancellationToken: ct)
            ?? throw new InvalidOperationException("トークン交換レスポンスの解析に失敗しました");

        return payload.Token;
    }

    private static string BuildProviderAuthorizeUrl(OAuthProvider provider, string redirectUri, string state, string challenge)
    {
        // 実際のclient_idはWebサイト側の設定と一致させる必要があるため、
        // 本番実装ではWebサイト側に「認可URL組み立てエンドポイント」を1つ用意し、
        // ネイティブアプリはclient_idをハードコードしない設計を推奨する（このメソッドはその暫定版）。
        return provider switch
        {
            OAuthProvider.Discord =>
                "https://discord.com/oauth2/authorize" +
                $"?client_id={Uri.EscapeDataString(AppConfig.DiscordClientId)}" +
                $"&redirect_uri={Uri.EscapeDataString(redirectUri)}" +
                "&response_type=code&scope=identify%20email" +
                $"&state={state}&code_challenge={challenge}&code_challenge_method=S256",

            OAuthProvider.Vatsim =>
                // 確度: 低〜中。PKCE対応可否は実装開始時にvatsim.dev/services/connect/で必ず確認すること（設計書10章）。
                $"{AppConfig.VatsimConnectDomain}/oauth/authorize" +
                $"?client_id={Uri.EscapeDataString(AppConfig.VatsimClientId)}" +
                $"&redirect_uri={Uri.EscapeDataString(redirectUri)}" +
                "&response_type=code&scope=full_name%20email%20country%20vatsim_details" +
                $"&state={state}&code_challenge={challenge}&code_challenge_method=S256",

            _ => throw new ArgumentOutOfRangeException(nameof(provider)),
        };
    }
}
