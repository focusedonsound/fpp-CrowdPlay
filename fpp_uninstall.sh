#!/bin/bash
set -uo pipefail

PLUGIN_ID="CrowdPlay"
MEDIA_DIR="/home/fpp/media"
STATE_DIR="${MEDIA_DIR}/plugins/fpp-CrowdPlay/state"

log() { echo "[$PLUGIN_ID] $*"; }

# Stop the poll daemon if running.
PID_FILE="${STATE_DIR}/poll.pid"
if [[ -f "$PID_FILE" ]]; then
  PID="$(cat "$PID_FILE" 2>/dev/null)"
  [[ -n "$PID" ]] && kill "$PID" 2>/dev/null || true
  rm -f "$PID_FILE"
  log "Stopped poll daemon."
fi

# Leave plugindata/fpp-CrowdPlay/ in place so reinstalling keeps settings.
log "fpp-CrowdPlay uninstalled. Your settings in plugindata/fpp-CrowdPlay/ were left intact."
