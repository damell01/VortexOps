#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="${WHATNOT_PROJECT_ROOT:-/var/www/vortexops}"
DISPLAY_NUM="${WHATNOT_BROWSER_DISPLAY:-101}"
DISPLAY_VALUE=":${DISPLAY_NUM}"
CDP_PORT="${WHATNOT_CDP_PORT:-9222}"
CDP_HOST="127.0.0.1"
CDP_URL="http://${CDP_HOST}:${CDP_PORT}"
PROFILE_DIR="${WHATNOT_USER_DATA_DIR:-${PROJECT_ROOT}/storage/whatnot-browser-profile}"
LOG_DIR="${WHATNOT_BROWSER_LOG_DIR:-${PROJECT_ROOT}/storage/logs}"
BROWSER_LOG="${LOG_DIR}/whatnot-browser.log"
XVFB_LOG="${LOG_DIR}/whatnot-xvfb.log"
START_URL="${WHATNOT_BROWSER_START_URL:-https://www.whatnot.com/dashboard/home}"

mkdir -p "$PROFILE_DIR" "$LOG_DIR"

cdp_ready() {
  curl -fsS --max-time 2 "${CDP_URL}/json/version" >/dev/null 2>&1
}

if cdp_ready; then
  echo "[whatnot-browser] Chromium already available at ${CDP_URL}"
  exit 0
fi

if ! command -v Xvfb >/dev/null 2>&1; then
  echo "[whatnot-browser] ERROR: Xvfb is required but not installed" >&2
  exit 1
fi

# Keep one persistent X display alive for the browser. This is only a display;
# it does not solve or suppress any site challenge. If Whatnot later requires
# human verification the scraper still detects that state and fails closed.
if ! pgrep -f "Xvfb ${DISPLAY_VALUE}( |$)" >/dev/null 2>&1; then
  nohup Xvfb "$DISPLAY_VALUE" -screen 0 1365x900x24 -nolisten tcp -ac \
    >>"$XVFB_LOG" 2>&1 &

  for _ in $(seq 1 20); do
    if [ -S "/tmp/.X11-unix/X${DISPLAY_NUM}" ]; then
      break
    fi
    sleep 0.25
  done
fi

CHROME="${PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH:-}"

if [ -z "$CHROME" ] && [ -f "${PROJECT_ROOT}/storage/chromium-path.txt" ]; then
  CANDIDATE="$(tr -d '\r\n' < "${PROJECT_ROOT}/storage/chromium-path.txt")"
  if [ -x "$CANDIDATE" ]; then
    CHROME="$CANDIDATE"
  fi
fi

if [ -z "$CHROME" ]; then
  for CANDIDATE in \
    /root/.cache/ms-playwright/chromium-*/chrome-linux64/chrome \
    /opt/pw-browsers/chromium-*/chrome-linux/chrome \
    /opt/pw-browsers/chromium-*/chrome-linux64/chrome; do
    for MATCH in $CANDIDATE; do
      if [ -x "$MATCH" ]; then
        CHROME="$MATCH"
        break 2
      fi
    done
  done
fi

if [ -z "$CHROME" ] && command -v google-chrome >/dev/null 2>&1; then
  CHROME="$(command -v google-chrome)"
fi

if [ -z "$CHROME" ] && command -v chromium >/dev/null 2>&1; then
  CHROME="$(command -v chromium)"
fi

if [ -z "$CHROME" ] || [ ! -x "$CHROME" ]; then
  echo "[whatnot-browser] ERROR: no Chromium executable found" >&2
  exit 1
fi

# Do not start a second Chromium against the shared profile. If a browser exists
# but CDP is unhealthy, surface that condition instead of deleting locks or
# killing a possibly human-owned session.
if pgrep -af "${PROFILE_DIR}" >/dev/null 2>&1; then
  echo "[whatnot-browser] ERROR: a Chromium process already owns ${PROFILE_DIR}, but CDP ${CDP_URL} is unavailable" >&2
  exit 1
fi

echo "[whatnot-browser] starting Chromium display=${DISPLAY_VALUE} cdp=${CDP_URL} profile=${PROFILE_DIR}"

nohup env DISPLAY="$DISPLAY_VALUE" "$CHROME" \
  --no-sandbox \
  --disable-dev-shm-usage \
  --remote-debugging-address="$CDP_HOST" \
  --remote-debugging-port="$CDP_PORT" \
  --user-data-dir="$PROFILE_DIR" \
  --window-size=1365,900 \
  "$START_URL" \
  >>"$BROWSER_LOG" 2>&1 &

for _ in $(seq 1 40); do
  if cdp_ready; then
    echo "[whatnot-browser] Chromium ready at ${CDP_URL}"
    exit 0
  fi
  sleep 0.5
done

echo "[whatnot-browser] ERROR: Chromium did not expose CDP within 20 seconds" >&2
exit 1
