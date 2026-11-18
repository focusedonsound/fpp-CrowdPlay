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
$configured = ($relayUrl !== '' && $showId !== '');
?>
<style>
  #cpWrap{
    --cp-ink:#1f2430; --cp-muted:#6b7280; --cp-line:#e6e6ef; --cp-card:#ffffff;
    --cp-bg:#f6f6fb; --cp-brand:#6d5bff; --cp-brand2:#9b4dff; --cp-accent:#ff9f1c;
    --cp-good:#1e9e63; --cp-bad:#d6455b; --cp-field:#ffffff; --cp-radius:14px;
    max-width:960px; margin:0 auto; padding:4px 2px 40px;
    color:var(--cp-ink);
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  }
  @media (prefers-color-scheme:dark){
    #cpWrap{
      --cp-ink:#e8e9f2; --cp-muted:#9aa0b0; --cp-line:#2c2f3e; --cp-card:#1b1d27;
      --cp-bg:#141520; --cp-field:#20232f;
    }
  }
  #cpWrap *{box-sizing:border-box}

  /* hero */
  #cpWrap .cp-hero{
    display:flex; align-items:center; gap:16px; flex-wrap:wrap;
    background:linear-gradient(120deg,var(--cp-brand),var(--cp-brand2));
    color:#fff; border-radius:18px; padding:18px 22px; margin:6px 0 20px;
    box-shadow:0 10px 30px -12px rgba(109,91,255,.6);
  }
  #cpWrap .cp-mark{flex:0 0 auto; filter:drop-shadow(0 4px 10px rgba(0,0,0,.25))}
  #cpWrap .cp-hero h1{margin:0; font-size:26px; letter-spacing:-.02em; line-height:1.1}
  #cpWrap .cp-tag{margin:3px 0 0; opacity:.9; font-size:14px}
  #cpWrap .cp-status{margin-left:auto; display:flex; align-items:center; gap:8px;
    background:rgba(255,255,255,.15); padding:7px 12px; border-radius:999px; font-size:13px; font-weight:600}
  #cpWrap .cp-dot{width:9px; height:9px; border-radius:50%; background:#ffd66b; box-shadow:0 0 0 4px rgba(255,214,107,.25)}
  #cpWrap .cp-dot.off{background:#ff8f9c; box-shadow:0 0 0 4px rgba(255,143,156,.2)}

  /* cards */
  #cpWrap .cp-card{
    background:var(--cp-card); border:1px solid var(--cp-line); border-radius:var(--cp-radius);
    padding:18px 20px; margin:0 0 16px; box-shadow:0 1px 2px rgba(16,24,40,.04);
  }
  #cpWrap .cp-card > h2{
    margin:0 0 4px; font-size:15px; font-weight:700; letter-spacing:.01em;
    display:flex; align-items:center; gap:9px;
  }
  #cpWrap .cp-card > h2 .ci{width:22px;height:22px;flex:0 0 auto;color:var(--cp-brand)}
  #cpWrap .cp-sub{margin:0 0 14px; color:var(--cp-muted); font-size:13px; line-height:1.45}

  /* fields */
  #cpWrap label.cp-lab{display:block; margin:12px 0 5px; font-size:12.5px; font-weight:600; color:var(--cp-muted)}
  #cpWrap input[type=text],#cpWrap input[type=number],#cpWrap input[type=password],#cpWrap select{
    width:100%; padding:9px 11px; font-size:14px; color:var(--cp-ink);
    background:var(--cp-field); border:1px solid var(--cp-line); border-radius:10px; outline:none;
  }
  #cpWrap input:focus,#cpWrap select:focus{border-color:var(--cp-brand); box-shadow:0 0 0 3px rgba(109,91,255,.18)}
  #cpWrap .cp-grid2{display:grid; grid-template-columns:1fr 1fr; gap:12px}
  #cpWrap .cp-inline{display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap}
  #cpWrap details.cp-adv{margin-top:12px; border-top:1px dashed var(--cp-line); padding-top:10px}
  #cpWrap details.cp-adv summary{cursor:pointer; color:var(--cp-muted); font-size:13px; font-weight:600}

  /* toggle row */
  #cpWrap .cp-switch{display:flex; align-items:center; gap:10px; font-weight:600; font-size:14px}
  #cpWrap .cp-switch input{width:auto}

  /* command table */
  #cpWrap .cp-tablewrap{overflow-x:auto; border:1px solid var(--cp-line); border-radius:12px}
  #cpWrap table.cp-tbl{width:100%; border-collapse:collapse; font-size:13px; min-width:760px}
  #cpWrap table.cp-tbl th{
    text-align:left; font-size:11px; letter-spacing:.04em; text-transform:uppercase;
    color:var(--cp-muted); font-weight:700; padding:10px 8px; background:var(--cp-bg); border-bottom:1px solid var(--cp-line); white-space:nowrap;
  }
  #cpWrap table.cp-tbl td{padding:7px 8px; border-bottom:1px solid var(--cp-line); vertical-align:middle}
  #cpWrap table.cp-tbl tr:last-child td{border-bottom:0}
  #cpWrap table.cp-tbl input[type=text],#cpWrap table.cp-tbl input[type=number],#cpWrap table.cp-tbl select{padding:6px 8px; border-radius:8px}
  #cpWrap td.cp-num input{width:74px}
  #cpWrap td.cp-chk{text-align:center}
  #cpWrap .cp-rm{border:0; background:transparent; color:var(--cp-muted); cursor:pointer; font-size:17px; line-height:1; padding:4px 7px; border-radius:7px}
  #cpWrap .cp-rm:hover{background:rgba(214,69,91,.12); color:var(--cp-bad)}
  #cpWrap .cp-add{
    margin-top:12px; border:1.5px dashed var(--cp-line); background:transparent; color:var(--cp-brand);
    font-weight:600; font-size:13.5px; padding:10px 14px; border-radius:10px; cursor:pointer; width:100%;
  }
  #cpWrap .cp-add:hover{border-color:var(--cp-brand); background:rgba(109,91,255,.06)}

  /* embed */
  #cpWrap .cp-embed{width:100%; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12px;
    padding:11px 12px; border:1px solid var(--cp-line); border-radius:10px; background:var(--cp-field); color:var(--cp-ink); resize:vertical}
  #cpWrap .cp-copy{margin-top:8px}

  /* action bar */
  #cpWrap .cp-actions{
    position:sticky; bottom:0; display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    background:var(--cp-card); border:1px solid var(--cp-line); border-radius:14px;
    padding:12px 16px; box-shadow:0 -6px 20px -14px rgba(16,24,40,.3);
  }
  #cpWrap button.cp-btn{font-size:14px; font-weight:650; padding:10px 18px; border-radius:11px; cursor:pointer; border:1px solid transparent}
  #cpWrap .cp-btn.primary{background:linear-gradient(120deg,var(--cp-brand),var(--cp-brand2)); color:#fff; box-shadow:0 8px 18px -8px rgba(109,91,255,.7)}
  #cpWrap .cp-btn.primary:hover{filter:brightness(1.06)}
  #cpWrap .cp-btn.ghost{background:var(--cp-field); border-color:var(--cp-line); color:var(--cp-ink)}
  #cpWrap .cp-btn.ghost:hover{border-color:var(--cp-brand); color:var(--cp-brand)}
  #cpWrap #cpMsg{margin-left:auto; font-weight:650; font-size:13.5px; min-height:1.2em}
  #cpWrap .cp-muted{color:var(--cp-muted)}
  #cpWrap a{color:var(--cp-brand)}
  @media (max-width:560px){ #cpWrap .cp-grid2{grid-template-columns:1fr} #cpWrap #cpMsg{margin-left:0; width:100%} }
</style>

<div id="cpWrap">
  <header class="cp-hero">
    <svg class="cp-mark" width="52" height="52" viewBox="0 0 512 512" aria-hidden="true">
      <defs>
        <linearGradient id="cpBtn" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFD66B"/><stop offset="1" stop-color="#FF9F1C"/></linearGradient>
        <radialGradient id="cpGlow" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="#FFD66B" stop-opacity="0.95"/><stop offset="1" stop-color="#FFD66B" stop-opacity="0"/></radialGradient>
      </defs>
      <rect x="150" y="86" width="196" height="340" rx="34" fill="#ffffff"/>
      <rect x="168" y="116" width="160" height="280" rx="16" fill="#1C1340"/>
      <rect x="226" y="100" width="44" height="8" rx="4" fill="#CBB8FF"/>
      <circle cx="248" cy="236" r="104" fill="url(#cpGlow)"/>
      <circle cx="248" cy="236" r="84" fill="none" stroke="#FFD66B" stroke-opacity="0.30" stroke-width="7"/>
      <circle cx="248" cy="236" r="66" fill="none" stroke="#FFD66B" stroke-opacity="0.55" stroke-width="7"/>
      <circle cx="248" cy="236" r="50" fill="url(#cpBtn)"/>
      <g transform="rotate(26 300 360)"><rect x="276" y="236" width="56" height="170" rx="28" fill="#ffffff"/><circle cx="304" cy="250" r="30" fill="#ffffff"/></g>
    </svg>
    <div>
      <h1>CrowdPlay</h1>
      <p class="cp-tag">Put the show in their hands.</p>
    </div>
    <div class="cp-status">
      <span class="cp-dot <?= $configured ? '' : 'off' ?>"></span>
      <span id="cpStatusText"><?= $configured ? 'Relay configured' : 'Not configured' ?></span>
    </div>
  </header>

  <form id="cpForm" onsubmit="return false;">
    <!-- Relay -->
    <section class="cp-card">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.5a7 7 0 0 1 14 0"/><path d="M8 15a4 4 0 0 1 8 0"/><circle cx="12" cy="18" r="1.4" fill="currentColor" stroke="none"/></svg>Relay</h2>
      <p class="cp-sub">Where this player polls for visitor requests. The player never opens a port &mdash; it reaches the relay outbound.</p>
      <label class="cp-lab">Relay URL</label>
      <input type="text" id="relayUrl" name="relayUrl" value="<?= $h($relayUrl) ?>" placeholder="https://your-show.workers.dev">
      <div class="cp-grid2">
        <div><label class="cp-lab">Show ID</label>
          <input type="text" id="showId" name="showId" value="<?= $h($showId) ?>" placeholder="my-show"></div>
        <div><label class="cp-lab">License key <span class="cp-muted">(blank = free tier)</span></label>
          <input type="text" id="licenseKey" name="licenseKey" value="<?= $h($licenseKey) ?>" placeholder="unlock premium"></div>
      </div>
      <details class="cp-adv">
        <summary>Advanced</summary>
        <div class="cp-grid2">
          <div><label class="cp-lab">Local FPP command API</label>
            <input type="text" id="fppApi" name="fppApi" value="<?= $h($fppApi) ?>"></div>
          <div><label class="cp-lab">Poll hold seconds (1&ndash;300)</label>
            <input type="number" id="holdSec" name="holdSec" min="1" max="300" value="<?= $h($holdSec) ?>"></div>
        </div>
      </details>
    </section>

    <!-- Location -->
    <section class="cp-card">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-6-5.3-6-10a6 6 0 1 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2.3"/></svg>Location gating</h2>
      <p class="cp-sub">Optionally require a visitor to be near the show. Checked at the relay from the visitor's device; coordinates aren't stored.</p>
      <label class="cp-switch"><input type="checkbox" id="locEnabled" name="locEnabled" <?= !empty($loc['enabled']) ? 'checked' : '' ?>> Require visitors to be near the show</label>
      <div class="cp-inline" style="margin-top:12px">
        <div style="flex:1 1 150px"><label class="cp-lab">Latitude</label>
          <input type="text" id="locLat" name="locLat" value="<?= $h($loc['lat'] ?? '') ?>"></div>
        <div style="flex:1 1 150px"><label class="cp-lab">Longitude</label>
          <input type="text" id="locLon" name="locLon" value="<?= $h($loc['lon'] ?? '') ?>"></div>
        <button type="button" class="cp-btn ghost" onclick="cpUseMyLocation()">Use my location</button>
      </div>
      <div class="cp-grid2" style="margin-top:4px">
        <div><label class="cp-lab">Allowed radius (m)</label>
          <input type="number" id="locRadius" name="locRadius" min="0" value="<?= $h($loc['radiusM'] ?? 300) ?>"></div>
        <div><label class="cp-lab">Device tolerance (m) <span class="cp-muted">&mdash; GPS drift</span></label>
          <input type="number" id="locTolerance" name="locTolerance" min="0" value="<?= $h($loc['toleranceM'] ?? 50) ?>"></div>
      </div>
      <label class="cp-lab">If the visitor denies location permission</label>
      <select id="locOnDenied" name="locOnDenied">
        <option value="strict" <?= (($loc['onDenied'] ?? 'strict') === 'strict') ? 'selected' : '' ?>>Deny the request (strict)</option>
        <option value="lenient" <?= (($loc['onDenied'] ?? '') === 'lenient') ? 'selected' : '' ?>>Allow anyway (lenient)</option>
      </select>
    </section>

    <!-- Commands -->
    <section class="cp-card">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M7 9h6M7 13h10"/></svg>Exposed commands</h2>
      <p class="cp-sub">Only commands you add here can be triggered. The raw FPP command and its arguments stay on this player &mdash; the relay only learns the label, lane, cooldown and duration.</p>
      <div class="cp-tablewrap">
        <table class="cp-tbl">
          <thead><tr>
            <th>Label</th><th>ID</th><th>FPP command</th><th>Args (JSON)</th>
            <th>Mode</th><th>Lane</th><th>Cooldown&nbsp;(s)</th><th>Duration&nbsp;(s)</th><th>Public</th><th></th>
          </tr></thead>
          <tbody id="cpCmdBody"></tbody>
        </table>
      </div>
      <datalist id="cpFppCommands"></datalist>
      <button type="button" class="cp-add" onclick="cpAddRow()">+ Add command</button>
    </section>

    <!-- Embed -->
    <section class="cp-card">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 9l-4 3 4 3M16 9l4 3-4 3"/></svg>Embed</h2>
      <p class="cp-sub">Paste into your own site or your Remote Falcon page &mdash; it keeps working inside an iframe.</p>
      <textarea class="cp-embed" id="cpEmbed" rows="3" readonly onclick="this.select()"></textarea>
      <div><button type="button" class="cp-btn ghost cp-copy" onclick="cpCopyEmbed()">Copy embed code</button></div>
    </section>

    <div class="cp-actions">
      <button type="button" class="cp-btn primary" onclick="cpSave()">Save</button>
      <button type="button" class="cp-btn ghost" onclick="cpStart()">Start now</button>
      <button type="button" class="cp-btn ghost" onclick="cpStop()">Stop now</button>
      <span id="cpMsg"></span>
    </div>
  </form>
</div>

<script>
const CP_PLUGIN = 'fpp-CrowdPlay';
const cpUrl = (p) => 'plugin.php?plugin=' + CP_PLUGIN + '&nopage=1&page=www/' + p;
const EXISTING = <?= json_encode($commands ?: [], JSON_UNESCAPED_SLASHES) ?>;
function msg(t, err){ const m=document.getElementById('cpMsg'); m.textContent=t; m.style.color = err ? 'var(--cp-bad)' : 'var(--cp-good)'; }

function cpRow(c = {}) {
  const tr = document.createElement('tr');
  const cell = (html) => { const td = document.createElement('td'); td.innerHTML = html; return td; };
  tr.appendChild(cell('<input type="text" class="f-label">'));
  tr.appendChild(cell('<input type="text" class="f-id">'));
  tr.appendChild(cell('<input type="text" class="f-cmd" list="cpFppCommands">'));
  tr.appendChild(cell('<input type="text" class="f-args" placeholder="[]">'));
  tr.appendChild(cell('<select class="f-mode"><option value="overlay">overlay</option><option value="interrupt">interrupt</option></select>'));
  tr.appendChild(cell('<input type="text" class="f-lane" value="default">'));
  const t7 = cell('<input type="number" class="f-cool" min="0" value="0">'); t7.className='cp-num'; tr.appendChild(t7);
  const t8 = cell('<input type="number" class="f-dur" min="0" value="0">'); t8.className='cp-num'; tr.appendChild(t8);
  const t9 = cell('<input type="checkbox" class="f-pub" checked>'); t9.className='cp-chk'; tr.appendChild(t9);
  tr.appendChild(cell('<button type="button" class="cp-rm" title="Remove" onclick="this.closest(\'tr\').remove()">&times;</button>'));
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
function cpAddRow(c){ document.getElementById('cpCmdBody').appendChild(cpRow(c)); }

function cpCollect(){
  const rows=[];
  document.querySelectorAll('#cpCmdBody tr').forEach(tr=>{
    const v=(s)=>tr.querySelector(s);
    const label=v('.f-label').value.trim(), cmd=v('.f-cmd').value.trim();
    if(!label && !cmd) return;
    rows.push({ id:v('.f-id').value.trim(), label, fppCommand:cmd, args:v('.f-args').value.trim(),
      mode:v('.f-mode').value, lane:v('.f-lane').value.trim()||'default',
      cooldownSec:parseInt(v('.f-cool').value||'0',10), durationSec:parseInt(v('.f-dur').value||'0',10),
      publicSafe:v('.f-pub').checked });
  });
  return rows;
}

async function cpSave(){
  const fd=new FormData(document.getElementById('cpForm'));
  fd.set('locEnabled', document.getElementById('locEnabled').checked ? '1' : '');
  fd.set('commandsJson', JSON.stringify(cpCollect()));
  try{
    const res=await fetch(cpUrl('save.php'),{method:'POST',body:fd,cache:'no-store'});
    const d=await res.json();
    if(d.status==='OK'){ msg('Saved ✓ ('+(d.commands??0)+' commands) — re-run Start to re-register.');
      cpUpdateEmbed(); cpRefreshStatus(); }
    else msg(d.message||'Save failed', true);
  }catch(e){ msg('Network error saving', true); }
}

async function cpCommand(name){
  try{ const r=await fetch('/api/command',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({command:name,args:[]}),cache:'no-store'}); return r.ok; }catch(e){ return false; }
}
async function cpStart(){ msg((await cpCommand('CrowdPlay - Start')) ? 'Start sent ✓' : 'Start failed', false); }
async function cpStop(){ msg((await cpCommand('CrowdPlay - Stop')) ? 'Stop sent ✓' : 'Stop failed', false); }

function cpUseMyLocation(){
  if(!navigator.geolocation){ msg('No geolocation in this browser', true); return; }
  navigator.geolocation.getCurrentPosition(
    p=>{ document.getElementById('locLat').value=p.coords.latitude.toFixed(6);
         document.getElementById('locLon').value=p.coords.longitude.toFixed(6); msg('Location captured ✓'); },
    ()=>msg('Could not get location', true), {enableHighAccuracy:true,timeout:10000});
}

function cpEmbedCode(){
  const relay=document.getElementById('relayUrl').value.trim().replace(/\/+$/,'');
  const show=encodeURIComponent(document.getElementById('showId').value.trim());
  return (relay && show)
    ? '<iframe src="'+relay+'/widget?show='+show+'"\n        width="320" height="420" style="border:0"\n        allow="geolocation"></iframe>'
    : '(set a Relay URL and Show ID, then Save)';
}
function cpUpdateEmbed(){ document.getElementById('cpEmbed').value = cpEmbedCode(); }
function cpCopyEmbed(){
  const t=document.getElementById('cpEmbed'); t.select();
  navigator.clipboard ? navigator.clipboard.writeText(t.value).then(()=>msg('Embed code copied ✓')) : document.execCommand('copy');
}
function cpRefreshStatus(){
  const ok = document.getElementById('relayUrl').value.trim() && document.getElementById('showId').value.trim();
  document.querySelector('#cpWrap .cp-dot').classList.toggle('off', !ok);
  document.getElementById('cpStatusText').textContent = ok ? 'Relay configured' : 'Not configured';
}

async function cpLoadFppCommands(){
  try{
    const res=await fetch('/api/commands',{cache:'no-store'});
    const list=await res.json();
    const dl=document.getElementById('cpFppCommands');
    (Array.isArray(list)?list:[]).forEach(c=>{ const name=(typeof c==='string')?c:(c&&c.name);
      if(name){ const o=document.createElement('option'); o.value=name; dl.appendChild(o); } });
  }catch(e){}
}

(EXISTING.length ? EXISTING : [{}]).forEach(cpAddRow);
cpLoadFppCommands();
cpUpdateEmbed();
document.getElementById('relayUrl').addEventListener('input', ()=>{ cpUpdateEmbed(); cpRefreshStatus(); });
document.getElementById('showId').addEventListener('input', ()=>{ cpUpdateEmbed(); cpRefreshStatus(); });
</script>
