using System.Collections.ObjectModel;
using System.Windows;

namespace RelayFleetTracker.UI;

/// <summary>
/// メイン画面から分離した内部ログ表示用ウィンドウ。非モーダルで開き、MainWindowが保持する
/// ObservableCollection&lt;string&gt;をそのままItemsSourceに束ねる（新規ログ追加時に自動反映される）。
/// </summary>
public partial class DeveloperLogWindow : Window
{
    public DeveloperLogWindow(ObservableCollection<string> logEntries)
    {
        InitializeComponent();
        LogListBox.ItemsSource = logEntries;
    }
}
