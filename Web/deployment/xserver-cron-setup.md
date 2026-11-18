# Xserver共用レンタルサーバーへのデプロイ（cron方式）

root権限がなく常駐プロセス（Supervisor等）を動かせない環境向けの手順。
一次情報で確認済み（https://www.xserver.ne.jp/manual/man_program_cron.php ）:
Xserverのcron設定はサーバーパネルの「Cron設定」から行い、**最短実行間隔は1分**（秒単位指定は不可）。
これは設計書4.2節の「60秒間隔」要件とちょうど一致する。

## 1. PHP CLIのパスを確認する

ブラウザ用PHPとSSH/cronで使われるPHPはパスが別（バージョンも別のことがある）。
サーバーパネルの「サーバー情報」→「コマンドパス一覧」で、使いたいPHPバージョン（8.3以上）の
実行パスを確認する（例: `/usr/bin/php8.3`）。SSHで直接確認する場合は以下でも良い。

```bash
ls /usr/bin/php*
/usr/bin/php8.3 -v
```

## 2. Cron設定にスケジューラを登録する

サーバーパネルの「Cron設定」で、以下を1分間隔で登録する（`/home/ユーザーID/RelayFleet-Web`は実際のアップロード先に置き換える）。

```
/usr/bin/php8.3 /home/niko8556/RelayFleet-Web/artisan schedule:run >> /dev/null 2>&1
```

これだけで、routes/console.php に登録済みの以下2つが自動的に毎分実行される。

- `reservations:release-expired`（3.1節: 失効予約の解放）
- `vatsim:poll`（4.2節: VATSIM Data APIの1回ポーリング）

キュー（`VerifyPirepJob`）は `.env` の `QUEUE_CONNECTION=sync` により、
PIREP提出リクエストの中でそのまま同期実行されるため、別途queue:work用のcronは不要。

## 3. Webサーバー側の設定

- ドキュメントルートは `public/` を指すようにする（Xserverのマルチドメイン設定で
  「サブディレクトリ」ではなく `public` を公開フォルダに指定する）。
- `.env` の `APP_URL` を実際のドメインに合わせる。
- `php artisan migrate --force` をSSHで実行してテーブルを作成する。

## 4. 動作確認

```bash
# schedule:run が正しく両コマンドを認識しているか確認
/usr/bin/php8.3 /home/niko8556/RelayFleet-Web/artisan schedule:list
```

`vatsim:poll` と `reservations:release-expired` が毎分(`* * * * *`)で表示されればOK。

## VPSへ移行する場合

root権限を持つVPS/専用サーバーに移行する場合は、`deployment/supervisor-relayfleet.conf` の
`schedule:work`常駐プロセス方式に切り替えると、cronの1分単位の粒度よりも取りこぼしなく動く
（ただし現状のvatsim:pollは1回実行して終了する設計のままで問題なく動作する）。
