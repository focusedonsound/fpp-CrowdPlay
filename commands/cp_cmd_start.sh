#!/bin/bash
# FPP Command: CrowdPlay - Start
# Starts the outbound poll daemon (cp_poll.sh) if it is not already running.
set -uo pipefail

PLUGIN_DIR="$(dirname "$(dirname "$0")")"
HERE="${PLUGIN_DIR}/scripts"
# shellcheck source=../scripts/cp_lib.sh
source "${HERE}/cp_lib.sh"

cp_ensure_dirs
PID_FILE="${CP_STATE_DIR}/poll.pid"

if [[ -f "$PID_FILE" ]] && kill -0 "$(cat "$PID_FILE" 2>/dev/null)" 2>/dev/null; then
    cp_log "Start: poll daemon already running pid=$(cat "$PID_FILE")"
    exit 0
fi

if [[ ! -f "$CP_CFG_FILE" ]]; then
    cp_log "ERROR: config not found ($CP_CFG_FILE) - configure CrowdPlay first"
    exit 1
fi

nohup bash "${HERE}/cp_poll.sh" >> "$CP_LOG_FILE" 2>&1 &
echo $! > "$PID_FILE"
cp_log "Start: poll daemon started pid=$(cat "$PID_FILE")"
