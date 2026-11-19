#!/bin/bash
# One-shot: push the current CrowdPlay config (exposed commands + location gate
# + widget style) to the relay. save.php runs this right after writing settings
# so edits apply immediately, without a CrowdPlay Stop/Start to re-register.
# Prints REGISTER_OK on success (relay accepted) so the caller can confirm the
# sync, or REGISTER_FAIL otherwise. Never fails the caller - best effort.
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
. "$DIR/cp_lib.sh"
cp_ensure_dirs
if cp_relay_register; then
    echo "REGISTER_OK"
else
    echo "REGISTER_FAIL"
fi
