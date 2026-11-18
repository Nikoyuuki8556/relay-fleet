using System.Text.Json;

namespace RelayFleetTracker;

/// <summary>
/// appsettings.json（実行ファイルと同じフォルダに配置）から読み込む設定。
/// Discord/VATSIMのclient_idはネイティブアプリに埋め込む必要があるため機密情報ではないが
/// （PKCEのpublicクライアント想定）、client_secretは絶対にアプリ側に置かないこと
/// （secretはWebサイト側のトークン交換エンドポイントのみが保持する）。
/// </summary>
public static class AppConfig
{
    public static string ApiBaseUrl { get; private set; } = "https://relayfleet.example";
    public static string DiscordClientId { get; private set; } = "";
    public static string VatsimClientId { get; private set; } = "";
    public static string VatsimConnectDomain { get; private set; } = "https://auth.vatsim.net";

    // VATSIM Connect側のアプリ申請が承認されるまでの暫定フラグ。Web側のVATSIM_LOGIN_ENABLEDと同じ考え方。
    // appsettings.jsonはDictionary<string,string>としてパースしているため、値は"true"/"false"の文字列で持つ。
    public static bool VatsimLoginEnabled { get; private set; }

    public static void Load(string path = "appsettings.json")
    {
        if (!File.Exists(path)) return;

        using var stream = File.OpenRead(path);
        var doc = JsonSerializer.Deserialize<Dictionary<string, string>>(stream) ?? new();

        ApiBaseUrl = doc.GetValueOrDefault(nameof(ApiBaseUrl), ApiBaseUrl);
        DiscordClientId = doc.GetValueOrDefault(nameof(DiscordClientId), DiscordClientId);
        VatsimClientId = doc.GetValueOrDefault(nameof(VatsimClientId), VatsimClientId);
        VatsimConnectDomain = doc.GetValueOrDefault(nameof(VatsimConnectDomain), VatsimConnectDomain);
        VatsimLoginEnabled = bool.TryParse(doc.GetValueOrDefault(nameof(VatsimLoginEnabled)), out var enabled) && enabled;
    }
}
