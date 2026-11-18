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

# The CrowdPlay - Start/Stop commands are defined in commands/descriptions.json,
# which fppd only reads at its own startup. This script is the only code that runs
# before the plugin directory is deleted, so ask fppd to restart (FPP's own
# mechanism, not a raw service/host command) so the now-gone commands stop
# lingering in the command list.
( set +u; . "${FPPDIR:-/opt/fpp}/scripts/common" 2>/dev/null && setSetting restartFlag 1 ) 2>/dev/null || true

# Leave plugindata/fpp-CrowdPlay/ in place so reinstalling keeps settings.
log "fpp-CrowdPlay uninstalled. Your settings in plugindata/fpp-CrowdPlay/ were left intact."
