<?php
// Per-control button image upload (premium). The browser POSTs the raw image
// bytes here; this proxy adds the show's PRIVATE Show Key + license key (read
// from the saved config, never exposed to the browser) and forwards to the
// relay's /api/upload. The relay enforces premium + ownership and stores the
// image in R2, returning its /img/<key> path. We pass the relay's JSON straight
// back to the page.
header('Content-Type: application/json');

// State-changing + carries the private Show Key, so POST-only (see start.php /
// save.php for the CSRF rationale - a GET <img> must never trigger this).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required']);
    exit;
}

$configFile = "/home/fpp/media/plugindata/fpp-CrowdPlay/crowdplay.json";
$cfg = is_file($configFile) ? json_decode(@file_get_contents($configFile), true) : [];
if (!is_array($cfg)) $cfg = [];

$relay   = $cfg['relayUrl'] ?? '';
if ($relay === '') $relay = 'https://crowdplay-relay.nscilingo.workers.dev';
$show    = $cfg['showId']     ?? '';
$license = $cfg['licenseKey'] ?? '';
$showKey = $cfg['showKey']    ?? '';

if ($show === '' || $showKey === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Save your Show name and run Start once before adding images.']);
    exit;
}
if ($license === '') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Custom button images need a license key.']);
    exit;
}

$ct = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
$allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
if (!in_array($ct, $allowed, true)) {
    http_response_code(415);
    echo json_encode(['ok' => false, 'message' => 'Use a PNG, JPG, WEBP or GIF image.']);
    exit;
}

$data = file_get_contents('php://input');
if ($data === false || strlen($data) === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'No image received.']);
    exit;
}
if (strlen($data) > 512 * 1024) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'message' => 'Image too large (max 512 KB).']);
    exit;
}

$url = rtrim($relay, '/') . '/api/upload?show=' . rawurlencode($show);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: ' . $ct,
        'X-CrowdPlay-Key: ' . $showKey,
        'X-CrowdPlay-License: ' . $license,
    ],
]);
$resp = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false || $resp === '') {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'Could not reach the relay' . ($err !== '' ? ": $err" : '.')]);
    exit;
}
http_response_code($code ?: 200);
echo $resp;   // relay JSON: {ok,key,url} or {error,message,...}
