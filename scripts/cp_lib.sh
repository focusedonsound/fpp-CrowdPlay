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
