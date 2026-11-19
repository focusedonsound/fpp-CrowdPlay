<?php
// "Start now" endpoint for the CrowdPlay settings page.
//
// Runs commands/cp_cmd_start.sh DIRECTLY (as the web UI's fpp user) so the
// button works immediately after install - before fppd has restarted and
// registered the "CrowdPlay - Start" FPP Command. That Command still exists for
// scheduling a start before your show; this endpoint just backs the UI button so
// a brand-new install doesn't look broken while the restart banner is pending.
header('Content-Type: application/json');

$script = dirname(__DIR__) . '/commands/cp_cmd_start.sh';   // .../plugins/fpp-CrowdPlay/commands/...
if (!is_file($script)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Start script not found on the player.']);
    exit;
}

$out = [];
$rc = 1;
// cp_cmd_start.sh nohups the poll daemon (output redirected to the plugin log)
// and returns at once; </dev/null keeps PHP's exec from waiting on any fd.
exec('bash ' . escapeshellarg($script) . ' </dev/null 2>&1', $out, $rc);

if ($rc === 0) {
    echo json_encode(['ok' => true, 'message' => 'your control appears in the widget within a few seconds.']);
} else {
    $detail = trim(implode(' ', $out));
    echo json_encode(['ok' => false, 'message' => $detail !== '' ? $detail : 'the start script exited with an error.']);
}
