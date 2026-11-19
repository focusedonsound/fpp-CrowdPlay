<?php
// "Stop" endpoint for the CrowdPlay settings page - runs commands/cp_cmd_stop.sh
// directly, so the button works even before fppd has registered the FPP Commands.
// (See start.php for the full rationale.)
header('Content-Type: application/json');

$script = dirname(__DIR__) . '/commands/cp_cmd_stop.sh';
if (!is_file($script)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Stop script not found on the player.']);
    exit;
}

$out = [];
$rc = 1;
exec('bash ' . escapeshellarg($script) . ' </dev/null 2>&1', $out, $rc);

if ($rc === 0) {
    echo json_encode(['ok' => true, 'message' => 'poll daemon stopped.']);
} else {
    $detail = trim(implode(' ', $out));
    echo json_encode(['ok' => false, 'message' => $detail !== '' ? $detail : 'the stop script exited with an error.']);
}
