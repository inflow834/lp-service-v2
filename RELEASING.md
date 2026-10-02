# リリース手順（メンテナー向け）

前提となる取り決め:

- タグ `vX.Y.Z` と `style.css` の `Version: X.Y.Z` は必ず一致させます。
- リリースに添付するzipの名前は必ず `lp-service-v2.zip`、zip内のルートフォルダは `lp-service-v2/` です。
- タグをpushすると GitHub Actions の `.github/workflows/release.yml` が `npm ci && npm run build` でブロックをビルドし、`bin/package.sh` でzipを作って Release を公開します。
- `build/` と `node_modules/` はコミットしません。
- 各サイトのテーマは `style.css` の `Update URI: https://github.com/inflow834/lp-service-v2` と `inc/updater.php` を使い、GitHubの公開API（トークン不要）で最新リリースを確認します。

注意: このパイプラインは本ドキュメント作成時点では動作確認されていません。初回は下の「初回公開チェックリスト」に沿って、1つのサイトで検証してください。

## 通常のリリース手順

1. 変更を加えたら、`style.css` の `Version:` を上げます（セマンティックバージョニング: 不具合修正は X.Y.Z の Z、機能追加は Y、互換性を壊す変更は X）。`LP_SERVICE_VERSION`（ブロック・CSS・JSのキャッシュ更新に使う値）は、`functions.php` が `style.css` の `Version` から読み取るため、ほかの場所を直す必要はありません。
2. 変更をコミットして `main` にpushします。`check.yml` ワークフローがビルドと `php -l` の構文チェックを行うので、成功を確認します。
3. タグを作ってpushします。
   ```
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```
4. Release ワークフローが自動でzipをビルドし、Releaseを公開します。Actionsタブで成功を確認し、Releasesページに `lp-service-v2.zip` が添付されていることを確認します。
5. 各サイトには、約12時間以内（WordPressの更新確認は1日2回）に更新通知が出ます。すぐ反映したい場合は、各サイトで「ダッシュボード > 更新」の「再確認」を押します。

## 初回公開チェックリスト

- [x] `style.css` の `Update URI` を `https://github.com/inflow834/lp-service-v2` に設定済み（`README.md` のリンクも同様）。更新確認の対象リポジトリは、この `Update URI` だけから読み取ります。`inc/updater.php` にある `'OWNER'` という文字列は「未設定のまま」を判定するための比較用なので、置き換えないでください。
- [x] ライセンス（GPL-2.0-or-later）と作者名（inflow）を `style.css` と `LICENSE` に設定済み。
- [ ] 公開（Public）リポジトリ `lp-service-v2` をGitHubに作成する。
- [ ] 公開前に、含めたくないファイル（`CLAUDE.md` など内部向け資料）を除外または整理する。
- [ ] リポジトリにpushする。
- [ ] Actionsが403で失敗する場合は、Settings > Actions > General > Workflow permissions を「Read and write permissions」にする（Releaseの作成に `contents: write` が必要）。
- [ ] 最初のタグ `v1.4.0` をpushし、Releaseに `lp-service-v2.zip` が付くことを確認する。
- [ ] zipの中身が `lp-service-v2/` フォルダで始まり、`build/` を含むことを確認する。
- [ ] 1.4.0 を各サイトに手動でインストールする（約10〜50サイト）。1.4.0 が更新機能を持つ最初のバージョンのため、これ以前のバージョンは自動更新されません。インストール手順は [README.md](README.md) を参照。
- [ ] 1つのテストサイトで、1.4.1 など次のバージョンをリリースし、更新通知から実際に更新できることを確認してからほかのサイトへ広げる。

## トラブルシューティング

| 症状 | 原因と対処 |
|---|---|
| Release ワークフローが失敗する（バージョン不一致） | タグ `vX.Y.Z` と `style.css` の `Version` が違います。`style.css` を直して再コミットし、タグを付け直します（誤ったタグは `git tag -d vX.Y.Z` と `git push origin :refs/tags/vX.Y.Z` で削除）。 |
| ワークフローが 403 で失敗する | Settings > Actions > General > Workflow permissions を「Read and write」にします。 |
| 更新後にテーマが別名のフォルダになる、または有効なテーマが外れる | zipのルートフォルダ名が `lp-service-v2` になっていません。GitHub自動生成の「Source code」zipを使っていないか、`bin/package.sh` の出力を確認します。 |
| サイトに更新通知が出ない | アセット名が正確に `lp-service-v2.zip` か確認します。GitHubの公開APIは未認証だとレート制限（IPあたり1時間60回）があり、更新確認の結果は10分キャッシュされます（失敗した場合も10分）。待つか、「ダッシュボード > 更新」で再確認します。多数のサイトが同じIPから確認する環境では制限に当たる場合があります。 |
| 公開したReleaseがドラフトのまま・プレリリース扱い | GitHubの「最新リリース」の取得（`releases/latest`）は、ドラフトとプレリリースを返しません。公開済みの通常のリリースだけが更新の対象になります。 |
| 誤ったバージョンを配布してしまった | GitHubのRelease/タグは差し替えず、より大きいバージョン番号の修正版を新しくリリースしてください（WordPressは現在より新しいバージョンしか更新対象にしません）。サイトを古いバージョンに戻したい場合は、古い `lp-service-v2.zip` を管理画面の「テーマのアップロード」から手動で上書きインストールします。 |

## 多数のサイトを運用するときの注意

- サイトごとに自動更新をオンにするか、管理者が手動で更新するかを決めておきます。LPが収益に直結するサイトは手動更新にし、内容を確認してから更新する運用が安全です。
- 新バージョンはまず1つのサイトで検証してから広げます。可能ならステージングサイトを用意し、本番と同じ構成でLPの表示・比較テーブル・絞り込みを確認します。
- 更新で変わるのはテーマのファイルだけで、データベース内のコンテンツは変わりません。ただし、ブロックの仕様やメタデータの扱いを変えるリリースでは既存のLPの表示に影響する場合があるため、リリースノートに明記してください。
- テーマのファイルを直接編集しているサイトでは、更新でその変更が上書きされます。事前に把握して、カスタマイズが必要なら別の仕組みに移します。
