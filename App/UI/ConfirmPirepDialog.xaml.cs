using System.Windows;
using RelayFleetTracker.Core;

namespace RelayFleetTracker.UI;

public partial class ConfirmPirepDialog : Window
{
    public PirepDraft Draft { get; private set; }
    public bool Confirmed { get; private set; }

    public ConfirmPirepDialog(PirepDraft draft)
    {
        InitializeComponent();
        Draft = draft;

        DepartureIcaoBox.Text = draft.DepartureIcao;
        ArrivalIcaoBox.Text = draft.ArrivalIcao;
        ArrivalSpotBox.Text = draft.ArrivalSpot ?? "";
        SummaryText.Text =
            $"出発: {draft.DepartureTimeUtc:HH:mm} UTC / 到着: {draft.ArrivalTimeUtc:HH:mm} UTC\n" +
            $"飛行時間: {draft.FlightTimeMin} 分";
    }

    private void ConfirmButton_OnClick(object sender, RoutedEventArgs e)
    {
        var spot = ArrivalSpotBox.Text.Trim();
        Draft = new PirepDraft
        {
            DepartureIcao = DepartureIcaoBox.Text.Trim().ToUpperInvariant(),
            ArrivalIcao = ArrivalIcaoBox.Text.Trim().ToUpperInvariant(),
            ArrivalSpot = spot.Length > 0 ? spot : null,
            DepartureTimeUtc = Draft.DepartureTimeUtc,
            ArrivalTimeUtc = Draft.ArrivalTimeUtc,
            Network = Draft.Network,
            VatsimCallsign = Draft.VatsimCallsign,
        };
        Confirmed = true;
        DialogResult = true;
    }

    private void CancelButton_OnClick(object sender, RoutedEventArgs e)
    {
        Confirmed = false;
        DialogResult = false;
    }
}
