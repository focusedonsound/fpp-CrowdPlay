<?php
// Button-image preview proxy. FPP's UI sets a strict Content-Security-Policy
// (img-src 'self' blob: data:), so the settings page cannot load the button
// image directly from the relay origin. This fetches it server-side and serves
// it from the plugin's own origin, which CSP allows. Owner-only (admin UI),
// read-only, and the path is validated to the relay's /img/ space so this can't
// be turned into an open proxy.
declare(strict_types=1);
ini_set('display_errors', '0');

$configFile = "/home/fpp/media/plugindata/fpp-CrowdPlay/crowdplay.json";
$cfg = file_exists($configFile) ? json_decode(@file_get_contents($configFile), true) : [];
if (!is_array($cfg)) $cfg = [];
$relay = rtrim((string)($cfg['relayUrl'] ?? 'https://crowdplay-relay.nscilingo.workers.dev'), '/');

// Only a relay-hosted /img/ path, exactly as upload.php returns and save.php
// stores. Anything else is refused so this can't fetch arbitrary URLs.
$src = (string)($_GET['src'] ?? '');
if (!preg_match('#^/img/[A-Za-z0-9._/%-]{1,200}$#', $src)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo "bad path";
  exit;
}

$url  = $relay . $src;
$body = false;
$code = 0;
$ct   = 'application/octet-stream';

if (function_exists('curl_init')) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 12,
  ]);
  $body = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $ctHeader = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  if (is_string($ctHeader) && $ctHeader !== '') $ct = explode(';', $ctHeader)[0];
  curl_close($ch);
} else {
  $ctx = stream_context_create(["http" => ["method" => "GET", "timeout" => 12, "ignore_errors" => true]]);
  $body = @file_get_contents($url, false, $ctx);
  $code = ($body === false) ? 0 : 200;
  // Recover the upstream content-type from the response headers when available.
  if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $h) {
      if (stripos($h, 'content-type:') === 0) { $ct = trim(explode(';', substr($h, 13))[0]); break; }
    }
  }
}

// Only pass through real image types; never echo an unexpected upstream body
// (e.g. a JSON error) as if it were an image.
$allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
if ($body === false || $body === null || $body === '' || $code >= 400 || !in_array($ct, $allowed, true)) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  echo "not found";
  exit;
}

header('Content-Type: ' . $ct);
header('Cache-Control: public, max-age=86400');
echo $body;
