#!/usr/bin/env bash
# Build the browser extension zip (load it unpacked in Chrome, or upload to the Chrome Web Store).
# Usage: bin/build-extension-zip.sh [output-dir]   (default: dist/)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/dist}"; mkdir -p "$OUT"; OUT="$(cd "$OUT" && pwd)"
VERSION="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["version"])' "$ROOT/browser-extension/manifest.json")"
ZIP="$OUT/aff-link-cloaker-extension-$VERSION.zip"

for f in "$ROOT"/browser-extension/*.js; do node --check "$f"; done
rm -f "$ZIP"
(cd "$ROOT/browser-extension" && zip -qr "$ZIP" . -x 'README.md' -x '.*')
echo "$ZIP"
