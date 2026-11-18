#!/bin/bash
# Shared helpers for fpp-CrowdPlay. Source this; don't execute it.
# shellcheck shell=bash

CP_PLUGIN_ID="CrowdPlay"
CP_MEDIA_DIR="${MEDIADIR:-/home/fpp/media}"
CP_CFG_DIR="${CP_MEDIA_DIR}/plugindata/fpp-CrowdPlay"
CP_CFG_FILE="${CP_CFG_DIR}/crowdplay.json"
CP_STATE_DIR="${CP_MEDIA_DIR}/plugins/fpp-CrowdPlay/state"
CP_LOG_FILE="${CP_MEDIA_DIR}/logs/plugin-fpp-CrowdPlay.log"

cp_ts()  { date '+%Y-%m-%d %H:%M:%S'; }
cp_log() { echo "[$(cp_ts)] [fpp-CrowdPlay] $*" >> "$CP_LOG_FILE"; }

cp_ensure_dirs() {
    mkdir -p "$CP_CFG_DIR" "$CP_STATE_DIR" "${CP_MEDIA_DIR}/logs" 2>/dev/null || true
}

# cp_cfg <jq-filter> [default] -> value from crowdplay.json
cp_cfg() {
    local filter="$1" def="${2:-}"
    [[ -f "$CP_CFG_FILE" ]] || { printf '%s' "$def"; return; }
    local v
    v="$(jq -r "$filter // empty" "$CP_CFG_FILE" 2>/dev/null)"
    [[ -n "$v" ]] && printf '%s' "$v" || printf '%s' "$def"
}

# cp_local_fpp_api -> the local FPP command endpoint
cp_local_fpp_api() { cp_cfg '.fppApi' 'http://127.0.0.1/api/command'; }

# Relay coordinates.
cp_relay()   { local u; u="$(cp_cfg '.relayUrl')"; printf '%s' "${u%/}"; }
cp_show()    { cp_cfg '.showId'; }
cp_license() { cp_cfg '.licenseKey'; }
# Private ownership secret - proves this player owns the (public) Show ID.
# Sent as X-CrowdPlay-Key on register/poll/complete; never in the embed URL.
cp_showkey() { cp_cfg '.showKey'; }

# Register the exposed-command catalog + location config with the relay.
# Sends ONLY what the relay needs to queue/gate/render - never fppCommand/args
# (those stay local; the relay never sees the raw FPP command).
cp_relay_register() {
    local relay show lic skey payload resp
    relay="$(cp_relay)"; show="$(cp_show)"; lic="$(cp_license)"; skey="$(cp_showkey)"
    [[ -n "$relay" && -n "$show" ]] || { cp_log "register: relayUrl/showId not set"; return 1; }
    payload="$(jq -c --arg show "$show" --arg lic "$lic" \
        '{show:$show, licenseKey:$lic,
          location: (.location // null),
          style: (.widget // null),
          commands: [ .commands[]? | {id,label,lane,cooldownSec,durationSec,publicSafe} ]}' \
        "$CP_CFG_FILE" 2>/dev/null)"
    [[ -n "$payload" ]] || { cp_log "register: could not build payload"; return 1; }
    resp="$(curl -sS --max-time 15 -X POST "${relay}/api/register" \
        -H 'Content-Type: application/json' -H "X-CrowdPlay-Key: ${skey}" \
        -d "$payload" 2>/dev/null)"
    cp_log "register: -> ${resp:-<no response>}"
    case "$resp" in
        *show-taken*) cp_log "register: SHOW ID TAKEN - another player already claimed '${show}'. Pick a different Show name in the CrowdPlay settings." ;;
    esac
}

# Report a finished command so the relay releases its resource lane.
cp_relay_complete() {
    local id="$1" relay show skey
    relay="$(cp_relay)"; show="$(cp_show)"; skey="$(cp_showkey)"
    [[ -n "$relay" && -n "$show" ]] || return 0
    curl -sS --max-time 10 -X POST "${relay}/api/complete" \
        -H 'Content-Type: application/json' -H "X-CrowdPlay-Key: ${skey}" \
        -d "$(jq -cn --arg s "$show" --arg i "$id" '{show:$s,id:$i}')" >/dev/null 2>&1 || true
}
