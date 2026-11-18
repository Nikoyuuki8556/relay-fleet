using System.Windows;

namespace RelayFleetTracker.UI;

// UseWindowsForms(トレイアイコン用)を有効にしているため、
// System.Windows.Application と System.Windows.Forms.Application が両方スコープに入り
// 単に"Application"と書くとCS0104(あいまいな参照)になる。フル修飾して回避する。
public partial class App : System.Windows.Application
{
    protected override void OnStartup(StartupEventArgs e)
    {
        base.OnStartup(e);
        RelayFleetTracker.AppConfig.Load();
    }
}
