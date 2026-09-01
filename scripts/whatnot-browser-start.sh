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

# IMPORTANT: this helper is launched by whatnot-runner.cjs, whose stdout is the
# scraper's machine-readable JSON result. Keep every lifecycle/status message on
# stderr so an automatic browser start can never corrupt that JSON payload.
log() {
  echo "$*" >&2
}

cdp_ready() {
  local body
  body="$(curl -fsS --max-time 2 "${CDP_URL}/json/version" 2>/dev/null)" || return 1
  printf '%s' "$body" | grep -q '"webSocketDebuggerUrl"' || return 1
}

browser_owns_profile() {
  pgrep -af -- "--user-data-dir=${PROFILE_DIR}" >/dev/null 2>&1
}

stable_cdp_ready() {
  # Chrome can briefly expose /json/version and then exit during startup. Require
  # three consecutive healthy responses while the profile-owning process remains
  # alive before declaring the persistent browser ready.
  for _ in 1 2 3; do
    cdp_ready || return 1
    browser_owns_profile || return 1
    sleep 1
  done
  return 0
}

if stable_cdp_ready; then
  log "[whatnot-browser] Chromium already available and stable at ${CDP_URL}"
  exit 0
fi

if ! command -v Xvfb >/dev/null 2>&1; then
  log "[whatnot-browser] ERROR: Xvfb is required but not installed"
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

# Prefer the browser revision installed for this project's Playwright version.
# This avoids accidentally starting an old shared Chromium build from /opt that
# no longer matches node_modules/playwright.
if [ -z "$CHROME" ] && command -v node >/dev/null 2>&1; then
  CANDIDATE="$(cd "$PROJECT_ROOT" && node -e 'try { const { chromium } = require("playwright"); process.stdout.write(chromium.executablePath()); } catch (_) {}' 2>/dev/null || true)"
  if [ -n "$CANDIDATE" ] && [ -x "$CANDIDATE" ]; then
    CHROME="$CANDIDATE"
  fi
fi

# Fallback to Playwright cache installations, newest revision first.
if [ -z "$CHROME" ]; then
  CANDIDATE="$(find /root/.cache/ms-playwright -maxdepth 3 -type f -path '*/chromium-*/chrome-linux64/chrome' -perm -111 -printf '%p\n' 2>/dev/null | sort -V | tail -1)"
  if [ -n "$CANDIDATE" ] && [ -x "$CANDIDATE" ]; then
    CHROME="$CANDIDATE"
  fi
fi

# System Chrome/Chromium are fallbacks only. Do not prefer stale shared
# Playwright browser directories over the revision required by this project.
if [ -z "$CHROME" ] && command -v google-chrome-stable >/dev/null 2>&1; then
  CHROME="$(command -v google-chrome-stable)"
fi

if [ -z "$CHROME" ] && command -v google-chrome >/dev/null 2>&1; then
  CHROME="$(command -v google-chrome)"
fi

if [ -z "$CHROME" ] && command -v chromium >/dev/null 2>&1; then
  CHROME="$(command -v chromium)"
fi

if [ -z "$CHROME" ] || [ ! -x "$CHROME" ]; then
  log "[whatnot-browser] ERROR: no Chromium executable found"
  exit 1
fi

CHROME_VERSION="$($CHROME --version 2>/dev/null | head -1 || true)"
log "[whatnot-browser] selected browser=${CHROME}${CHROME_VERSION:+ version=${CHROME_VERSION}}"

# Do not start a second Chromium against the shared profile. If a browser exists
# but CDP is unhealthy, surface that condition instead of deleting locks or
# killing a possibly human-owned session.
if browser_owns_profile; then
  log "[whatnot-browser] ERROR: a Chromium process already owns ${PROFILE_DIR}, but CDP ${CDP_URL} is unavailable or unstable"
  exit 1
fi

log "[whatnot-browser] starting Chromium display=${DISPLAY_VALUE} cdp=${CDP_URL} profile=${PROFILE_DIR}"

nohup env DISPLAY="$DISPLAY_VALUE" "$CHROME" \
  --no-sandbox \
  --disable-dev-shm-usage \
  --remote-debugging-address="$CDP_HOST" \
  --remote-debugging-port="$CDP_PORT" \
  --user-data-dir="$PROFILE_DIR" \
  --window-size=1365,900 \
  "$START_URL" \
  >>"$BROWSER_LOG" 2>&1 &
BROWSER_PID=$!

for _ in $(seq 1 40); do
  if ! kill -0 "$BROWSER_PID" 2>/dev/null && ! browser_owns_profile; then
    log "[whatnot-browser] ERROR: Chromium exited during startup; see ${BROWSER_LOG}"
    exit 1
  fi

  if stable_cdp_ready; then
    log "[whatnot-browser] Chromium ready and stable at ${CDP_URL}"
    exit 0
  fi
  sleep 0.5
done

if ! browser_owns_profile; then
  log "[whatnot-browser] ERROR: Chromium exited before CDP became stable; see ${BROWSER_LOG}"
else
  log "[whatnot-browser] ERROR: Chromium did not expose stable CDP within the startup window"
fi
exit 1
