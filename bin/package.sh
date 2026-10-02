#!/usr/bin/env bash
#
# 配布用zip（dist/lp-service-v2.zip）を作成する。
#
# 使い方:
#   bash bin/package.sh            # style.css の Version をそのまま使う
#   bash bin/package.sh 1.4.0      # style.css の Version が 1.4.0 と一致しなければ失敗する（リリースCI用）
#
# 前提: 先に `npm run build` を実行して build/blocks を生成しておくこと。
# 出力: dist/lp-service-v2.zip（zipのルートフォルダは lp-service-v2/ ＝ WordPressが同じテーマフォルダへ上書き更新できる）
#
# Git Bash（Windows）と ubuntu-latest（GitHub Actions）の両方で動く。zipコマンドが無い環境では bin/make-zip.js（node）で代用する。

set -euo pipefail

THEME_SLUG="lp-service-v2"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$DIST/$THEME_SLUG"
ZIP="$DIST/$THEME_SLUG.zip"
EXPECTED_VERSION="${1:-}"

fail() {
	echo "エラー: $1" >&2
	exit 1
}

cd "$ROOT"

# ---- 事前チェック ----------------------------------------------------------

[ -f style.css ] || fail "style.css が見つかりません（テーマのルートで実行してください）。"

# style.css の「Version:」行からバージョンを取得（CRLFにも対応）
VERSION_LINE="$(tr -d '\r' < style.css | grep -m1 -E '^[[:space:]]*Version:' || true)"
VERSION="${VERSION_LINE#*:}"
VERSION="$(echo "$VERSION" | tr -d '[:space:]')"
[ -n "$VERSION" ] || fail "style.css に「Version:」行がありません。"
if ! echo "$VERSION" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$'; then
	fail "style.css の Version「$VERSION」が X.Y.Z 形式ではありません。"
fi

if [ -n "$EXPECTED_VERSION" ]; then
	EXPECTED_VERSION="${EXPECTED_VERSION#v}"
	if [ "$EXPECTED_VERSION" != "$VERSION" ]; then
		fail "指定バージョン（$EXPECTED_VERSION）と style.css の Version（$VERSION）が一致しません。style.css の Version を更新してから、同じ番号のタグ v$VERSION を打ってください。"
	fi
fi

if [ ! -d build/blocks ] || [ -z "$(find build/blocks -name block.json -print -quit 2>/dev/null)" ]; then
	fail "build/blocks が無い、または空です。先に npm ci && npm run build を実行してください。"
fi

# ---- ステージング ----------------------------------------------------------

echo "== $THEME_SLUG v$VERSION のパッケージを作成します =="
rm -rf "$DIST"
mkdir -p "$STAGE"

# ルート直下を1件ずつ見て、開発専用のものを除いてコピーする。
# 除外: node_modules / src / bin / dist / docs / tests / ドットファイル(.git*, .github 等) /
#       package.json / package-lock.json / *.md(CLAUDE.md, README.md, RELEASING.md) / *.bak / *.zip / *.log
shopt -s dotglob nullglob
for entry in "$ROOT"/*; do
	name="$(basename "$entry")"
	case "$name" in
		node_modules|src|bin|dist|docs|tests|.*) continue ;;
		package.json|package-lock.json) continue ;;
		*.md|*.bak|*.zip|*.log) continue ;;
	esac
	cp -R "$entry" "$STAGE/"
done
shopt -u dotglob nullglob

# 配下に紛れ込んだゴミを念のため削除
find "$STAGE" \( -name '*.bak' -o -name '.DS_Store' -o -name 'Thumbs.db' -o -name '.git*' \) -exec rm -rf {} + 2>/dev/null || true

# ---- ステージングの検証 ----------------------------------------------------

for required in style.css functions.php index.php inc assets template-parts build/blocks; do
	[ -e "$STAGE/$required" ] || fail "必須ファイル/フォルダ「$required」がステージングに含まれていません。"
done
[ -n "$(find "$STAGE/build/blocks" -name block.json -print -quit)" ] || fail "ステージング内の build/blocks に block.json がありません。"

for forbidden in node_modules src bin dist docs tests package.json package-lock.json CLAUDE.md README.md RELEASING.md .github .gitignore; do
	[ ! -e "$STAGE/$forbidden" ] || fail "除外すべき「$forbidden」がステージングに含まれています。"
done
if [ -n "$(find "$STAGE" \( -name '*.bak' -o -name '*.md' -o -name '*.zip' \) -print -quit)" ]; then
	fail "ステージングに *.bak / *.md / *.zip が含まれています。"
fi

# PHPファイルの構文チェック（phpがある場合のみ）
if command -v php >/dev/null 2>&1; then
	find "$STAGE" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null \
		|| fail "PHPの構文エラーがあります（php -l で個別に確認してください）。"
fi

# ---- zip作成 ---------------------------------------------------------------

if command -v zip >/dev/null 2>&1; then
	(cd "$DIST" && zip -r -q -X "$THEME_SLUG.zip" "$THEME_SLUG")
elif command -v node >/dev/null 2>&1; then
	node "$ROOT/bin/make-zip.js" "$ZIP" "$DIST" "$THEME_SLUG"
else
	fail "zip コマンドも node も見つかりません。どちらかをインストールしてください。"
fi

[ -s "$ZIP" ] || fail "zip の作成に失敗しました。"

# ---- zipの検証 -------------------------------------------------------------

if command -v unzip >/dev/null 2>&1; then
	ENTRIES="$(unzip -Z1 "$ZIP")"
	if echo "$ENTRIES" | grep -v "^$THEME_SLUG/" | grep -q .; then
		fail "zip に「$THEME_SLUG/」以外のルートを持つエントリがあります。"
	fi
	if echo "$ENTRIES" | grep -q '\\'; then
		fail "zip のエントリ名にバックスラッシュが含まれています。"
	fi
	echo "$ENTRIES" | grep -qx "$THEME_SLUG/style.css" || fail "zip に $THEME_SLUG/style.css がありません。"
	echo "$ENTRIES" | grep -q "^$THEME_SLUG/build/blocks/.*/block.json$" || fail "zip に build/blocks の block.json がありません。"
else
	echo "注意: unzip が無いため zip の中身の自動検証は省略しました。"
fi

# ---- サマリー --------------------------------------------------------------

FILE_COUNT="$(find "$STAGE" -type f | wc -l | tr -d ' ')"
ZIP_BYTES="$(wc -c < "$ZIP" | tr -d ' ')"
echo
echo "== 含まれるトップレベル =="
(cd "$STAGE" && ls -1A)
echo
echo "== 完了 =="
echo "バージョン : $VERSION"
echo "ファイル数 : $FILE_COUNT"
echo "zipサイズ  : $ZIP_BYTES bytes（約 $(( (ZIP_BYTES + 1023) / 1024 )) KB）"
echo "出力先     : dist/$THEME_SLUG.zip"
