# RelayFleet — コミュニティ機体シェアシステム

「コミュニティ機体シェアシステム_設計書.md」に基づく実装一式。

```
RelayFleet/
  Web/   … Laravel 13 / PHP 8.3+（サーバ・REST API・Blade管理画面）
  App/   … C# / WPF / .NET 10（フライトシム連携クライアント）
```

各フォルダの `README.md` に、実装状況・セットアップ手順・未検証事項を詳しく書いてある。
まず結論から: **どちらも「読める・筋が通ったコードとして完成」しているが、
このサンドボックス環境にPHP/Composer/.NET SDKが無くネットワークも制限されているため、
実際にビルド・実行して動作確認はできていない。** 静的なチェック（構文の対応関係の確認、
Laravel/X-Plane UDP等の一次情報でのバージョン確認）は行った。ローカルで一度必ず動かしてから使うこと。

## 次にやること（優先順）

1. Web側で `composer install` → `.env` 設定 → `php artisan migrate` → `php artisan test` を実行し、
   Phase 1 DoD（3件のFeatureテスト）が通ることを確認する。
2. Discord Developer PortalとVATSIM Connectでアプリ登録し、実際のclient_id/secretを設定する。
3. Windowsアプリを `dotnet build` でビルドし、モックシムでOOOI→PIREP確認ダイアログ→API送信の流れを確認する。
4. 実機（MSFSまたはX-Plane12）でシム接続を確認する。
