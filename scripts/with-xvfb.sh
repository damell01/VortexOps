#!/bin/sh
# Run a command against a private X display, without merging its output streams.
# Usage: with-xvfb.sh <command> [args...]

set -e

: "${WHATNOT_SWITCH_TIMEOUT_MS:=90000}"
export WHATNOT_SWITCH_TIMEOUT_MS

DISPLAY_NUM=""
n=99
while [ "$n" -lt 200 ]; do
    if [ ! -e "/tmp/.X${n}-lock" ]; then
        DISPLAY_NUM="$n"
        break
    fi
    n=$((n + 1))
done

if [ -z "$DISPLAY_NUM" ]; then
    echo "with-xvfb: no free X display between :99 and :199" >&2
    exit 1
fi

Xvfb ":${DISPLAY_NUM}" -screen 0 1280x1024x24 -nolisten tcp >/dev/null 2>&1 &
XVFB_PID=$!

TMP_DIR="$(mktemp -d /tmp/vortex-whatnot-xvfb.XXXXXX)"
STDERR_LOG="${TMP_DIR}/stderr.log"
STDERR_PIPE="${TMP_DIR}/stderr.pipe"
: > "$STDERR_LOG"
mkfifo "$STDERR_PIPE"
TEE_PID=""

cleanup() {
    if [ -n "$TEE_PID" ]; then
        kill "$TEE_PID" 2>/dev/null || true
        wait "$TEE_PID" 2>/dev/null || true
    fi
    kill "$XVFB_PID" 2>/dev/null || true
    wait "$XVFB_PID" 2>/dev/null || true
    rm -rf "$TMP_DIR" 2>/dev/null || true
}
trap cleanup EXIT INT TERM HUP

i=0
while [ "$i" -lt 50 ]; do
    [ -e "/tmp/.X11-unix/X${DISPLAY_NUM}" ] && break
    i=$((i + 1))
    sleep 0.1
done

tee "$STDERR_LOG" < "$STDERR_PIPE" >&2 &
TEE_PID=$!

set +e
DISPLAY=":${DISPLAY_NUM}" "$@" 2> "$STDERR_PIPE"
STATUS=$?
wait "$TEE_PID"
TEE_PID=""
set -e

# The log is created before the child starts, so an interrupt/cleanup cannot
# leave the post-run grep pointing at a file that never existed. Test the file
# again anyway because cleanup from an external signal may have raced us.
if [ -f "$STDERR_LOG" ] && grep -Eq 'switchToChannel: gave up after|switchToChannel: WARNING — channel .* not found|Switch Role not found' "$STDERR_LOG"; then
    echo "CHANNEL_SWITCH_FAILED: requested Whatnot channel was not activated; refusing to import data from the currently active channel." >&2
    exit 1
fi

exit "$STATUS"
