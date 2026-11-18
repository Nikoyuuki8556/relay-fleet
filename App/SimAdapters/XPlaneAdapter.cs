using System.Net;
using System.Net.Sockets;
using System.Text;
using RelayFleetTracker.Core;

namespace RelayFleetTracker.SimAdapters;

/// <summary>
/// 7.2節: X-Plane12用アダプタ。公式SDK・プラグイン不要、自前UDPソケット実装（RREF購読）。
/// 「RREF」コマンドで欲しいdatarefをindex付きで購読すると、X-Planeが
/// "RREF," ヘッダ + (4byte int index, 4byte float value) の繰り返しでUDP送信してくる。
///
/// 確度: 中〜高。RREFプロトコルの構造体レイアウトはX-Plane SDKのData.txt/公式フォーラムで
/// 長年安定しているが、X-Plane12でのポート番号・パケット長は環境設定(Settings > Network)に依存するため、
/// 実機での疎通確認を必ず行うこと。既定UDPポートは49000（X-Plane側の設定次第で変わる）。
/// </summary>
public sealed class XPlaneAdapter : IFlightDataSource
{
    private enum DrIndex
    {
        Latitude = 1,
        Longitude = 2,
        ElevationMeters = 3,
        GroundSpeedMs = 4,
        VerticalSpeedFpm = 5,
        OnGround = 6,
        ParkingBrake = 7,
        EngineRunning = 8,
    }

    private static readonly (DrIndex Index, string Dataref, int Freq)[] Subscriptions =
    {
        (DrIndex.Latitude, "sim/flightmodel/position/latitude", 5),
        (DrIndex.Longitude, "sim/flightmodel/position/longitude", 5),
        (DrIndex.ElevationMeters, "sim/flightmodel/position/elevation", 2),
        (DrIndex.GroundSpeedMs, "sim/flightmodel/position/groundspeed", 5),
        (DrIndex.VerticalSpeedFpm, "sim/flightmodel/position/vh_ind_fpm", 2),
        (DrIndex.OnGround, "sim/flightmodel/failures/onground_any", 2),
        (DrIndex.ParkingBrake, "sim/flightmodel/controls/parkbrake", 2),
        (DrIndex.EngineRunning, "sim/flightmodel/engine/ENGN_running[0]", 2),
    };

    private readonly IPEndPoint _xplaneEndpoint;
    private readonly int _localReceivePort;
    private UdpClient? _udp;
    private CancellationTokenSource? _cts;
    private Task? _receiveLoop;

    private readonly Dictionary<DrIndex, float> _latest = new();
    private readonly object _lock = new();

    public bool IsConnected { get; private set; }

    public XPlaneAdapter(string xplaneHost = "127.0.0.1", int xplaneUdpPort = 49000, int localReceivePort = 49001)
    {
        _xplaneEndpoint = new IPEndPoint(IPAddress.Parse(xplaneHost), xplaneUdpPort);
        _localReceivePort = localReceivePort;
    }

    public Task<bool> ConnectAsync(CancellationToken ct)
    {
        try
        {
            _udp = new UdpClient(_localReceivePort);
            _udp.Client.ReceiveTimeout = 5000;

            foreach (var (index, dataref, freq) in Subscriptions)
            {
                SendRrefRequest((int)index, dataref, freq);
            }

            _cts = CancellationTokenSource.CreateLinkedTokenSource(ct);
            _receiveLoop = Task.Run(() => ReceiveLoopAsync(_cts.Token), _cts.Token);

            IsConnected = true;
            return Task.FromResult(true);
        }
        catch
        {
            IsConnected = false;
            return Task.FromResult(false);
        }
    }

    private void SendRrefRequest(int index, string dataref, int freqHz)
    {
        // struct RREF_in { char header[5]="RREF\0"; int freq; int index; char dataref[400]; }
        using var ms = new MemoryStream();
        using var bw = new BinaryWriter(ms);

        bw.Write(Encoding.ASCII.GetBytes("RREF\0"));
        bw.Write(freqHz);
        bw.Write(index);

        var drBytes = Encoding.ASCII.GetBytes(dataref);
        var drField = new byte[400];
        Array.Copy(drBytes, drField, Math.Min(drBytes.Length, 399));
        bw.Write(drField);

        _udp!.Send(ms.ToArray(), (int)ms.Length, _xplaneEndpoint);
    }

    private async Task ReceiveLoopAsync(CancellationToken ct)
    {
        while (!ct.IsCancellationRequested)
        {
            UdpReceiveResult result;
            try
            {
                result = await _udp!.ReceiveAsync(ct);
            }
            catch (OperationCanceledException)
            {
                return;
            }
            catch (SocketException)
            {
                continue;
            }

            ParsePacket(result.Buffer);
        }
    }

    private void ParsePacket(byte[] buffer)
    {
        // ヘッダ "RREF," (5バイト) の後ろに (4byte index, 4byte float) が繰り返す。
        if (buffer.Length < 5) return;
        var header = Encoding.ASCII.GetString(buffer, 0, 4);
        if (header != "RREF") return;

        var offset = 5;
        lock (_lock)
        {
            while (offset + 8 <= buffer.Length)
            {
                var index = BitConverter.ToInt32(buffer, offset);
                var value = BitConverter.ToSingle(buffer, offset + 4);
                _latest[(DrIndex)index] = value;
                offset += 8;
            }
        }
    }

    public FlightDataSnapshot? TryGetLatest()
    {
        lock (_lock)
        {
            if (!_latest.ContainsKey(DrIndex.Latitude) || !_latest.ContainsKey(DrIndex.Longitude))
            {
                return null; // まだ最初のRREFパケットが届いていない
            }

            var elevationMeters = Get(DrIndex.ElevationMeters);
            var groundSpeedMs = Get(DrIndex.GroundSpeedMs);

            return new FlightDataSnapshot(
                AltitudeFeet: elevationMeters * 3.28084,
                GroundSpeedKnots: groundSpeedMs * 1.94384,
                VerticalSpeedFpm: Get(DrIndex.VerticalSpeedFpm),
                Latitude: Get(DrIndex.Latitude),
                Longitude: Get(DrIndex.Longitude),
                ParkingBrakeSet: Get(DrIndex.ParkingBrake) > 0.5f,
                OnGround: Get(DrIndex.OnGround) > 0.5f,
                EngineRunning: Get(DrIndex.EngineRunning) > 0.5f
            );
        }
    }

    private float Get(DrIndex index) => _latest.TryGetValue(index, out var v) ? v : 0f;

    public async ValueTask DisposeAsync()
    {
        IsConnected = false;
        if (_cts != null)
        {
            await _cts.CancelAsync();
        }
        if (_receiveLoop != null)
        {
            try { await _receiveLoop; } catch { /* cancellation */ }
        }
        _udp?.Dispose();
    }
}
