<?php
// fpp-CrowdPlay settings page (skeleton).
// TODO: build out the full UI - relay/show/license, the command allowlist
// editor (label -> FPP command + args + mode + lane + cooldown + public-safe),
// and the location gate (enable, map pin, radius, tolerance, strict/lenient).
// Command/model/sequence pickers pull from FPP's own content lists:
//   /api/models?simple=true   /api/sequence   /api/overlays/effects   /api/commands

$pluginName = "fpp-CrowdPlay";
$cfgFile = "/home/fpp/media/plugindata/fpp-CrowdPlay/crowdplay.json";
$cfg = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : array();
if (!is_array($cfg)) { $cfg = array(); }

$relayUrl = isset($cfg['relayUrl']) ? $cfg['relayUrl'] : '';
$showId   = isset($cfg['showId'])   ? $cfg['showId']   : '';
$hasKey   = !empty($cfg['licenseKey']);
$numCmds  = isset($cfg['commands']) && is_array($cfg['commands']) ? count($cfg['commands']) : 0;
$locOn    = !empty($cfg['location']['enabled']);
?>
<div id="global" class="settings">
  <h2>CrowdPlay <span style="font-weight:normal;opacity:.7">— put the show in their hands</span></h2>

  <fieldset>
    <legend>Relay</legend>
    <table class="settingsTable">
      <tr><td>Relay URL</td><td><input type="text" id="relayUrl" size="48"
            value="<?php echo htmlspecialchars($relayUrl); ?>" placeholder="https://your-show.you.workers.dev"></td></tr>
      <tr><td>Show ID</td><td><input type="text" id="showId" size="32"
            value="<?php echo htmlspecialchars($showId); ?>"></td></tr>
      <tr><td>License key</td><td><?php echo $hasKey ? '<em>configured</em>' : '<em>free tier</em>'; ?>
            <input type="text" id="licenseKey" size="32" placeholder="paste a key to unlock premium"></td></tr>
    </table>
  </fieldset>

  <fieldset>
    <legend>Exposed commands</legend>
    <p><?php echo (int)$numCmds; ?> command(s) configured.
       <em>TODO: allowlist editor — label → FPP command + args + mode (overlay/interrupt)
       + lane + cooldown + public-safe.</em></p>
  </fieldset>

  <fieldset>
    <legend>Location gating</legend>
    <p>Status: <strong><?php echo $locOn ? 'on' : 'off'; ?></strong>.
       <em>TODO: enable toggle, map pin / "use my location", radius, deviation tolerance,
       strict-vs-lenient on denied permission.</em></p>
  </fieldset>

  <p><button type="button" onclick="alert('TODO: wire to www/save.php');">Save</button></p>
</div>
