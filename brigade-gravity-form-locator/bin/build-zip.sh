#!/usr/bin/env bash
# Builds an installable ZIP without development files.
# Usage: bin/build-zip.sh [output-directory]
set -euo pipefail

SLUG="brigade-gravity-form-locator"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$(cd "${1:-$ROOT/..}" && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf -- "$STAGE"' EXIT

mkdir "$STAGE/$SLUG"
cp -R \
	"$ROOT/brigade-gravity-form-locator.php" \
	"$ROOT/readme.txt" \
	"$ROOT/uninstall.php" \
	"$ROOT/includes" \
	"$ROOT/admin" \
	"$ROOT/languages" \
	"$STAGE/$SLUG/"

VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/$SLUG.php")"
ZIP="$OUT/$SLUG-$VERSION.zip"
rm -f -- "$ZIP"
(cd "$STAGE" && zip -qr -X "$ZIP" "$SLUG")
echo "$ZIP"
