<?php
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// FPP has no login by default, so a state-changing action must never run on
// a GET - a plain <img>/<iframe> on any page an operator has open would
// otherwise trigger it. This save rewrites the exposed-command allowlist and
// relay credentials, so it is POST-only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["status" => "ERROR", "message" => "POST required"]);
  exit;
}

$configFile = "/home/fpp/media/plugindata/fpp-CrowdPlay/crowdplay.json";
require_once __DIR__ . "/cp_config_lock.php";

function cpRespond($ok, $msg, $extra = []) {
  echo json_encode(array_merge(["status" => $ok ? "OK" : "ERROR", "message" => $msg], $extra));
  exit;
}

// Strip CR/LF from any scalar that gets written back out or forwarded to the
// relay, so a value can never splice a new line into a generated request.
function cpClean($s) { return str_replace(["\r", "\n"], "", (string)$s); }

function cpDefaultConfig() {
  return [
    "relayUrl" => "",
    "showId" => "",
    "licenseKey" => "",
    "fppApi" => "http://127.0.0.1/api/command",
    "poll" => ["mode" => "longpoll", "holdSec" => 25],
    "commands" => [],
    "location" => ["enabled" => false, "lat" => null, "lon" => null,
                   "radiusM" => 300, "toleranceM" => 50, "onDenied" => "strict"],
    "ui" => ["onboardingSeen" => false, "onboardingTourEnabled" => true],
  ];
}

$dir = dirname($configFile);
if (!is_dir($dir))       cpRespond(false, "Config directory missing: $dir");
if (!is_writable($dir))  cpRespond(false, "Config directory not writable: $dir");

// Held across the whole read-merge-write below.
$lock = cpConfigLockAuto($configFile);

$cfg = cpDefaultConfig();
if (file_exists($configFile)) {
  $j = json_decode(@file_get_contents($configFile), true);
  if (is_array($j)) $cfg = array_replace_recursive($cfg, $j);
}

// ---- Relay credentials ----
if (isset($_POST['relayUrl']))   $cfg['relayUrl']   = cpClean(trim($_POST['relayUrl']));
if (isset($_POST['showId']))     $cfg['showId']     = cpClean(trim($_POST['showId']));
if (isset($_POST['licenseKey'])) $cfg['licenseKey'] = cpClean(trim($_POST['licenseKey']));
if (isset($_POST['fppApi']) && trim($_POST['fppApi']) !== '')
  $cfg['fppApi'] = cpClean(trim($_POST['fppApi']));
if (isset($_POST['holdSec'])) {
  $h = (int)$_POST['holdSec'];
  $cfg['poll']['holdSec'] = ($h >= 1 && $h <= 300) ? $h : 25;
}

// ---- Location gate ----
$cfg['location']['enabled']  = !empty($_POST['locEnabled']);
if (isset($_POST['locLat']) && $_POST['locLat'] !== '') $cfg['location']['lat'] = (float)$_POST['locLat'];
if (isset($_POST['locLon']) && $_POST['locLon'] !== '') $cfg['location']['lon'] = (float)$_POST['locLon'];
if (isset($_POST['locRadius']))    $cfg['location']['radiusM']    = max(0, (int)$_POST['locRadius']);
if (isset($_POST['locTolerance'])) $cfg['location']['toleranceM'] = max(0, (int)$_POST['locTolerance']);
if (isset($_POST['locOnDenied']))
  $cfg['location']['onDenied'] = ($_POST['locOnDenied'] === 'lenient') ? 'lenient' : 'strict';

// ---- Command allowlist ----
// The UI serializes the editor rows into a single JSON field.
if (isset($_POST['commandsJson'])) {
  $cmds = json_decode($_POST['commandsJson'], true);
  if (!is_array($cmds)) cpRespond(false, "Commands payload was not valid JSON.");

  $clean = [];
  $seen = [];
  foreach ($cmds as $c) {
    if (!is_array($c)) continue;

    $id = cpClean(trim($c['id'] ?? ''));
    if ($id === '') $id = preg_replace('/[^a-z0-9]+/', '-', strtolower(cpClean($c['label'] ?? '')));
    $id = trim($id, '-');
    if ($id === '') cpRespond(false, "Every command needs an id or a label.");
    if (isset($seen[$id])) cpRespond(false, "Duplicate command id: '$id'.");
    $seen[$id] = true;

    $fppCommand = cpClean(trim($c['fppCommand'] ?? ''));
    if ($fppCommand === '') cpRespond(false, "Command '$id' has no FPP command selected.");

    // Args: accept an array, or a JSON-array string typed in the UI.
    $args = $c['args'] ?? [];
    if (is_string($args)) {
      $args = trim($args);
      if ($args === '') $args = [];
      else {
        $decoded = json_decode($args, true);
        if (!is_array($decoded)) cpRespond(false, "Command '$id' args must be a JSON array, e.g. [\"wave.fseq\", false, true, true].");
        $args = $decoded;
      }
    }
    if (!is_array($args)) $args = [];

    $mode = (($c['mode'] ?? 'overlay') === 'interrupt') ? 'interrupt' : 'overlay';

    $clean[] = [
      "id"          => $id,
      "label"       => cpClean(trim($c['label'] ?? $id)),
      "fppCommand"  => $fppCommand,
      "args"        => $args,
      "mode"        => $mode,
      "lane"        => cpClean(trim($c['lane'] ?? 'default')) ?: 'default',
      "cooldownSec" => max(0, (int)($c['cooldownSec'] ?? 0)),
      "durationSec" => max(0, (int)($c['durationSec'] ?? 0)),
      "publicSafe"  => !empty($c['publicSafe']),
    ];
  }
  $cfg['commands'] = $clean;
}

// ---- Atomic write ----
$tmp = $configFile . ".tmp";
$ok = @file_put_contents($tmp, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
if ($ok === false) cpRespond(false, "Could not write config.");
@chmod($tmp, 0600);
if (!@rename($tmp, $configFile)) { @unlink($tmp); cpRespond(false, "Could not save config."); }

cpRespond(true, "Saved.", ["commands" => count($cfg['commands'])]);
