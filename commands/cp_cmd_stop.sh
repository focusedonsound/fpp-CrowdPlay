#!/bin/bash
# FPP Command: CrowdPlay - Stop
# Stops the outbound poll daemon.
set -uo pipefail

PLUGIN_DIR="$(dirname "$(dirname "$0")")"
HERE="${PLUGIN_DIR}/scripts"
# shellcheck source=../scripts/cp_lib.sh
source "${HERE}/cp_lib.sh"

cp_ensure_dirs
PID_FILE="${CP_STATE_DIR}/poll.pid"

if [[ -f "$PID_FILE" ]]; then
    PID="$(cat "$PID_FILE" 2>/dev/null)"
    if [[ -n "$PID" ]] && kill -0 "$PID" 2>/dev/null; then
        kill "$PID" 2>/dev/null || true
        cp_log "Stop: signalled poll daemon pid=$PID"
    fi
    rm -f "$PID_FILE"
else
    cp_log "Stop: no poll daemon running"
fi

# Remove the persistent key-header file (in case the daemon was SIGKILLed and
# its own graceful cleanup never ran).
cp_keyfile_cleanup
