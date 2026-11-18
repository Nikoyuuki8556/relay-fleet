# RelayFleet — コミュニティ機体シェアシステム

フライトシミュレーターの機体を、コミュニティのメンバー全員で共有して運用するためのシステムです。
Volanta の「機体の所在（テールナンバー単位で、いまどこの空港にあるか）」という考え方を、個人ではなくコミュニティ全体で共有できるようにしました。

システムは次の2つで構成されます。

- **Web**（`Web/`）: サーバー側。Laravel 13 / PHP 8.3+。REST API と Blade の管理画面を提供します
- **App**（`App/`）: Windows 用のフライトシム連携クライアント。C# / WPF / .NET 10。MSFS / X-Plane 12 と接続し、フライトを自動で記録して Web へ送信します

本リポジトリは「コミュニティ機体シェアシステム_設計書.md」（RelayFleet 実装仕様書）に基づいて実装したものです。各フォルダの `README.md` に、フォルダごとの詳細（実装状況・セットアップ・未検証事項）を書いています。

## できること

### コミュニティとメンバーシップ

- コミュニティの作成、招待コードによる参加、公開コミュニティへの参加（即時参加／承認制）の3経路
- ロール（owner / admin / dispatcher / pilot）はユーザーではなくコミュニティごとの所属（メンバーシップ）に持たせます。同じユーザーでも、コミュニティごとに違うロールを持てます
- メンバーの承認・ロール変更・BAN、コミュニティの設定・リンク集・監査ログなどの管理機能（owner / admin 向け）

### 機体の共有と予約

- 機体は登録記号（テールナンバー）単位で管理し、所属空港・現在位置・状態（available / reserved / in_flight / maintenance / retired）を保持します
- 予約による排他制御を実装しています。行ロック（`SELECT ... FOR UPDATE`）と楽観的ロック（version カラム）を併用し、同じ機体が二重に使われることを防ぎます
- 予約の有効期間は既定で12時間です。期限切れの予約は毎分のスケジューラが自動で解放します
- 使用中の機体には順番待ち（予約キュー）に並ぶことができ、空きが出ると先頭のメンバーへ自動で回ります
- 機体ごとの運用予定（スケジュール）登録、整備間隔の設定、所属エアラインや空港マスタの管理もできます

### PIREP と VATSIM 連携

- フライト実績（PIREP）は予約とひも付けて提出され、到着空港が機体の新しい現在位置になります
- VATSIM Data API を60秒間隔でポーリングしてフライトデータを蓄積し、自己申告の PIREP と照合します。一致した PIREP は `auto_verified` として自動検証されます（オフラインのフライトは対象外で、人力レビューに回ります）
- ログインは Discord OAuth と VATSIM Connect OAuth に対応します

### Discord 連携

- PIREP 確定時と参加リクエスト発生時に、Discord Webhook へ通知を送ります
- Interactions Endpoint（Ed25519 署名検証つき）を実装し、`/aircraft <registration>` スラッシュコマンドで機体の現在位置と状態を返します

### Windows アプリ（RelayFleetTracker）

- シム接続アダプタ: MSFS 2020 / 2024（SimConnect）、X-Plane 12（UDP の RREF 購読）、動作確認用のモック
- OOOI（Out / Off / On / In）状態機械でフライトを自動検出し、出発・到着空港と時刻を記録します。着陸後に確認ダイアログを出し、内容を確認・修正してから PIREP を送信します
- 認証はネイティブ OAuth（PKCE、RFC 8252 準拠、ループバックリダイレクト）で行い、トークンは Windows 資格情報マネージャーに保存します。`client_secret` はアプリに置かず、Web 側のトークン交換エンドポイントだけが保持します
- 送信に失敗した PIREP は SQLite のオフラインキューに保存し、バックグラウンドで再送します
- トレイアイコン常駐、複数コミュニティ所属時の切り替えに対応します

## 技術スタック

| 対象 | 技術 |
|---|---|
| Web | PHP 8.3+ / Laravel 13 / Laravel Sanctum / MySQL 8 |
| Web フロント | Blade / Tailwind CSS 4 / Vite 8 |
| App | C# / WPF / .NET 10（Windows、x64） |
| App のデータ保存 | SQLite（Microsoft.Data.Sqlite、オフラインキュー用） |
| 外部連携 | VATSIM Data API / VATSIM Connect / Discord API（OAuth・Webhook・Interactions） |
| 対象シム | MSFS 2020 / MSFS 2024 / X-Plane 12（P3D は対象外） |

## リポジトリ構成

```
RelayFleet/
  Web/   … Laravel 13（REST API・Blade 管理画面・Discord / VATSIM 連携）
  App/   … C# / WPF / .NET 10（フライトシム連携クライアント「RelayFleetTracker」）
```

**Web 側のおもなディレクトリ**

```
Web/app/Models/               … 設計書の DDL に対応する Eloquent モデル
Web/app/Services/             … 予約の排他制御、メンバーシップ、PIREP 検証などのビジネスロジック
Web/app/Http/Controllers/Api  … Windows アプリ向け REST API（/api/v1）
Web/app/Http/Controllers/Web  … Blade 管理画面
Web/app/Console/Commands/     … vatsim:poll（単発実行）、reservations:release-expired
Web/deployment/               … Xserver 向け cron 手順、VPS 向け Supervisor 設定
```

**App 側のおもなディレクトリ**

```
App/Core/          … シム非依存のドメインロジック（OOOI 状態機械、空港マスタ照合）
App/SimAdapters/   … IFlightDataSource の実装（MSFS / X-Plane / モック）
App/Api/           … Web API クライアント
App/Auth/          … ネイティブ OAuth（PKCE）、資格情報マネージャー連携
App/OfflineQueue/  … SQLite による再送キュー
App/UI/            … WPF（メインウィンドウ、トレイアイコン、PIREP 確認ダイアログ）
```

## セットアップ

### 1. 外部アプリの登録（先に必要）

- **Discord**: Discord Developer Portal でアプリを作成し、OAuth2 の Redirect URI に `{APP_URL}/auth/discord/callback` を登録します。Interactions Endpoint URL には `{APP_URL}/discord/interactions` を設定し、アプリの公開鍵を `.env` の `DISCORD_APPLICATION_PUBLIC_KEY` に入れます
- **VATSIM**: VATSIM Connect でアプリを登録し、Client ID / Secret を `.env` に設定します。VATSIM ログインは、Connect のアプリ申請が承認されるまで `.env` の `VATSIM_LOGIN_ENABLED` で無効にしておく想定です（承認後に `true` へ切り替えます）

### 2. Web

```bash
cd Web
composer install
cp .env.example .env
php artisan key:generate
# .env に DB 接続情報と DISCORD_* / VATSIM_* を設定する
php artisan migrate
php artisan db:seed   # サンプルコミュニティ・機体の投入（任意）
php artisan serve
```

フロントエンドのビルドが必要な場合は `npm install && npm run build` を実行します。

デプロイは2方式に対応しています。

- **共用レンタルサーバー（Xserver など、常駐プロセス不可）**: `Web/deployment/xserver-cron-setup.md` を参照。サーバーの cron で `php artisan schedule:run` を1分ごとに実行する方式で、VATSIM ポーリングと期限切れ予約の解放が動きます。キューは `QUEUE_CONNECTION=sync` で同期実行します
- **VPS / 専用サーバー**: `Web/deployment/supervisor-relayfleet.conf` を参照。`php artisan schedule:work` を常駐させます

### 3. App

1. Visual Studio 2022（17.10以降）または .NET 10 SDK を用意します
2. `App/appsettings.json` の `ApiBaseUrl` / `DiscordClientId` / `VatsimClientId` を実際の値に設定します
3. ビルドして実行します

```powershell
cd App
dotnet restore
dotnet build
dotnet run
```

MSFS の SimConnect 連携を有効にする場合は、MSFS 2020 SDK をインストールし、環境変数 `MSFS_SDK` に SDK のルートを設定してからビルドします。未設定の場合はスタブ実装にフォールバックし、SDK がない環境でもビルドできます。
シムなしで動作を確認したい場合は、UI の「シム」ドロップダウンで「モック（動作確認用）」を選んで接続してください。

なお、設計書では .NET 8 の指定でしたが、.NET 8 は2026年11月にサポートが終了するため、現行 LTS の .NET 10（2028年11月までサポート）に変更しています。

## API の概要

Windows アプリ向けの REST API です。ベースパスは `/api/v1`、認証は Laravel Sanctum の Bearer トークンです。ブラウザ向けの管理画面は REST API を経由せず、Web 側のサービス層を直接呼びます。

**おもなエンドポイント**

| メソッド | パス | 説明 |
|---|---|---|
| POST | `/communities` | コミュニティ作成（作成者が owner になります） |
| GET | `/communities?visibility=public` | 公開コミュニティの一覧（募集中フィルタ可） |
| POST | `/communities/join-by-code` | 招待コードで参加 |
| POST | `/communities/{id}/join` | 公開コミュニティへ参加（ポリシーにより即時／承認待ち） |
| GET | `/me/communities` | 自分が所属するコミュニティとロールの一覧 |
| GET | `/communities/{id}/aircraft` | 機体一覧 |
| POST | `/aircraft/{id}/reserve` | 機体の予約（競合時は 409） |
| POST | `/reservations/{id}/release` | 予約の解放 |
| POST | `/pireps` | PIREP の提出 |
| GET | `/pireps` | 自分の PIREP 履歴 |

このほかに、Windows アプリのログイン用に、認可コードを Sanctum トークンへ交換するエンドポイント（`/auth/discord/token`、`/auth/vatsim/token`）があります。

## テスト

```bash
cd Web
php artisan test
```

`Web/tests/Feature/` の `MembershipTest` / `ReservationTest` / `PirepVerificationTest` が、設計書フェーズ1・2の完了条件（DoD）に対応します。ただし `ReservationTest` の同時実行の検証は、単一プロセスのテストの限界により擬似的な検証であることをテスト内のコメントに明記しています。

## 実装状況と未検証事項

コードは一通り実装済みですが、**作成環境に PHP / Composer / .NET SDK がなかったため、ビルドと実行の確認はできていません**。静的な検査（構文や対応関係の確認、仕様の一次情報確認）は実施済みです。本番に入れる前に、必ずローカルで `composer install` → マイグレーション → `php artisan test` と、App の `dotnet build` を実行して確認してください。

確認が取れていない主な事項は次の4点です。

1. VATSIM Connect の PKCE 対応可否（OAuth 実装は PKCE 対応を前提にしています）
2. VATSIM Data API の利用規約（キャッシュ保持期間・再配布制限）
3. 実在の登録記号・エアライン塗装を扱う場合の商標運用方針
4. Discord Webhook は現在グローバル1本の暫定運用です。コミュニティごとに持たせる場合は `communities` へのカラム追加を検討してください

## スコープ外（Non-goals）

- リバリー・フライトダイナミクス・サウンドなどの実ファイル配布（扱うのはメタデータのみ）
- ペイウェア機体の改変ファイルの配布
- P3D への対応
- 決済・課金機能
- コミュニティをまたいだ機体の貸し借り（機体は所属コミュニティ内でのみ共有します）

## ライセンス

MIT License です。詳細は [LICENSE](LICENSE) を参照してください。
