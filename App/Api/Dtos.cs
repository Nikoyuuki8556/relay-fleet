using System.Text.Json.Serialization;

namespace RelayFleetTracker.Api;

public sealed class MeDto
{
    [JsonPropertyName("id")] public int Id { get; set; }
    [JsonPropertyName("discord_username")] public string? DiscordUsername { get; set; }
    [JsonPropertyName("vatsim_cid")] public int? VatsimCid { get; set; }
}

public sealed class CommunityMembershipDto
{
    [JsonPropertyName("community_id")] public int CommunityId { get; set; }
    [JsonPropertyName("role")] public string Role { get; set; } = "";
    [JsonPropertyName("status")] public string Status { get; set; } = "";
    [JsonPropertyName("community")] public CommunityDto? Community { get; set; }
}

public sealed class CommunityDto
{
    [JsonPropertyName("id")] public int Id { get; set; }
    [JsonPropertyName("name")] public string Name { get; set; } = "";
}

public sealed class AircraftDto
{
    [JsonPropertyName("id")] public int Id { get; set; }
    [JsonPropertyName("registration")] public string Registration { get; set; } = "";
    [JsonPropertyName("icao_type")] public string IcaoType { get; set; } = "";
    [JsonPropertyName("current_location_icao")] public string CurrentLocationIcao { get; set; } = "";
    [JsonPropertyName("status")] public string Status { get; set; } = "";
}

public sealed class ReserveResponse
{
    [JsonPropertyName("reservation_id")] public int ReservationId { get; set; }
    [JsonPropertyName("expires_at")] public DateTimeOffset ExpiresAt { get; set; }
}

public sealed class SubmitPirepRequest
{
    [JsonPropertyName("reservation_id")] public int ReservationId { get; set; }
    [JsonPropertyName("departure_icao")] public string DepartureIcao { get; set; } = "";
    [JsonPropertyName("arrival_icao")] public string ArrivalIcao { get; set; } = "";
    [JsonPropertyName("arrival_spot")] public string? ArrivalSpot { get; set; }
    [JsonPropertyName("departure_time")] public DateTimeOffset DepartureTime { get; set; }
    [JsonPropertyName("arrival_time")] public DateTimeOffset ArrivalTime { get; set; }
    [JsonPropertyName("flight_time_min")] public int FlightTimeMin { get; set; }
    [JsonPropertyName("network")] public string Network { get; set; } = "offline";
    [JsonPropertyName("vatsim_callsign")] public string? VatsimCallsign { get; set; }
}

public sealed class SubmitPirepResponse
{
    [JsonPropertyName("pirep_id")] public int PirepId { get; set; }
    [JsonPropertyName("verification_status")] public string VerificationStatus { get; set; } = "";
}

public sealed class ApiErrorResponse
{
    [JsonPropertyName("error")] public string Error { get; set; } = "";
    [JsonPropertyName("message")] public string Message { get; set; } = "";
}
