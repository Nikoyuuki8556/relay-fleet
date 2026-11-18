# RelayFleetTracker — Windowsアプリ（C# / WPF / .NET 10）

「コミュニティ機体シェアシステム_設計書.md」7章に基づく実装。Web側は `../Web` を参照。

## .NET バージョンについて（要確認事項・ユーザー確認済み）

設計書は.NET 8を指定していたが、.NET 8は2026年11月10日にサポート終了（本セッション時点で約4ヶ月後）するため、
現行LTSの **.NET 10**（2028年11月まで）に変更した。SimConnect等の外部ライブラリ側の.NET 10対応状況は
実装時点で未検証のため、ビルド時にエラーが出た場合は `net10.0-windows` を `net8.0-windows` に戻すフォールバックも検討すること。

## 実装状況（正直な棚卸し）

- 完了: Core（OOOI状態機械、7.3節の疑似コードを忠実に実装）、X-Plane12アダプタ（UDP RREF、実装完結）、
  MSFS用アダプタの骨格（SimConnect SDK未導入でもビルドが通るようスタブにフォールバック — 後述）、
  APIクライアント（5章の全エンドポイント）、ネイティブOAuth（PKCE、RFC 8252準拠、7.4節）、
  Windows資格情報マネージャー連携、SQLiteオフラインキュー（7.5節）、最小限のWPF UI・トレイアイコン。
- **このサンドボックス環境に.NET SDK・Visual Studioが無く、ビルド確認ができていない。**
  必ずローカル（Windows、Visual Studio 2022 17.10+ または `dotnet` CLI）で一度ビルドしてから使うこと。
  静的なコード検査（波かっこ・かっこの対応チェック）は実施済みだが、コンパイル可否の保証にはならない。
- MSFS SimConnect部分は「本物のSimConnect DLLに依存する既知の制約」があるため、
  `RelayFleetTracker.csproj` は既定でスタブ実装にフォールバックしビルドが通るようにしてある。
  実機テストする場合は以下の「MSFS SimConnect対応を有効化する」を参照。
- X-Plane12アダプタは自己完結（外部SDK不要）だが、UDPポート番号・パケット構造は
  X-Plane側の設定（Settings > Network）や将来のマイナーアップデートで変わりうるため、
  実機での疎通確認を必ず行うこと（確度: 中〜高）。

## セットアップ

1. Visual Studio 2022（17.10以降、.NET 10 SDKを含むワークロード）または `dotnet` CLIをインストール。
2. `appsettings.json` の `ApiBaseUrl` / `DiscordClientId` / `VatsimClientId` を実際の値に設定。
   `client_secret` はこのアプリには**絶対に置かない**（Webサイト側のトークン交換エンドポイントのみが保持する、7.4節）。
3. ビルド:
   ```powershell
   dotnet restore
   dotnet build
   dotnet run
   ```

## MSFS SimConnect対応を有効化する

1. MSFS2020 SDKをインストールし、SDKルートを控える（例: `C:\MSFS SDK`）。
2. 環境変数 `MSFS_SDK` にそのパスを設定する。
3. 再度 `dotnet build` すると、`RelayFleetTracker.csproj` が自動的に
   `Microsoft.FlightSimulator.SimConnect.dll` を参照し、`SimAdapters/MsfsAdapter.cs` の
   `#if RFT_MSFS_SDK` ブロック（実際のSimConnect実装）が有効になる。

## 動作確認（シム無しで確認したい場合）

UIの「シム」ドロップダウンで「モック（動作確認用）」を選び「接続」を押すと、
実機に接続せずにOOOI状態機械やPIREP確認ダイアログの流れを確認できる
（`SimAdapters/MockFlightDataSource.cs` の `Push()` を使ってスナップショットを注入するテストコードを別途追加すると良い）。

## ディレクトリ構成（7.1節準拠）

```
/Core          … シム非依存のドメインロジック（OOOI状態機械、空港マスタ照合）
/SimAdapters   … IFlightDataSource実装（MSFS/X-Plane/モック）
/Api           … Web APIクライアント（Sanctumトークン）
/Auth          … ネイティブOAuth(PKCE)、資格情報マネージャー連携
/OfflineQueue  … SQLiteによる送信失敗時の再送キュー
/UI            … WPF（メインウィンドウ、トレイアイコン、PIREP確認ダイアログ）
```

## 既知の未確認事項（設計書10章と対応）

- VATSIM ConnectのPKCE対応可否（`Auth/NativeOAuthClient.cs` はPKCE対応前提）。
- カスタムURLスキームは意図的に不採用（RFC 8252、設計書7.4節末尾）。
