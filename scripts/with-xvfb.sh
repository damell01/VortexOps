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

cleanup() {
    kill "$XVFB_PID" 2>/dev/null || true
    wait "$XVFB_PID" 2>/dev/null || true
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

# No redirection: the command's stdout and stderr stay exactly as it wrote them,
# which is the entire point of this script existing.
set +e
DISPLAY=":${DISPLAY_NUM}" "$@"
STATUS=$?
set -e

exit "$STATUS"
