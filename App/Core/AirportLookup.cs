using System.Globalization;
using System.Reflection;

namespace RelayFleetTracker.Core;

/// <summary>
/// 7.3節 nearest_airport(current_lat, current_lon) の実装。
/// 空港マスタはOurAirports (https://ourairports.com/data/airports.csv) の
/// icao/lat/lon 列だけを抜き出したCSVを埋め込みリソースとして持つ想定。
///
/// 確度に関する注意: 同梱の Resources/airports_seed.csv は動作確認用の主要空港のみを
/// 収録したサンプルであり、本番運用前に OurAirports の全件データに差し替えること。
/// </summary>
public sealed class AirportLookup
{
    private readonly List<Airport> _airports;

    public AirportLookup(IEnumerable<Airport> airports)
    {
        _airports = airports.ToList();
        if (_airports.Count == 0)
        {
            throw new InvalidOperationException("空港マスタが空です。Resources/airports_seed.csvを確認してください。");
        }
    }

    public static AirportLookup LoadFromEmbeddedSeed()
    {
        var asm = Assembly.GetExecutingAssembly();
        var resourceName = asm.GetManifestResourceNames()
            .FirstOrDefault(n => n.EndsWith("airports_seed.csv", StringComparison.OrdinalIgnoreCase))
            ?? throw new InvalidOperationException("airports_seed.csv が埋め込みリソースに見つかりません");

        using var stream = asm.GetManifestResourceStream(resourceName)!;
        using var reader = new StreamReader(stream);

        var list = new List<Airport>();
        string? line;
        var isHeader = true;
        while ((line = reader.ReadLine()) != null)
        {
            if (isHeader) { isHeader = false; continue; }
            if (string.IsNullOrWhiteSpace(line)) continue;

            var parts = line.Split(',');
            if (parts.Length < 3) continue;

            var icao = parts[0].Trim();
            if (!double.TryParse(parts[1], NumberStyles.Float, CultureInfo.InvariantCulture, out var lat)) continue;
            if (!double.TryParse(parts[2], NumberStyles.Float, CultureInfo.InvariantCulture, out var lon)) continue;

            list.Add(new Airport(icao, lat, lon));
        }

        return new AirportLookup(list);
    }

    /// <summary>Haversine距離で最も近い空港のICAOコードを返す。</summary>
    public string FindNearest(double lat, double lon)
    {
        var best = _airports[0];
        var bestDist = Haversine(lat, lon, best.Latitude, best.Longitude);

        foreach (var airport in _airports.Skip(1))
        {
            var dist = Haversine(lat, lon, airport.Latitude, airport.Longitude);
            if (dist < bestDist)
            {
                bestDist = dist;
                best = airport;
            }
        }

        return best.Icao;
    }

    private static double Haversine(double lat1, double lon1, double lat2, double lon2)
    {
        const double earthRadiusNm = 3440.065;
        double ToRad(double deg) => deg * Math.PI / 180.0;

        var dLat = ToRad(lat2 - lat1);
        var dLon = ToRad(lon2 - lon1);
        var a = Math.Sin(dLat / 2) * Math.Sin(dLat / 2)
                + Math.Cos(ToRad(lat1)) * Math.Cos(ToRad(lat2)) * Math.Sin(dLon / 2) * Math.Sin(dLon / 2);
        var c = 2 * Math.Atan2(Math.Sqrt(a), Math.Sqrt(1 - a));

        return earthRadiusNm * c;
    }
}
