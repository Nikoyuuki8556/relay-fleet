# RelayFleet — Web（Laravel 13 / PHP 8.3+）

「コミュニティ機体シェアシステム_設計書.md」（RelayFleet 実装仕様書）に基づく実装。
このフォルダはWeb側のみ。Windowsアプリは `../App` を参照。

## 実装状況（正直な棚卸し）

- 完了: DB設計（3章DDL）、排他制御（3.1節）、メンバーシップ3経路（3.2節）、
  REST API（5章の全エンドポイント）、VATSIM検証ロジック（4.2節）、
  Discord Webhook・Interactions署名検証（6章）、Sanctumトークン発行（7.4節連携先）、
  最小限のBladeフロント、Feature testsでPhase 1/2のDoDの一部を検証。
- 未実施（このサンドボックス環境にPHP/Composer/MySQLが無く、外部パッケージ取得も
  ネットワーク制限で不可のため、実行・自動テストができていない）:
  実際の `composer install` / `php artisan migrate` / `php artisan test` の実行確認。
  **必ずローカルで一度実行してから本番投入すること。**
- 確度が低い/要一次情報確認の箇所（設計書10章と同じ論点）:
  1. VATSIM ConnectのPKCE対応可否（`app/Services/VatsimOAuthService.php` はPKCE対応前提で実装）
  2. VATSIM Data APIの利用規約
  3. 実在エアラインの商標運用方針
  4. Discordのwebhook URLをコミュニティ単位で持たせるカラムがDDLに無い点
     （`DiscordNotifier` はグローバル1本のwebhookを暫定利用。運用時は
     `communities.discord_webhook_url` 等の追加を検討）

## セットアップ

```bash
composer install
cp .env.example .env
php artisan key:generate
# .envにDB接続情報・DISCORD_*・VATSIM_*を設定
php artisan migrate
php artisan db:seed   # サンプルコミュニティ・機体を投入（任意）
php artisan serve
```

### デプロイ先がXserver共用レンタルサーバー（root権限なし・常駐プロセス不可）の場合

`deployment/xserver-cron-setup.md` を参照。VATSIMポーラー(`vatsim:poll`)は1回実行して終了する設計に変更済みで、
サーバーパネルのCron設定から1分毎に `php artisan schedule:run` を叩くだけで、
`vatsim:poll` と `reservations:release-expired` が自動的に動く。キューは`.env`の
`QUEUE_CONNECTION=sync`により常駐ワーカー無しでそのまま動く（PIREP提出時に同期実行される）。

### デプロイ先がVPS/専用サーバー（root権限あり・常駐プロセス可）の場合

`deployment/supervisor-relayfleet.conf` を参照。`schedule:work`を1プロセス常駐させるだけでよい。

```bash
php artisan schedule:work
# QUEUE_CONNECTION=database に戻した場合のみ↓も常駐させる
php artisan queue:work database
```

## テスト

```bash
php artisan test
```

`tests/Feature/MembershipTest.php` / `ReservationTest.php` / `PirepVerificationTest.php` が
9章のPhase 1・Phase 2 DoDに対応する（ただしReservationTestの同時実行検証は
単一プロセステストの限界により擬似的な検証である旨をテスト内コメントに明記済み）。

## Discord/VATSIM アプリ登録（実装前に必須）

- Discord Developer Portalでアプリを作成し、OAuth2のRedirect URIに
  `{APP_URL}/auth/discord/callback` を登録。Interactions Endpoint URLには
  `{APP_URL}/discord/interactions` を設定（Ed25519公開鍵を`DISCORD_APPLICATION_PUBLIC_KEY`に設定）。
- VATSIM Connectでアプリ登録し、`VATSIM_CONNECT_CLIENT_ID/SECRET`を設定。
  PKCE対応可否は https://vatsim.dev/services/connect/ で必ず確認すること。

## ディレクトリ構成の要点

```
app/Models/            … 3章DDLに対応するEloquentモデル
app/Services/          … 3.1/3.2/4.2/6.2節の疑似コードをそのまま実装したビジネスロジック
app/Http/Controllers/Api    … 5章のREST API
app/Http/Controllers/Auth   … Discord/VATSIM OAuth（Blade用・ネイティブアプリ用の両方）
app/Http/Controllers/Web    … Bladeフロント
app/Console/Commands/  … vatsim:poll（単発、スケジューラ/cronから毎分起動）, reservations:release-expired（毎分）
deployment/            … xserver-cron-setup.md（共用サーバー向け）, supervisor-relayfleet.conf（VPS向け）
app/Jobs/VerifyPirepJob.php … PIREP自動検証の非同期ジョブ
database/migrations/   … 3章DDLの忠実な移植
```
