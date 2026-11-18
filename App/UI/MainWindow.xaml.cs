using System.Collections.ObjectModel;
using System.Net.Http;
using System.Text.Json;
using System.Windows;
using System.Windows.Threading;
using RelayFleetTracker.Api;
using RelayFleetTracker.Auth;
using RelayFleetTracker.Core;
using RelayFleetTracker.OfflineQueue;
using RelayFleetTracker.SimAdapters;

namespace RelayFleetTracker.UI;

public partial class MainWindow : Window
{
    private readonly HttpClient _http = new();
    private readonly ApiClient _api;
    private readonly NativeOAuthClient _oauth;
    private readonly OfflineQueueRepository _offlineQueue;
    private readonly OfflineQueueWorker _offlineWorker;
    private readonly AirportLookup _airports = AirportLookup.LoadFromEmbeddedSeed();
    private readonly DispatcherTimer _pollTimer = new() { Interval = TimeSpan.FromSeconds(1) };
    private TrayIconService? _tray;

    // 8.節: ログはメイン画面には出さず、開発者ログウィンドウでのみ表示する。
    // ObservableCollectionにしておくことで、DeveloperLogWindowを開いている間も
    // 追加分が自動でUIへ反映される（バインディングの仕組み上、コレクション変更通知が飛ぶため）。
    private readonly ObservableCollection<string> _logEntries = new();
    private DeveloperLogWindow? _devLogWindow;

    private OoOiStateMachine? _ooOi;
    private IFlightDataSource? _sim;
    private int? _activeReservationId;
    private int? _activeAircraftId;

    public MainWindow()
    {
        InitializeComponent();

        // 6.節: VATSIM Connectのアプリ申請が承認されるまでの暫定措置。Web側の運用と合わせる。
        LoginVatsimButton.Visibility = AppConfig.VatsimLoginEnabled ? Visibility.Visible : Visibility.Collapsed;

        _api = new ApiClient(_http, AppConfig.ApiBaseUrl);
        _oauth = new NativeOAuthClient(_http, AppConfig.ApiBaseUrl);

        var dbPath = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "RelayFleetTracker", "offline_queue.sqlite3");
        Directory.CreateDirectory(Path.GetDirectoryName(dbPath)!);
        _offlineQueue = new OfflineQueueRepository(dbPath);
        _offlineWorker = new OfflineQueueWorker(_offlineQueue, _api);
        _offlineWorker.LogMessage += Log;

        Loaded += MainWindow_OnLoaded;
        _pollTimer.Tick += PollTimer_OnTick;
    }

    private async void MainWindow_OnLoaded(object? sender, RoutedEventArgs e)
    {
        _tray = new TrayIconService(this);

        _ = Task.Run(() => _offlineWorker.RunForeverAsync(CancellationToken.None));

        var savedToken = CredentialStore.TryLoadToken();
        if (savedToken != null)
        {
            _api.SetBearerToken(savedToken);
            await LoadMeAndCommunitiesAsync();
        }
    }

    private async void LoginDiscordButton_OnClick(object sender, RoutedEventArgs e) => await LoginAsync(OAuthProvider.Discord);
    private async void LoginVatsimButton_OnClick(object sender, RoutedEventArgs e) => await LoginAsync(OAuthProvider.Vatsim);

    private void DeveloperLogButton_OnClick(object sender, RoutedEventArgs e)
    {
        if (_devLogWindow == null || !_devLogWindow.IsLoaded)
        {
            _devLogWindow = new DeveloperLogWindow(_logEntries) { Owner = this };
            _devLogWindow.Closed += (_, _) => _devLogWindow = null;
            _devLogWindow.Show();
        }
        else
        {
            _devLogWindow.Activate();
        }
    }

    private bool _loginInProgress;

    private async Task LoginAsync(OAuthProvider provider)
    {
        // ループバックリスナーは固定ポート(51823)を1つしか待ち受けられないため、
        // 連打などで認可フローが二重に走るとポート衝突やstate不一致の原因になる。
        // ここで多重起動を防ぐ。
        if (_loginInProgress)
        {
            Log("ログイン処理が既に進行中です。ブラウザでの認可を完了するか、少し待ってから再度お試しください。");
            return;
        }

        _loginInProgress = true;
        LoginDiscordButton.IsEnabled = false;
        LoginVatsimButton.IsEnabled = false;
        try
        {
            Log($"{provider} でログイン中... ブラウザが開きます");
            var token = await _oauth.LoginAsync(provider, CancellationToken.None);
            CredentialStore.SaveToken(token);
            _api.SetBearerToken(token);
            await LoadMeAndCommunitiesAsync();
        }
        catch (Exception ex)
        {
            Log($"ログイン失敗: {ex.Message}");
        }
        finally
        {
            _loginInProgress = false;
            LoginDiscordButton.IsEnabled = true;
            LoginVatsimButton.IsEnabled = AppConfig.VatsimLoginEnabled;
        }
    }

    private async Task LoadMeAndCommunitiesAsync()
    {
        try
        {
            var me = await _api.GetMeAsync();
            LoginStatusText.Text = $"ログイン中: {me?.DiscordUsername ?? me?.VatsimCid?.ToString() ?? "unknown"}";

            var memberships = await _api.GetMyCommunitiesAsync() ?? new List<CommunityMembershipDto>();
            CommunityComboBox.ItemsSource = memberships.Where(m => m.Status == "active").ToList();
            if (CommunityComboBox.Items.Count > 0) CommunityComboBox.SelectedIndex = 0;
        }
        catch (RelayFleetApiException ex)
        {
            Log($"プロフィール取得失敗: {ex.Message}");
        }
    }

    private async void CommunityComboBox_OnSelectionChanged(object sender, System.Windows.Controls.SelectionChangedEventArgs e)
    {
        if (CommunityComboBox.SelectedItem is not CommunityMembershipDto membership) return;

        try
        {
            var aircraft = await _api.GetAircraftAsync(membership.CommunityId) ?? new List<AircraftDto>();
            AircraftComboBox.ItemsSource = aircraft;
            if (aircraft.Count > 0) AircraftComboBox.SelectedIndex = 0;
        }
        catch (RelayFleetApiException ex)
        {
            Log($"機体一覧取得失敗: {ex.Message}");
        }
    }

    private async void ReserveButton_OnClick(object sender, RoutedEventArgs e)
    {
        if (AircraftComboBox.SelectedItem is not AircraftDto aircraft) return;

        try
        {
            var reservation = await _api.ReserveAircraftAsync(aircraft.Id);
            _activeReservationId = reservation?.ReservationId;
            _activeAircraftId = aircraft.Id;
            ReservationStatusText.Text = $"予約中: {aircraft.Registration}（{reservation?.ExpiresAt:HH:mm}まで）";
            Log($"{aircraft.Registration} を予約しました");
        }
        catch (RelayFleetApiException ex)
        {
            // 3.1節: 409は「この機体は現在使用中です」。UIにそのまま出す。
            Log($"予約失敗: {ex.Message}");
        }
    }

    private void ConnectSimButton_OnClick(object sender, RoutedEventArgs e)
    {
        if (_sim != null) { _ = _sim.DisposeAsync(); }

        _sim = SimComboBox.SelectedIndex switch
        {
            0 => new MsfsAdapter(new System.Windows.Interop.WindowInteropHelper(this).EnsureHandle()),
            1 => new XPlaneAdapter(),
            _ => new MockFlightDataSource(),
        };

        _ooOi = new OoOiStateMachine(_airports);
        _ooOi.PirepReady += OnPirepReady;

        _ = ConnectSimAsync();
    }

    private async Task ConnectSimAsync()
    {
        var ok = await _sim!.ConnectAsync(CancellationToken.None);
        SimStatusText.Text = ok ? "接続済み" : "接続失敗";
        Log(ok ? "シムに接続しました" : "シムへの接続に失敗しました");
        if (ok) _pollTimer.Start();
    }

    private void PollTimer_OnTick(object? sender, EventArgs e)
    {
        _sim?.Pump();
        var snap = _sim?.TryGetLatest();
        if (snap == null || _ooOi == null) return;

        _ooOi.Update(snap.Value);
        OoOiStateText.Text = $"OOOI状態: {_ooOi.State}";
    }

    private void OnPirepReady(PirepDraft draft)
    {
        Dispatcher.Invoke(async () =>
        {
            if (_activeReservationId == null)
            {
                Log("警告: アクティブな予約がないためPIREPを破棄しました。先に機体を予約してください。");
                return;
            }

            var dialog = new ConfirmPirepDialog(draft) { Owner = this };
            if (dialog.ShowDialog() != true || !dialog.Confirmed)
            {
                Log("PIREP提出をキャンセルしました");
                return;
            }

            var request = new SubmitPirepRequest
            {
                ReservationId = _activeReservationId.Value,
                DepartureIcao = dialog.Draft.DepartureIcao,
                ArrivalIcao = dialog.Draft.ArrivalIcao,
                ArrivalSpot = dialog.Draft.ArrivalSpot,
                DepartureTime = dialog.Draft.DepartureTimeUtc,
                ArrivalTime = dialog.Draft.ArrivalTimeUtc,
                FlightTimeMin = dialog.Draft.FlightTimeMin,
                Network = dialog.Draft.Network,
                VatsimCallsign = dialog.Draft.VatsimCallsign,
            };

            try
            {
                var result = await _api.SubmitPirepAsync(request);
                Log($"PIREP送信完了: id={result?.PirepId} status={result?.VerificationStatus}");
                _activeReservationId = null;
                ReservationStatusText.Text = "予約なし";
            }
            catch (Exception ex)
            {
                // 7.5節: 送信失敗時はOfflineQueueにENQUEUE。
                _offlineQueue.Enqueue(JsonSerializer.Serialize(request));
                Log($"PIREP送信に失敗したためオフラインキューに保存しました: {ex.Message}");
                _activeReservationId = null;
                ReservationStatusText.Text = "予約なし";
            }
        });
    }

    private void Log(string message)
    {
        Dispatcher.Invoke(() =>
        {
            _logEntries.Insert(0, $"[{DateTime.Now:HH:mm:ss}] {message}");
            // 無制限に増え続けないよう上限を設ける（開発者ログ用途なので直近だけ見えれば十分）。
            while (_logEntries.Count > 500) _logEntries.RemoveAt(_logEntries.Count - 1);
        });
    }

    private void MainWindow_OnClosing(object? sender, System.ComponentModel.CancelEventArgs e)
    {
        _tray?.Dispose();
        _pollTimer.Stop();
        _devLogWindow?.Close();
    }
}
