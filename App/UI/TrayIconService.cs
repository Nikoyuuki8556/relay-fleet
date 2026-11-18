using System.Windows;
using System.Windows.Forms;
using Application = System.Windows.Application;
using MessageBox = System.Windows.MessageBox;

namespace RelayFleetTracker.UI;

/// <summary>7.1節 /UI「トレイアイコン」。最小化時にタスクトレイへ格納する。</summary>
public sealed class TrayIconService : IDisposable
{
    private readonly NotifyIcon _notifyIcon;
    private readonly Window _window;

    public TrayIconService(Window window)
    {
        _window = window;

        _notifyIcon = new NotifyIcon
        {
            Icon = System.Drawing.SystemIcons.Application,
            Visible = true,
            Text = "RelayFleetTracker",
        };

        var menu = new ContextMenuStrip();
        menu.Items.Add("開く", null, (_, _) => ShowWindow());
        menu.Items.Add("終了", null, (_, _) => Application.Current.Shutdown());
        _notifyIcon.ContextMenuStrip = menu;

        _notifyIcon.DoubleClick += (_, _) => ShowWindow();

        _window.StateChanged += (_, _) =>
        {
            if (_window.WindowState == WindowState.Minimized)
            {
                _window.Hide();
            }
        };
    }

    private void ShowWindow()
    {
        _window.Show();
        _window.WindowState = WindowState.Normal;
        _window.Activate();
    }

    public void Dispose()
    {
        _notifyIcon.Visible = false;
        _notifyIcon.Dispose();
    }
}
