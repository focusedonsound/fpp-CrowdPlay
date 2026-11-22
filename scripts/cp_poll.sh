#!/bin/bash
# CrowdPlay outbound poll daemon.
#
# On startup it registers this show's command catalog + location config with the
# relay (cp_relay_register), then holds an outbound long-poll to /api/poll,
# receives queued command ids, and dispatches each to cp_exec.sh (which re-checks
# the local allowlist). The player never accepts an inbound connection.
set -uo pipefail

PLUGIN_DIR="$(dirname "$(dirname "$0")")"
HERE="${PLUGIN_DIR}/scripts"
# shellcheck source=./cp_lib.sh
source "${HERE}/cp_lib.sh"
cp_ensure_dirs

RELAY_URL="$(cp_relay)"
SHOW_ID="$(cp_cfg '.showId')"
SHOW_KEY="$(cp_cfg '.showKey')"
HOLD_SEC="$(cp_cfg '.poll.holdSec' '25')"

if [[ -z "$RELAY_URL" || -z "$SHOW_ID" ]]; then
    cp_log "poll: relayUrl/showId not configured - stopping"
    exit 1
fi

RUNNING=1
trap 'RUNNING=0; cp_log "poll: shutdown requested"' TERM INT

cp_log "poll: starting (relay=$RELAY_URL show=$SHOW_ID hold=${HOLD_SEC}s)"
# Push the current command catalog + location config to the relay. Re-run the
# CrowdPlay - Start command after editing settings to re-register.
cp_relay_register || cp_log "poll: initial register failed (will still poll)"
BACKOFF=1

# Write the private-key auth header once (persistent, mode-600, plugin-owned).
# Reused for every poll; removed when the daemon exits (and at Stop/uninstall).
CP_HDR="$(cp_keyfile "$SHOW_KEY")"

while [[ $RUNNING -eq 1 ]]; do
    # Long-poll for due command ids. Authenticates with the private Show Key
    # (X-CrowdPlay-Key); the relay returns a JSON array like ["wave","snow"],
    # or [] on timeout. A 403 {"error":"show-taken"} means another player
    # claimed this Show ID - handled below.
    RESP="$(curl -sS --max-time "$((HOLD_SEC + 10))" \
                 -H "X-CrowdPlay-Show: ${SHOW_ID}" \
                 -H @"$CP_HDR" \
                 "${RELAY_URL%/}/api/poll?show=${SHOW_ID}&hold=${HOLD_SEC}" 2>/dev/null)" || RESP=""

    case "$RESP" in
        *show-taken*)
            cp_log "poll: Show ID '${SHOW_ID}' is owned by another player - check the Show name in CrowdPlay settings. Backing off."
            sleep 30; continue ;;
    esac

    if [[ -z "$RESP" ]]; then
        # timeout / transient - re-poll with capped backoff
        sleep "$BACKOFF"
        BACKOFF=$(( BACKOFF < 30 ? BACKOFF * 2 : 30 ))
        continue
    fi
    BACKOFF=1

    # Dispatch each returned command id through the local executor.
    while IFS= read -r CMD_ID; do
        [[ -n "$CMD_ID" ]] || continue
        bash "${HERE}/cp_exec.sh" "$CMD_ID" &
    done < <(jq -r '.[]? // empty' <<<"$RESP" 2>/dev/null)
done

# Graceful shutdown (SIGTERM/SIGINT): drop the key-header file. A SIGKILL would
# skip this, so cp_cmd_stop.sh / fpp_uninstall.sh remove it too (belt & braces).
cp_keyfile_cleanup
cp_log "poll: stopped"
