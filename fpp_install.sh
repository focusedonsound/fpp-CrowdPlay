#!/bin/bash
set -euo pipefail

PLUGIN_NAME="CrowdPlay"
PLUGIN_ID="CrowdPlay"

FPPDIR="${FPPDIR:-}"
SRCDIR="${SRCDIR:-}"
PLUGINDIR="${PLUGINDIR:-}"

MEDIA_DIR="/home/fpp/media"
CFG_DIR="${MEDIA_DIR}/plugindata/fpp-CrowdPlay"
STATE_DIR="${MEDIA_DIR}/plugins/fpp-CrowdPlay/state"
LOG_DIR="${MEDIA_DIR}/logs"
PLUGIN_SELF_DIR="${MEDIA_DIR}/plugins/fpp-CrowdPlay"

log() { echo "[$PLUGIN_ID] $*"; }

ensure_dir() { [[ -d "$1" ]] || mkdir -p "$1"; }

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      *=*)
        key="${1%%=*}"; val="${1#*=}"
        if [[ "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]; then export "$key=$val"; fi
        shift ;;
      *) shift ;;
    esac
  done
}

parse_args "$@"

log "Installing fpp-CrowdPlay..."

ensure_dir "$CFG_DIR"
ensure_dir "$STATE_DIR"
ensure_dir "$LOG_DIR"
chmod 700 "$CFG_DIR" 2>/dev/null || true

# Seed a default config on first install (never overwrite an existing one).
CFG_FILE="${CFG_DIR}/crowdplay.json"
if [[ ! -f "$CFG_FILE" ]]; then
  cat > "$CFG_FILE" <<'JSON'
{
  "relayUrl": "",
  "showId": "",
  "licenseKey": "",
  "fppApi": "http://127.0.0.1/api/command",
  "poll": { "mode": "longpoll", "holdSec": 25 },
  "commands": [],
  "location": { "enabled": false, "lat": null, "lon": null, "radiusM": 300, "toleranceM": 50, "onDenied": "strict" }
}
JSON
  chmod 600 "$CFG_FILE" 2>/dev/null || true
  log "Seeded default config at $CFG_FILE"
fi

# Make scripts executable (fppd runs command scripts via execve).
if [[ -n "$PLUGINDIR" && -d "$PLUGINDIR/fpp-CrowdPlay" ]]; then
  SELF="$PLUGINDIR/fpp-CrowdPlay"
else
  SELF="$PLUGIN_SELF_DIR"
fi
chmod +x "$SELF"/commands/*.sh "$SELF"/scripts/*.sh 2>/dev/null || true

log "fpp-CrowdPlay installed. Open the CrowdPlay page to configure the relay and your commands."
# NOTE: new plugin commands (CrowdPlay - Start/Stop) need an FPPD restart to
# appear in the command list. The Plugin Manager prompts for this.
