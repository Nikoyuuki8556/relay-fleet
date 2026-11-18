using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.Http.Json;

namespace RelayFleetTracker.Api;

/// <summary>
/// 5章 Web API仕様のクライアント。ベースパスは /api/v1、Authorization: Bearer &lt;token&gt; 必須（Sanctum）。
/// </summary>
public sealed class ApiClient
{
    private readonly HttpClient _http;

    public ApiClient(HttpClient http, string baseUrl)
    {
        _http = http;
        _http.BaseAddress = new Uri(baseUrl.TrimEnd('/') + "/api/v1/");
    }

    public void SetBearerToken(string token)
    {
        _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);
    }

    public Task<MeDto?> GetMeAsync(CancellationToken ct = default)
        => SendAsync<MeDto>(HttpMethod.Get, "me", null, ct);

    public Task<List<CommunityMembershipDto>?> GetMyCommunitiesAsync(CancellationToken ct = default)
        => SendAsync<List<CommunityMembershipDto>>(HttpMethod.Get, "me/communities", null, ct);

    public Task<List<AircraftDto>?> GetAircraftAsync(int communityId, CancellationToken ct = default)
        => SendAsync<List<AircraftDto>>(HttpMethod.Get, $"communities/{communityId}/aircraft", null, ct);

    public Task<ReserveResponse?> ReserveAircraftAsync(int aircraftId, CancellationToken ct = default)
        => SendAsync<ReserveResponse>(HttpMethod.Post, $"aircraft/{aircraftId}/reserve", null, ct);

    public Task ReleaseReservationAsync(int reservationId, CancellationToken ct = default)
        => SendAsync<object>(HttpMethod.Post, $"reservations/{reservationId}/release", null, ct);

    public Task<SubmitPirepResponse?> SubmitPirepAsync(SubmitPirepRequest request, CancellationToken ct = default)
        => SendAsync<SubmitPirepResponse>(HttpMethod.Post, "pireps", request, ct);

    private async Task<T?> SendAsync<T>(HttpMethod method, string path, object? body, CancellationToken ct)
    {
        using var req = new HttpRequestMessage(method, path);
        if (body != null)
        {
            req.Content = JsonContent.Create(body);
        }

        using var res = await _http.SendAsync(req, ct);

        if (!res.IsSuccessStatusCode)
        {
            ApiErrorResponse? err = null;
            try { err = await res.Content.ReadFromJsonAsync<ApiErrorResponse>(ct); } catch { /* ignore parse failure */ }

            throw new RelayFleetApiException(
                (int)res.StatusCode,
                err?.Error ?? "unknown_error",
                err?.Message ?? $"APIエラー: HTTP {(int)res.StatusCode}");
        }

        if (res.StatusCode == System.Net.HttpStatusCode.NoContent) return default;

        return await res.Content.ReadFromJsonAsync<T>(cancellationToken: ct);
    }
}
