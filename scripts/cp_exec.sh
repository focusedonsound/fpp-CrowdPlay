#!/bin/bash
# Execute one allowlisted CrowdPlay command locally via FPP's command API.
#
# Usage: cp_exec.sh <command-id>
#
# SECURITY: the plugin trusts only its own local allowlist. <command-id>
# must match an entry in crowdplay.json .commands[]; its fppCommand + args
# are read from THAT entry, never from anything the relay sends. The relay
# can only ever name an id you configured - it can never supply a raw FPP
# command.
set -uo pipefail

PLUGIN_DIR="$(dirname "$(dirname "$0")")"
HERE="${PLUGIN_DIR}/scripts"
# shellcheck source=./cp_lib.sh
source "${HERE}/cp_lib.sh"

CMD_ID="${1:-}"
[[ -n "$CMD_ID" ]] || { cp_log "exec: missing command id"; exit 2; }

# Look the id up in the LOCAL allowlist.
ENTRY="$(jq -c --arg id "$CMD_ID" '.commands[]? | select(.id == $id)' "$CP_CFG_FILE" 2>/dev/null)"
if [[ -z "$ENTRY" ]]; then
    cp_log "exec: DENY - id '$CMD_ID' is not in the allowlist"
    exit 1
fi

API="$(cp_local_fpp_api)"

# Resolve what to run. A control can either name a raw FPP command (+args) or a
# saved FPP Command Preset. For a preset we look up its command+args from FPP's
# own commandPresets.json at run time (so edits to the preset are followed) and
# run those - there is no separate "run preset" FPP command.
PRESET="$(jq -r '.preset // empty' <<<"$ENTRY")"
if [[ -n "$PRESET" ]]; then
    PRESET_URL="${API%/command}/configfile/commandPresets.json"
    PRESETS="$(curl -sS --max-time 10 "$PRESET_URL" 2>/dev/null)"
    PRESET_ENTRY="$(jq -c --arg n "$PRESET" 'first(.commands[]? | select(.name == $n)) // empty' <<<"$PRESETS" 2>/dev/null)"
    if [[ -z "$PRESET_ENTRY" ]]; then
        cp_log "exec: id='$CMD_ID' DENY - preset '$PRESET' not found in commandPresets.json"
        exit 1
    fi
    FPP_COMMAND="$(jq -r '.command // empty' <<<"$PRESET_ENTRY")"
    ARGS_JSON="$(jq -c '.args // []' <<<"$PRESET_ENTRY")"
    cp_log "exec: id='$CMD_ID' preset='$PRESET' resolves to cmd='$FPP_COMMAND'"
else
    FPP_COMMAND="$(jq -r '.fppCommand' <<<"$ENTRY")"
    ARGS_JSON="$(jq -c '.args // []' <<<"$ENTRY")"
fi

# Build the FPP command payload: {"command": <name>, "args": [ ... ]}
PAYLOAD="$(jq -cn --arg c "$FPP_COMMAND" --argjson a "$ARGS_JSON" '{command:$c, args:$a}')"

RESP="$(curl -sS --max-time 10 -X POST "$API" -H 'Content-Type: application/json' -d "$PAYLOAD" 2>/dev/null)"
RC=$?
if [[ $RC -ne 0 ]]; then
    cp_log "exec: id='$CMD_ID' cmd='$FPP_COMMAND' FAILED (curl rc=$RC)"
    exit 1
fi
cp_log "exec: id='$CMD_ID' cmd='$FPP_COMMAND' -> ${RESP:-ok}"

# Hold the resource lane for the command's declared duration, then release it.
# (cp_exec.sh is already run as a background job per command by cp_poll.sh, so
# sleeping here only blocks this one command's worker.)
DURATION="$(jq -r '.durationSec // 0' <<<"$ENTRY")"
if [[ "$DURATION" =~ ^[0-9]+$ && "$DURATION" -gt 0 ]]; then
    sleep "$DURATION"
    cp_log "exec: id='$CMD_ID' held ${DURATION}s"
    # TODO: if the command has a paired Stop mapping, issue it here too.
fi
cp_relay_complete "$CMD_ID"
# TODO(early-release): when durationSec==0, optionally poll /api/effects to
# release the lane the moment a self-terminating effect actually ends.
