<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["status" => "ERROR", "message" => "POST required"]);
  exit;
}

$configFile = "/home/fpp/media/plugindata/fpp-CrowdPlay/crowdplay.json";
require_once __DIR__ . "/cp_config_lock.php";
$lock = cpConfigLockAuto($configFile);

$cfg = [];
if (file_exists($configFile)) {
  $j = json_decode(@file_get_contents($configFile), true);
  if (is_array($j)) $cfg = $j;
}
$cfg["ui"]["onboardingSeen"] = true;

$tmp = $configFile . ".tmp";
if (@file_put_contents($tmp, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false
    || !@rename($tmp, $configFile)) {
  @unlink($tmp);
  cpConfigUnlock($lock);
  echo json_encode(["status" => "ERROR", "message" => "Failed to save"]);
  exit;
}
@chmod($configFile, 0600);
cpConfigUnlock($lock);

echo json_encode(["status" => "OK"]);
