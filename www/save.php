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
    "relayUrl" => "https://crowdplay-relay.nscilingo.workers.dev",
    "upgradeUrl" => "",
    "showId" => "",
    "showKey" => "",
    "licenseKey" => "",
    "fppApi" => "http://127.0.0.1/api/command",
    "poll" => ["mode" => "longpoll", "holdSec" => 25],
    "commands" => [],
    "location" => ["enabled" => false, "lat" => null, "lon" => null,
                   "radiusM" => 300, "toleranceM" => 50, "onDenied" => "strict"],
    "widget" => ["buttonColor" => "", "textColor" => "", "headline" => ""],
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

// ---- Widget appearance (premium; relay ignores it on the free tier) ----
// Validate here too so the stored config stays clean even before the relay
// sees it: colors must be hex, headline is short and strip-tagged.
$hexColor = function ($v) {
  $v = trim((string)$v);
  return preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : "";
};
if (isset($_POST['wgButtonColor'])) $cfg['widget']['buttonColor'] = $hexColor($_POST['wgButtonColor']);
if (isset($_POST['wgTextColor']))   $cfg['widget']['textColor']   = $hexColor($_POST['wgTextColor']);
if (isset($_POST['wgHeadline']))
  $cfg['widget']['headline'] = substr(cpClean(trim(str_replace(['<', '>'], '', $_POST['wgHeadline']))), 0, 60);

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

    // A control runs EITHER a saved FPP Command Preset (by name) or a raw
    // FPP command + args. A preset carries its own args, so none are needed.
    $preset     = cpClean(trim($c['preset'] ?? ''));
    $fppCommand = cpClean(trim($c['fppCommand'] ?? ''));
    if ($preset === '' && $fppCommand === '')
      cpRespond(false, "Command '$id' has no FPP command or preset selected.");

    // Args: accept an array, or a JSON-array string typed in the UI. Only a
    // raw command uses them - a preset carries its own.
    $args = $c['args'] ?? [];
    if ($preset !== '') {
      $args = [];
    } else {
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
    }

    $mode = (($c['mode'] ?? 'overlay') === 'interrupt') ? 'interrupt' : 'overlay';

    $clean[] = [
      "id"          => $id,
      "label"       => cpClean(trim($c['label'] ?? $id)),
      "preset"      => $preset,
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

// ---- Show identity invariants ----
// CrowdPlay uses one hosted relay, so never leave the relay blank. And always
// keep a Show ID + a private Show Key: the key is set once (by the installer or
// here) and never exposed in the form, so array_replace_recursive preserves it
// across saves; we only mint one if it's genuinely missing (pre-key configs).
if (trim((string)($cfg['relayUrl'] ?? '')) === '')
  $cfg['relayUrl'] = "https://crowdplay-relay.nscilingo.workers.dev";
if (trim((string)($cfg['showId'] ?? '')) === '')
  $cfg['showId'] = 'show-' . bin2hex(random_bytes(3));
if (trim((string)($cfg['showKey'] ?? '')) === '')
  $cfg['showKey'] = bin2hex(random_bytes(16));

// ---- Atomic write ----
$tmp = $configFile . ".tmp";
$ok = @file_put_contents($tmp, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
if ($ok === false) cpRespond(false, "Could not write config.");
@chmod($tmp, 0600);
if (!@rename($tmp, $configFile)) { @unlink($tmp); cpRespond(false, "Could not save config."); }

// Push the new config to the relay right now so changes (location gating,
// commands, cooldowns, widget style) take effect immediately - no CrowdPlay
// Stop/Start needed to re-register. Best effort: a relay hiccup never blocks
// the local save; the reported flag just drives the UI message. The long-poll
// daemon (cp_poll.sh) also re-registers on its next start as a backstop.
$relaySync = "skipped";
$regScript = __DIR__ . "/../scripts/cp_register.sh";
if (is_file($regScript)) {
  $out = @shell_exec("timeout 18 /bin/bash " . escapeshellarg($regScript) . " 2>&1");
  $relaySync = (is_string($out) && strpos($out, "REGISTER_OK") !== false) ? "ok" : "failed";
}

cpRespond(true, "Saved.", ["commands" => count($cfg['commands']), "relaySync" => $relaySync]);
