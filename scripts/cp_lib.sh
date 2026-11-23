#!/bin/bash
# Shared helpers for fpp-CrowdPlay. Source this; don't execute it.
# shellcheck shell=bash

CP_MEDIA_DIR="${MEDIADIR:-/home/fpp/media}"
CP_CFG_DIR="${CP_MEDIA_DIR}/plugindata/fpp-CrowdPlay"
CP_CFG_FILE="${CP_CFG_DIR}/crowdplay.json"
CP_STATE_DIR="${CP_MEDIA_DIR}/plugins/fpp-CrowdPlay/state"
CP_LOG_FILE="${CP_MEDIA_DIR}/logs/plugin-fpp-CrowdPlay.log"

# Path to the persistent, mode-600 auth-header file holding the private show
# key. curl reads the header with `-H @file` instead of taking the key on the
# command line (where `ps` would expose it). This is a SINGLE plugin-owned file
# under the state dir - NOT a per-call /tmp file - so an interrupted curl (e.g.
# the daemon killed mid-poll) can never orphan a secret-bearing file in /tmp.
# It holds no more than crowdplay.json already does (same mode-600, same dir
# tree) and is removed at Stop (cp_cmd_stop.sh) and uninstall (fpp_uninstall.sh).
CP_KEY_HDR="${CP_STATE_DIR}/showkey.hdr"

# Write/refresh the key header file and print its path. Idempotent: callers can
# reuse the returned path for the life of the process without re-creating it.
cp_keyfile() {
    local k="$1" tmp
    mkdir -p "$CP_STATE_DIR" 2>/dev/null || return 1
    # Write to a temp file in the SAME dir, then atomically rename it over the
    # target. A plain `> $CP_KEY_HDR` truncates-then-writes, so a concurrent
    # reader (another cp_exec.sh worker's `curl -H @file`, or the poll daemon)
    # could catch the momentarily-empty file and send a blank auth header.
    # rename(2) within one filesystem is atomic: readers see old or new, never
    # empty. Temp stays in the state dir so the rename isn't a cross-device copy.
    tmp="$(mktemp "${CP_KEY_HDR}.XXXXXX" 2>/dev/null)" || return 1
    ( umask 077; printf 'X-CrowdPlay-Key: %s\n' "$k" > "$tmp" ) || { rm -f "$tmp"; return 1; }
    chmod 600 "$tmp" 2>/dev/null
    mv -f "$tmp" "$CP_KEY_HDR" || { rm -f "$tmp"; return 1; }
    printf '%s' "$CP_KEY_HDR"
}

# Remove the persistent key-header file (called at Stop / uninstall).
cp_keyfile_cleanup() { rm -f "$CP_KEY_HDR" "${CP_KEY_HDR}".* 2>/dev/null || true; }

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
# The relay is hosted for everyone; when the config leaves relayUrl blank (the
# default a fresh install seeds), fall back to the built-in hosted relay.
CP_DEFAULT_RELAY="https://crowdplay-relay.nscilingo.workers.dev"
cp_relay()   { local u; u="$(cp_cfg '.relayUrl')"; [[ -n "$u" ]] || u="$CP_DEFAULT_RELAY"; printf '%s' "${u%/}"; }
cp_show()    { cp_cfg '.showId'; }
cp_license() { cp_cfg '.licenseKey'; }
# Private ownership secret - proves this player owns the (public) Show ID.
# Sent as X-CrowdPlay-Key on register/poll/complete; never in the embed URL.
cp_showkey() { cp_cfg '.showKey'; }

# Register the exposed-command catalog + location config with the relay.
# Sends ONLY what the relay needs to queue/gate/render - never fppCommand/args
# (those stay local; the relay never sees the raw FPP command).
cp_relay_register() {
    local relay show lic skey payload jqerr resp
    relay="$(cp_relay)"; show="$(cp_show)"; lic="$(cp_license)"; skey="$(cp_showkey)"
    [[ -n "$relay" && -n "$show" ]] || { cp_log "register: relayUrl/showId not set"; return 1; }
    # Build the registration payload. IMPORTANT: use explicit "{key: .field}"
    # form - jq 1.6 (shipped with FPP) does NOT accept the "{field}" object
    # shorthand and fails to compile the whole program, producing no output.
    # Capture jq's stderr so a failure says WHY instead of a vague message.
    jqerr="$(mktemp 2>/dev/null)"
    payload="$(jq -c --arg show "$show" --arg lic "$lic" \
        '{show: $show,
          licenseKey: $lic,
          location: (.location // null),
          turnstileRequired: (.turnstileRequired // false),
          style: (.widget // null),
          commands: [ .commands[]? | {
              id: .id,
              label: .label,
              lane: .lane,
              cooldownSec: .cooldownSec,
              durationSec: .durationSec,
              publicSafe: .publicSafe,
              image: .image
          } ]}' \
        "$CP_CFG_FILE" 2>"${jqerr:-/dev/null}")"
    if [[ -z "$payload" ]]; then
        cp_log "register: could not build payload${jqerr:+ ($(tr '\n' ' ' < "$jqerr" 2>/dev/null))}"
        [[ -n "$jqerr" ]] && rm -f "$jqerr"
        return 1
    fi
    [[ -n "$jqerr" ]] && rm -f "$jqerr"
    local hdr; hdr="$(cp_keyfile "$skey")"
    resp="$(curl -sS --max-time 15 -X POST "${relay}/api/register" \
        -H 'Content-Type: application/json' -H @"$hdr" \
        -d "$payload" 2>/dev/null)"
    cp_log "register: -> ${resp:-<no response>}"
    # Report whether the relay actually accepted the config, so callers (e.g.
    # save.php) can tell the user the settings synced vs. need a retry. The
    # long-poll daemon tolerates a non-zero return and keeps polling regardless.
    if [[ -z "$resp" ]]; then
        cp_log "register: no response from relay ${relay} (unreachable?)"
        return 1
    fi
    case "$resp" in
        *show-taken*)
            cp_log "register: SHOW ID TAKEN - another player already claimed '${show}'. Pick a different Show name in the CrowdPlay settings."
            return 1 ;;
    esac
    case "$resp" in
        *'"ok":true'*|*'"ok": true'*) return 0 ;;
        *) cp_log "register: relay rejected the config"; return 1 ;;
    esac
}

# Report a finished command so the relay releases its resource lane.
cp_relay_complete() {
    local id="$1" relay show skey
    relay="$(cp_relay)"; show="$(cp_show)"; skey="$(cp_showkey)"
    [[ -n "$relay" && -n "$show" ]] || return 0
    local hdr; hdr="$(cp_keyfile "$skey")"
    curl -sS --max-time 10 -X POST "${relay}/api/complete" \
        -H 'Content-Type: application/json' -H @"$hdr" \
        -d "$(jq -cn --arg s "$show" --arg i "$id" '{show:$s,id:$i}')" >/dev/null 2>&1 || true
}
