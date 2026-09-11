#!/bin/sh
# Run a command against a private X display, without merging its output streams.
# Usage: with-xvfb.sh <command> [args...]
#
# The wrapped command is started in its own process group.  This is important for
# the Whatnot stack because the chain is normally:
#   PHP -> this wrapper -> node -> flock -> python -> Chromium
# If PHP/Node is interrupted, descendants such as Python must not survive and
# continue holding storage/whatnot-browser.lock.

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
CHILD_PID=""
CHILD_PGID=""

process_group_alive() {
    [ -n "$CHILD_PGID" ] || return 1
    /bin/kill -0 -- "-$CHILD_PGID" 2>/dev/null
}

terminate_child_tree() {
    # setsid makes CHILD_PID the process-group leader.  Kill the group rather
    # than only the immediate Node child so flock/python/Chromium cannot become
    # orphaned lock holders.
    if [ -n "$CHILD_PGID" ] && process_group_alive; then
        /bin/kill -TERM -- "-$CHILD_PGID" 2>/dev/null || true

        i=0
        while [ "$i" -lt 30 ] && process_group_alive; do
            i=$((i + 1))
            sleep 0.1
        done

        if process_group_alive; then
            /bin/kill -KILL -- "-$CHILD_PGID" 2>/dev/null || true
        fi
    elif [ -n "$CHILD_PID" ] && kill -0 "$CHILD_PID" 2>/dev/null; then
        # Fallback for systems without setsid. This cannot guarantee descendant
        # cleanup, but still forwards termination to the direct child.
        kill -TERM "$CHILD_PID" 2>/dev/null || true
        sleep 0.5
        kill -KILL "$CHILD_PID" 2>/dev/null || true
    fi

    if [ -n "$CHILD_PID" ]; then
        wait "$CHILD_PID" 2>/dev/null || true
    fi
}

cleanup() {
    # Avoid recursively running signal traps while cleanup is in progress.
    trap - INT TERM HUP

    terminate_child_tree

    if [ -n "$TEE_PID" ]; then
        kill "$TEE_PID" 2>/dev/null || true
        wait "$TEE_PID" 2>/dev/null || true
    fi

    kill "$XVFB_PID" 2>/dev/null || true
    wait "$XVFB_PID" 2>/dev/null || true
    rm -rf "$TMP_DIR" 2>/dev/null || true
}

handle_signal() {
    SIGNAL="$1"
    trap - INT TERM HUP
    terminate_child_tree

    case "$SIGNAL" in
        INT)  exit 130 ;;
        TERM) exit 143 ;;
        HUP)  exit 129 ;;
        *)    exit 1 ;;
    esac
}

trap cleanup EXIT
trap 'handle_signal INT' INT
trap 'handle_signal TERM' TERM
trap 'handle_signal HUP' HUP

i=0
while [ "$i" -lt 50 ]; do
    [ -e "/tmp/.X11-unix/X${DISPLAY_NUM}" ] && break
    i=$((i + 1))
    sleep 0.1
done

tee "$STDERR_LOG" < "$STDERR_PIPE" >&2 &
TEE_PID=$!

set +e
if command -v setsid >/dev/null 2>&1; then
    DISPLAY=":${DISPLAY_NUM}" setsid "$@" 2> "$STDERR_PIPE" &
    CHILD_PID=$!
    CHILD_PGID=$CHILD_PID
else
    echo "with-xvfb: warning: setsid unavailable; descendant cleanup is best-effort" >&2
    DISPLAY=":${DISPLAY_NUM}" "$@" 2> "$STDERR_PIPE" &
    CHILD_PID=$!
fi

wait "$CHILD_PID"
STATUS=$?

# Even on a normal direct-child exit, terminate anything left in its process
# group. This closes the exact failure mode where node/flock exits but Python
# remains alive and keeps the browser lock's file descriptor open.
terminate_child_tree
CHILD_PID=""
CHILD_PGID=""

wait "$TEE_PID" 2>/dev/null || true
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
