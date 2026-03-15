#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8090}"
OUT_DIR="${1:-/tmp/starcast-visual-check}"

mkdir -p "$OUT_DIR"

capture() {
  local name="$1"
  local path="$2"
  local viewport="$3"

  echo "Capturing ${name} -> ${path}"
  npx --yes playwright screenshot --browser=chromium --viewport-size="$viewport" "${BASE_URL}${path}" "${OUT_DIR}/${name}.png" >/dev/null
}

capture_phone() {
  local name="$1"
  local path="$2"

  echo "Capturing ${name} -> ${path} (Pixel 7)"
  npx --yes playwright screenshot --browser=chromium --device="Pixel 7" "${BASE_URL}${path}" "${OUT_DIR}/${name}.png" >/dev/null
}

capture "home-desktop" "/" "1440,2200"
capture "fibre-desktop" "/fibre/" "1440,2200"
capture_phone "fibre-mobile" "/fibre/"
capture_phone "lte-mobile" "/lte-5g/"
capture_phone "signup-mobile" "/signup/"
capture_phone "router-mobile" "/router-selection/"

echo "Saved screenshots to ${OUT_DIR}"
