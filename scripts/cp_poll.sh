#!/bin/bash
# CrowdPlay outbound poll daemon.
#
# Holds an outbound long-poll to the relay, receives queued command ids,
# and dispatches each to cp_exec.sh (which enforces the local allowlist).
# The player never accepts an inbound connection.
#
# NOTE: the relay API contract is not finalized yet - the request/response
# shape below is a placeholder marked TODO(relay). The local half (config,
# allowlist enforcement, execution, backoff, clean shutdown) is real.
set -uo pipefail

PLUGIN_DIR="$(dirname "$(dirname "$0")")"
HERE="${PLUGIN_DIR}/scripts"
# shellcheck source=./cp_lib.sh
source "${HERE}/cp_lib.sh"
cp_ensure_dirs

RELAY_URL="$(cp_cfg '.relayUrl')"
SHOW_ID="$(cp_cfg '.showId')"
LICENSE_KEY="$(cp_cfg '.licenseKey')"
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

while [[ $RUNNING -eq 1 ]]; do
    # TODO(relay): replace with the real long-poll endpoint + auth (HMAC of
    # showId+timestamp, or a signed poll token). Expected response: a JSON
    # array of due command ids, e.g. ["wave","snow"], or [] on timeout.
    RESP="$(curl -sS --max-time "$((HOLD_SEC + 10))" \
                 -H "X-CrowdPlay-Show: ${SHOW_ID}" \
                 -H "X-CrowdPlay-Key: ${LICENSE_KEY}" \
                 "${RELAY_URL%/}/api/poll?show=${SHOW_ID}&hold=${HOLD_SEC}" 2>/dev/null)" || RESP=""

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

cp_log "poll: stopped"
