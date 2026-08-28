#!/bin/sh
# Run a command against a private X display, without merging its output streams.
#
# The obvious tool for this is xvfb-run, and it cannot be used here: Debian's
# xvfb-run executes the command as `"$@" 2>&1`, folding the child's stderr into
# its stdout. The scraper writes its JSON result to stdout and everything else
# to stderr, so that wrapper simultaneously destroys the payload and hides every
# diagnostic — a failing run then reports an exit code with no explanation,
# which is indistinguishable from the scraper having nothing to say.
#
# Usage: with-xvfb.sh <command> [args...]

set -e

# A channel switch starts by checking whether a Cloudflare interstitial is on
# screen. That check can itself consume the normal 45-second challenge budget.
# Giving the *whole* switch the same 45 seconds meant it timed out before the
# role picker ever got a chance to open. Keep the challenge budget as-is, but
# give the switch enough room to finish after it.
: "${WHATNOT_SWITCH_TIMEOUT_MS:=90000}"
export WHATNOT_SWITCH_TIMEOUT_MS

# Find a display nobody is using. Xvfb takes the lock file, so its absence is
# the check — starting on a taken number fails outright rather than sharing.
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

# Xvfb's own chatter goes nowhere; it is not part of the command's output and
# would otherwise be mistaken for it.
Xvfb ":${DISPLAY_NUM}" -screen 0 1280x1024x24 -nolisten tcp >/dev/null 2>&1 &
XVFB_PID=$!

# Keep a copy of stderr while streaming it unchanged to the caller. This gives
# the wrapper one important safety job: if the scraper explicitly says it gave
# up switching channels, do NOT let a successful exit cause Laravel to import
# whichever channel happened to remain active.
TMP_DIR="$(mktemp -d /tmp/vortex-whatnot-xvfb.XXXXXX)"
STDERR_LOG="${TMP_DIR}/stderr.log"
STDERR_PIPE="${TMP_DIR}/stderr.pipe"
mkfifo "$STDERR_PIPE"

cleanup() {
    kill "$XVFB_PID" 2>/dev/null || true
    wait "$XVFB_PID" 2>/dev/null || true
    rm -rf "$TMP_DIR" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

# Give the server a moment to create its socket; starting the client against a
# display that is not listening yet fails in a way that looks like a browser bug.
i=0
while [ "$i" -lt 50 ]; do
    [ -e "/tmp/.X11-unix/X${DISPLAY_NUM}" ] && break
    i=$((i + 1))
    sleep 0.1
done

# Tee stderr through a FIFO so live progress remains live, stdout remains pure
# JSON, and we can inspect the finished diagnostic stream before returning the
# child's status to PHP.
tee "$STDERR_LOG" < "$STDERR_PIPE" >&2 &
TEE_PID=$!

set +e
DISPLAY=":${DISPLAY_NUM}" "$@" 2> "$STDERR_PIPE"
STATUS=$?
wait "$TEE_PID"
set -e

if grep -Eq 'switchToChannel: gave up after|switchToChannel: WARNING — channel .* not found|Switch Role not found' "$STDERR_LOG"; then
    echo "CHANNEL_SWITCH_FAILED: requested Whatnot channel was not activated; refusing to import data from the currently active channel." >&2
    exit 1
fi

exit "$STATUS"
