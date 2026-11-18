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
chmod 755 "$CFG_DIR" 2>/dev/null || true

# Seed a default config on first install (never overwrite an existing one).
#
# CrowdPlay uses one hosted relay for everyone, so the relay URL is pre-filled
# and the user never has to deploy anything. Each install also gets:
#   - a unique Show ID   (public; appears in the embed URL) so two shows on the
#     shared relay never collide, and
#   - a private Show Key (secret) that proves this player owns the Show ID, so
#     nobody who sees your public Show ID can hijack, drain, or overwrite it.
CFG_FILE="${CFG_DIR}/crowdplay.json"
HOSTED_RELAY="https://crowdplay-relay.nscilingo.workers.dev"
if [[ ! -f "$CFG_FILE" ]]; then
  # Friendly, collision-resistant Show ID: sanitized hostname + random suffix.
  GEN_HOST="$(hostname 2>/dev/null | tr 'A-Z' 'a-z' | tr -cd 'a-z0-9-' | sed 's/^-*//;s/-*$//' | cut -c1-24)"
  [[ -n "$GEN_HOST" ]] || GEN_HOST="show"
  RAND6="$(openssl rand -hex 3 2>/dev/null || head -c3 /dev/urandom | od -An -tx1 | tr -d ' \n')"
  SHOW_KEY="$(openssl rand -hex 16 2>/dev/null || head -c16 /dev/urandom | od -An -tx1 | tr -d ' \n')"
  SHOW_ID="${GEN_HOST}-${RAND6}"

  cat > "$CFG_FILE" <<JSON
{
  "relayUrl": "${HOSTED_RELAY}",
  "showId": "${SHOW_ID}",
  "showKey": "${SHOW_KEY}",
  "licenseKey": "",
  "fppApi": "http://127.0.0.1/api/command",
  "poll": { "mode": "longpoll", "holdSec": 25 },
  "commands": [],
  "location": { "enabled": false, "lat": null, "lon": null, "radiusM": 300, "toleranceM": 50, "onDenied": "strict" },
  "ui": { "onboardingSeen": false, "onboardingTourEnabled": true }
}
JSON
  chmod 600 "$CFG_FILE" 2>/dev/null || true
  log "Seeded default config at $CFG_FILE (showId=${SHOW_ID})"
fi

# Make scripts executable (fppd runs command scripts via execve).
if [[ -n "$PLUGINDIR" && -d "$PLUGINDIR/fpp-CrowdPlay" ]]; then
  SELF="$PLUGINDIR/fpp-CrowdPlay"
else
  SELF="$PLUGIN_SELF_DIR"
fi
chmod +x "$SELF"/commands/*.sh "$SELF"/scripts/*.sh 2>/dev/null || true

# The plugin manager runs this installer as root, but FPP's web UI (PHP) and
# fppd both run as the 'fpp' user - so hand ownership of the data dirs (and the
# seeded config) to fpp, or the settings page can't save and the daemon can't
# read its config.
if id fpp >/dev/null 2>&1; then
  chown -R fpp:fpp "$CFG_DIR" "$STATE_DIR" 2>/dev/null || true
fi

log "fpp-CrowdPlay installed. Open the CrowdPlay page to configure your show and commands."

# fppd only reads commands/descriptions.json at its own startup, so the first
# time this plugin's commands (CrowdPlay - Start/Stop) become known an fppd
# restart is genuinely needed. fpp_install.sh is also the update path, though,
# and most updates don't touch that file - so only request the restart (which
# stops a running show) when descriptions.json actually changed, tracked by a
# hash. Uses FPP's own setSetting restartFlag, not a raw service/host command.
DESC_HASH_FILE="${CFG_DIR}/.descriptions_json_sha256"
DESC_FILE="${SELF}/commands/descriptions.json"
if [[ -f "$DESC_FILE" ]]; then
  NEW_HASH="$(sha256sum "$DESC_FILE" 2>/dev/null | awk '{print $1}')"
  PREV_HASH=""
  [[ -f "$DESC_HASH_FILE" ]] && PREV_HASH="$(cat "$DESC_HASH_FILE" 2>/dev/null || echo "")"
  if [[ -n "$NEW_HASH" && "$NEW_HASH" != "$PREV_HASH" ]]; then
    set +u
    . "${FPPDIR:-/opt/fpp}/scripts/common" 2>/dev/null || true
    set -u
    setSetting restartFlag 1 2>/dev/null || true
    echo "$NEW_HASH" > "$DESC_HASH_FILE" 2>/dev/null || true
    log "commands/descriptions.json changed - requested an fppd restart so CrowdPlay - Start/Stop register"
  fi
  # Installer runs as root; keep the hash file fpp-owned so an fpp-run media
  # backup/rsync of /home/fpp/media doesn't trip on a root-owned file.
  if [[ -f "$DESC_HASH_FILE" ]] && id fpp >/dev/null 2>&1; then
    chown fpp:fpp "$DESC_HASH_FILE" 2>/dev/null || true
  fi
fi
