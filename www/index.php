<?php
// fpp-CrowdPlay settings page.
$configFile = "/home/fpp/media/plugindata/fpp-CrowdPlay/crowdplay.json";
$cfg = file_exists($configFile) ? json_decode(@file_get_contents($configFile), true) : [];
if (!is_array($cfg)) $cfg = [];

$relayUrl   = $cfg['relayUrl']   ?? '';
$showId     = $cfg['showId']     ?? '';
$licenseKey = $cfg['licenseKey'] ?? '';
$fppApi     = $cfg['fppApi']     ?? 'http://127.0.0.1/api/command';
$holdSec    = $cfg['poll']['holdSec'] ?? 25;
$loc        = $cfg['location'] ?? [];
$commands   = $cfg['commands']  ?? [];
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
?>
<style>
  #cpWrap { max-width: 920px; }
  #cpWrap fieldset { margin: 0 0 18px; border:1px solid #ccc; border-radius:8px; padding:12px 16px; }
  #cpWrap legend { font-weight:600; padding:0 6px; }
  #cpWrap label.row { display:block; margin:8px 0 2px; font-size:13px; opacity:.85; }
  #cpWrap input[type=text], #cpWrap input[type=number], #cpWrap input[type=password] { padding:6px 8px; }
  #cpWrap .wide { width:460px; max-width:100%; }
  #cpCmdTable { width:100%; border-collapse:collapse; font-size:13px; }
  #cpCmdTable th, #cpCmdTable td { border:1px solid #ddd; padding:4px 6px; text-align:left; vertical-align:top; }
  #cpCmdTable input[type=text], #cpCmdTable input[type=number] { width:100%; box-sizing:border-box; padding:4px; }
  #cpCmdTable td.num { width:70px; } #cpCmdTable td.chk { width:52px; text-align:center; }
  #cpMsg { margin-left:10px; font-weight:600; }
  .cp-embed { font-family:monospace; font-size:12px; width:100%; box-sizing:border-box; padding:8px; }
  .cp-muted { opacity:.7; font-size:12px; }
</style>

<div id="cpWrap">
  <h2>CrowdPlay <span class="cp-muted">— put the show in their hands</span></h2>

  <form id="cpForm" onsubmit="return false;">
    <fieldset>
      <legend>Relay</legend>
      <label class="row">Relay URL</label>
      <input type="text" class="wide" id="relayUrl" name="relayUrl" value="<?= $h($relayUrl) ?>"
             placeholder="https://your-show.you.workers.dev">
      <label class="row">Show ID</label>
      <input type="text" id="showId" name="showId" value="<?= $h($showId) ?>" placeholder="my-show">
      <label class="row">License key <span class="cp-muted">(blank = free tier)</span></label>
      <input type="text" class="wide" id="licenseKey" name="licenseKey" value="<?= $h($licenseKey) ?>">
      <details style="margin-top:10px">
        <summary class="cp-muted">Advanced</summary>
        <label class="row">Local FPP command API</label>
        <input type="text" class="wide" id="fppApi" name="fppApi" value="<?= $h($fppApi) ?>">
        <label class="row">Poll hold seconds (1–300)</label>
        <input type="number" id="holdSec" name="holdSec" min="1" max="300" value="<?= $h($holdSec) ?>">
      </details>
    </fieldset>

    <fieldset>
      <legend>Location gating</legend>
      <label><input type="checkbox" id="locEnabled" name="locEnabled" <?= !empty($loc['enabled']) ? 'checked' : '' ?>>
        Require visitors to be near the show</label>
      <div style="margin-top:8px">
        <label class="row">Show location
          <button type="button" onclick="cpUseMyLocation()">Use my current location</button></label>
        lat <input type="text" id="locLat" name="locLat" value="<?= $h($loc['lat'] ?? '') ?>" size="12">
        lon <input type="text" id="locLon" name="locLon" value="<?= $h($loc['lon'] ?? '') ?>" size="12">
        <label class="row">Allowed radius (m)</label>
        <input type="number" id="locRadius" name="locRadius" min="0" value="<?= $h($loc['radiusM'] ?? 300) ?>">
        <label class="row">Device deviation tolerance (m) <span class="cp-muted">— allows for real GPS drift</span></label>
        <input type="number" id="locTolerance" name="locTolerance" min="0" value="<?= $h($loc['toleranceM'] ?? 50) ?>">
        <label class="row">If the visitor denies location permission</label>
        <select id="locOnDenied" name="locOnDenied">
          <option value="strict" <?= (($loc['onDenied'] ?? 'strict') === 'strict') ? 'selected' : '' ?>>Deny the request (strict)</option>
          <option value="lenient" <?= (($loc['onDenied'] ?? '') === 'lenient') ? 'selected' : '' ?>>Allow anyway (lenient)</option>
        </select>
      </div>
    </fieldset>

    <fieldset>
      <legend>Exposed commands</legend>
      <p class="cp-muted">Only the commands you add here can ever be triggered. The raw FPP command and its
        arguments stay on this box — the relay only learns the label, lane, cooldown and duration.</p>
      <table id="cpCmdTable">
        <thead><tr>
          <th>Label</th><th>ID</th><th>FPP command</th><th>Args (JSON array)</th>
          <th>Mode</th><th>Lane</th><th class="num">Cool&shy;down s</th><th class="num">Dur&shy;ation s</th>
          <th class="chk">Public</th><th></th>
        </tr></thead>
        <tbody id="cpCmdBody"></tbody>
      </table>
      <datalist id="cpFppCommands"></datalist>
      <p><button type="button" onclick="cpAddRow()">+ Add command</button></p>
    </fieldset>

    <fieldset>
      <legend>Embed</legend>
      <p class="cp-muted">Paste this into your own site or your Remote Falcon page (it keeps working inside an iframe):</p>
      <textarea class="cp-embed" id="cpEmbed" rows="3" readonly onclick="this.select()"></textarea>
    </fieldset>

    <p>
      <button type="button" onclick="cpSave()">Save</button>
      <button type="button" onclick="cpStart()">Start now</button>
      <button type="button" onclick="cpStop()">Stop now</button>
      <span id="cpMsg"></span>
    </p>
  </form>
</div>

<script>
const CP_PLUGIN = 'fpp-CrowdPlay';
// nopage=1 so plugin.php runs the PHP endpoint raw instead of wrapping it in
// the FPP page chrome (which would swallow the JSON response + its headers).
const cpUrl = (p) => 'plugin.php?plugin=' + CP_PLUGIN + '&nopage=1&page=www/' + p;
const EXISTING = <?= json_encode($commands ?: [], JSON_UNESCAPED_SLASHES) ?>;
const msg = (t, err) => { const m = document.getElementById('cpMsg'); m.textContent = t; m.style.color = err ? '#c0392b' : '#2e7d32'; };

function cpRow(c = {}) {
  const tr = document.createElement('tr');
  const cell = (html) => { const td = document.createElement('td'); td.innerHTML = html; return td; };
  tr.appendChild(cell('<input type="text" class="f-label" value="">'));
  tr.appendChild(cell('<input type="text" class="f-id" value="">'));
  tr.appendChild(cell('<input type="text" class="f-cmd" list="cpFppCommands" value="">'));
  tr.appendChild(cell('<input type="text" class="f-args" value="" placeholder="[]">'));
  tr.appendChild(cell('<select class="f-mode"><option value="overlay">overlay</option><option value="interrupt">interrupt</option></select>'));
  tr.appendChild(cell('<input type="text" class="f-lane" value="default">'));
  const td7 = cell('<input type="number" class="f-cool" min="0" value="0">'); td7.className = 'num'; tr.appendChild(td7);
  const td8 = cell('<input type="number" class="f-dur" min="0" value="0">'); td8.className = 'num'; tr.appendChild(td8);
  const td9 = cell('<input type="checkbox" class="f-pub" checked>'); td9.className = 'chk'; tr.appendChild(td9);
  tr.appendChild(cell('<button type="button" onclick="this.closest(\'tr\').remove()">✕</button>'));
  // populate
  tr.querySelector('.f-label').value = c.label || '';
  tr.querySelector('.f-id').value = c.id || '';
  tr.querySelector('.f-cmd').value = c.fppCommand || '';
  tr.querySelector('.f-args').value = c.args ? JSON.stringify(c.args) : '';
  tr.querySelector('.f-mode').value = (c.mode === 'interrupt') ? 'interrupt' : 'overlay';
  tr.querySelector('.f-lane').value = c.lane || 'default';
  tr.querySelector('.f-cool').value = c.cooldownSec || 0;
  tr.querySelector('.f-dur').value = c.durationSec || 0;
  tr.querySelector('.f-pub').checked = (c.publicSafe !== false);
  return tr;
}
function cpAddRow(c) { document.getElementById('cpCmdBody').appendChild(cpRow(c)); }

function cpCollect() {
  const rows = [];
  document.querySelectorAll('#cpCmdBody tr').forEach(tr => {
    const v = (s) => tr.querySelector(s);
    const label = v('.f-label').value.trim();
    const cmd = v('.f-cmd').value.trim();
    if (!label && !cmd) return; // skip blank row
    rows.push({
      id: v('.f-id').value.trim(), label, fppCommand: cmd,
      args: v('.f-args').value.trim(), mode: v('.f-mode').value,
      lane: v('.f-lane').value.trim() || 'default',
      cooldownSec: parseInt(v('.f-cool').value || '0', 10),
      durationSec: parseInt(v('.f-dur').value || '0', 10),
      publicSafe: v('.f-pub').checked,
    });
  });
  return rows;
}

async function cpSave() {
  const fd = new FormData(document.getElementById('cpForm'));
  fd.set('locEnabled', document.getElementById('locEnabled').checked ? '1' : '');
  fd.set('commandsJson', JSON.stringify(cpCollect()));
  try {
    const res = await fetch(cpUrl('save.php'), { method: 'POST', body: fd, cache: 'no-store' });
    const d = await res.json();
    if (d.status === 'OK') { msg('Saved ✓ (' + (d.commands ?? 0) + ' commands). Re-run “Start” to re-register.'); cpUpdateEmbed(); }
    else msg(d.message || 'Save failed', true);
  } catch (e) { msg('Network error saving', true); }
}

async function cpCommand(name) {
  // FPP's own command API - confirmed POST /api/command {command,args}.
  try {
    const res = await fetch('/api/command', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ command: name, args: [] }), cache: 'no-store'
    });
    return res.ok;
  } catch (e) { return false; }
}
async function cpStart() { msg((await cpCommand('CrowdPlay - Start')) ? 'Start sent ✓' : 'Start failed', false); }
async function cpStop()  { msg((await cpCommand('CrowdPlay - Stop'))  ? 'Stop sent ✓'  : 'Stop failed',  false); }

function cpUseMyLocation() {
  if (!navigator.geolocation) { msg('No geolocation in this browser', true); return; }
  navigator.geolocation.getCurrentPosition(
    p => { document.getElementById('locLat').value = p.coords.latitude.toFixed(6);
           document.getElementById('locLon').value = p.coords.longitude.toFixed(6);
           msg('Location captured ✓'); },
    () => msg('Could not get location', true), { enableHighAccuracy: true, timeout: 10000 });
}

function cpUpdateEmbed() {
  const relay = document.getElementById('relayUrl').value.trim().replace(/\/+$/, '');
  const show = encodeURIComponent(document.getElementById('showId').value.trim());
  const ta = document.getElementById('cpEmbed');
  ta.value = (relay && show)
    ? '<iframe src="' + relay + '/widget?show=' + show + '"\n        width="320" height="420" style="border:0"\n        allow="geolocation"></iframe>'
    : '(set a Relay URL and Show ID, then Save)';
}

async function cpLoadFppCommands() {
  try {
    const res = await fetch('/api/commands', { cache: 'no-store' });
    const list = await res.json();
    const dl = document.getElementById('cpFppCommands');
    (Array.isArray(list) ? list : []).forEach(c => {
      const name = (typeof c === 'string') ? c : (c && c.name);
      if (name) { const o = document.createElement('option'); o.value = name; dl.appendChild(o); }
    });
  } catch (e) { /* datalist stays empty; free-typing still works */ }
}

// init
(EXISTING.length ? EXISTING : [{}]).forEach(cpAddRow);
cpLoadFppCommands();
cpUpdateEmbed();
document.getElementById('relayUrl').addEventListener('input', cpUpdateEmbed);
document.getElementById('showId').addEventListener('input', cpUpdateEmbed);
</script>
