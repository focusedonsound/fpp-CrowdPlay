<?php
// Shared file-locking helper for crowdplay.json.
//
// save.php follows a read -> merge -> write-to-.tmp -> rename pattern. The
// rename is atomic, but the read-and-merge in between is not: two requests
// landing close together could each read the same starting content, merge
// independently, and whichever rename() lands second silently wins. A
// separate .lock file (not the config itself) so a plain reader that opens
// crowdplay.json directly is never blocked - only opt-in writers serialize.
// (Pattern mirrors fpp-EncoreRadio's er_config_lock.php.)

function cpConfigLock($configFile) {
  $lockFile = $configFile . ".lock";
  $fh = @fopen($lockFile, 'c');
  if ($fh === false) return false;
  if (!flock($fh, LOCK_EX)) { fclose($fh); return false; }
  return $fh;
}

function cpConfigUnlock($fh) {
  // Safe to call twice on the same handle (explicit release + shutdown
  // backstop): flock() on an already-fclose()'d resource throws on modern
  // PHP, and is_resource() is false once closed.
  if (!is_resource($fh)) return;
  flock($fh, LOCK_UN);
  fclose($fh);
}

function cpConfigLockAuto($configFile) {
  $fh = cpConfigLock($configFile);
  if ($fh !== false) {
    register_shutdown_function('cpConfigUnlock', $fh);
  }
  return $fh;
}
