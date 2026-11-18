<?php
// Analytics proxy. Reads per-show press counts from the relay on behalf of the
// settings page, using the private Show Key server-side so it never reaches the
// browser (and so the cross-origin call is made from here, not the page).
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configFile = "/home/fpp/media/plugindata/fpp-CrowdPlay/crowdplay.json";
$cfg = file_exists($configFile) ? json_decode(@file_get_contents($configFile), true) : [];
if (!is_array($cfg)) $cfg = [];

$relay = rtrim((string)($cfg['relayUrl'] ?? ''), '/');
$show  = (string)($cfg['showId'] ?? '');
$key   = (string)($cfg['showKey'] ?? '');
if ($relay === '' || $show === '') {
  echo json_encode(["error" => "not-configured"]);
  exit;
}

$url = $relay . "/api/show/" . rawurlencode($show) . "/stats";
$body = false;
$code = 0;

if (function_exists('curl_init')) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => ["X-CrowdPlay-Key: " . $key],
  ]);
  $body = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
} else {
  $ctx = stream_context_create(["http" => [
    "method" => "GET",
    "header" => "X-CrowdPlay-Key: " . $key . "\r\n",
    "timeout" => 12,
    "ignore_errors" => true,
  ]]);
  $body = @file_get_contents($url, false, $ctx);
  if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $code = (int)$m[1];
}

if ($body === false || $body === null || $body === '') {
  echo json_encode(["error" => "relay-unreachable"]);
  exit;
}
http_response_code($code ?: 200);
echo $body; // already JSON from the relay
