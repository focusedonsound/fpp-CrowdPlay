<?php
// fpp-CrowdPlay settings page.
$configFile = "/home/fpp/media/plugindata/fpp-CrowdPlay/crowdplay.json";
$cfg = file_exists($configFile) ? json_decode(@file_get_contents($configFile), true) : [];
if (!is_array($cfg)) $cfg = [];

// The relay is hosted for everyone; a fresh install seeds relayUrl blank, so
// default to the built-in hosted relay for the embed snippet and status checks.
$relayUrl   = $cfg['relayUrl']   ?? '';
if ($relayUrl === '') $relayUrl = 'https://crowdplay-relay.nscilingo.workers.dev';
$showId     = $cfg['showId']     ?? '';
$licenseKey = $cfg['licenseKey'] ?? '';
$fppApi     = $cfg['fppApi']     ?? 'http://127.0.0.1/api/command';
$holdSec    = $cfg['poll']['holdSec'] ?? 25;
$loc        = $cfg['location'] ?? [];
$widget     = $cfg['widget']   ?? [];
$commands   = $cfg['commands']  ?? [];
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$configured = ($relayUrl !== '' && $showId !== '');
$ui = $cfg['ui'] ?? [];
$onboardingSeen = !empty($ui['onboardingSeen']);
$tourEnabled = ($ui['onboardingTourEnabled'] ?? true);
// Optional, operator-set link to off-plugin license-key info. Blank by default:
// FPP's plugin guidelines forbid pricing/promotion or payment links in a plugin,
// so nothing ships pointing at a purchase page. Set it in config if you want a link.
$upgradeUrl = $cfg['upgradeUrl'] ?? '';

// Plugin version shown in the page header. Prefer the committed VERSION file
// (always correct for a release checkout, and works even when PHP's shell_exec
// is disabled or git tags weren't fetched); fall back to the git tag.
$cpVersion = '';
$cpRoot = dirname(__DIR__);
if (is_file($cpRoot . '/VERSION')) {
    $v = trim((string)@file_get_contents($cpRoot . '/VERSION'));
    if ($v !== '') $cpVersion = 'v' . ltrim($v, 'v');
}
if ($cpVersion === '') {
    $g = @trim((string)@shell_exec('git -C ' . escapeshellarg($cpRoot) . ' describe --tags --abbrev=0 2>/dev/null'));
    if ($g !== '') $cpVersion = $g;
}
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
  /* RAG status dot: green = ready (relay configured), red = not configured. */
  #cpWrap .cp-dot{width:9px; height:9px; border-radius:50%; background:#3ad07a; box-shadow:0 0 0 4px rgba(58,208,122,.28)}
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
  #cpWrap input[type=color]{width:100%; height:42px; padding:4px; cursor:pointer}
  /* Make every dropdown clearly a dropdown: force a visible caret even when a
     parent theme strips the native one. */
  #cpWrap select{
    -webkit-appearance:none; -moz-appearance:none; appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8' fill='none' stroke='%238a90a0' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M1 1.75 6 6.5 11 1.75'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 11px center; background-size:12px 8px;
    padding-right:30px; cursor:pointer;
  }
  #cpWrap .cp-badge{display:inline-block; font-weight:700; font-size:10.5px; letter-spacing:.04em; text-transform:uppercase; color:#fff; background:linear-gradient(120deg,var(--cp-brand),var(--cp-brand2)); padding:2px 8px; border-radius:999px; vertical-align:middle; margin-left:6px}
  #cpWrap .cp-ver{display:inline-block; font-size:11.5px; font-weight:700; color:#fff; background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.28); padding:1px 8px; border-radius:999px; vertical-align:middle; margin-left:8px; letter-spacing:.02em}
  #cpWrap table.cp-stats{width:100%; border-collapse:collapse; font-size:13px}
  #cpWrap table.cp-stats th{text-align:left; color:var(--cp-muted); font-size:11px; text-transform:uppercase; letter-spacing:.04em; padding:6px 8px; border-bottom:1px solid var(--cp-line)}
  #cpWrap table.cp-stats td{padding:7px 8px; border-bottom:1px solid var(--cp-line)}
  #cpWrap table.cp-stats td.n{text-align:right; font-variant-numeric:tabular-nums; font-weight:600}
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

  /* onboarding tour */
  #cp-tour-highlight{position:fixed; z-index:10050; pointer-events:none; border:2.5px solid var(--cp-brand); border-radius:14px; box-shadow:0 0 0 4000px rgba(10,8,30,.55); transition:top .2s,left .2s,width .2s,height .2s}
  #cp-tour-popup{position:fixed; z-index:10051; max-width:350px; width:calc(100% - 24px); background:var(--cp-card); color:var(--cp-ink); border:1px solid var(--cp-line); border-radius:14px; box-shadow:0 .7rem 1.6rem rgba(0,0,0,.45); padding:16px 18px; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
  #cp-tour-popup .t-of{font-size:11px; letter-spacing:.05em; text-transform:uppercase; color:var(--cp-brand); font-weight:700; margin-bottom:5px}
  #cp-tour-popup .t-title{font-weight:750; font-size:16px; margin-bottom:6px; color:var(--cp-ink)}
  #cp-tour-popup .t-text{font-size:13.5px; color:var(--cp-muted); line-height:1.5; margin-bottom:14px}
  #cp-tour-popup .t-row{display:flex; gap:8px; align-items:center}
  #cp-tour-popup .t-row .spacer{margin-left:auto}
  #cp-tour-popup button{font-size:13px; font-weight:650; padding:8px 14px; border-radius:9px; cursor:pointer; border:1px solid var(--cp-line); background:var(--cp-field); color:var(--cp-ink)}
  #cp-tour-popup button.t-next{background:linear-gradient(120deg,var(--cp-brand),var(--cp-brand2)); color:#fff; border-color:transparent}
  #cp-tour-popup button.t-skip{border:0; background:transparent; color:var(--cp-muted); padding-left:0}
  #cp-tour-popup button:disabled{opacity:.4; cursor:default}
  #cp-tour-arrow{position:fixed; z-index:10051; width:0; height:0; border-left:9px solid transparent; border-right:9px solid transparent}
  .cp-arrow-below{border-bottom:9px solid var(--cp-card)}
  .cp-arrow-above{border-top:9px solid var(--cp-card)}
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
      <h1>CrowdPlay<?php if ($cpVersion !== ''): ?> <span class="cp-ver"><?= $h($cpVersion) ?></span><?php endif; ?></h1>
      <p class="cp-tag">Put the show in their hands.</p>
    </div>
    <div class="cp-status">
      <span class="cp-dot <?= $configured ? '' : 'off' ?>"></span>
      <span id="cpStatusText"><?= $configured ? 'Relay configured' : 'Not configured' ?></span>
    </div>
  </header>

  <div style="text-align:right; margin:-10px 2px 14px">
    <a href="#" id="cpReplayTour" style="font-size:12.5px; font-weight:600; text-decoration:none">&#9654; Replay walkthrough</a>
  </div>

  <form id="cpForm" onsubmit="return false;">
    <!-- Relay -->
    <section class="cp-card" id="cpCardRelay">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.5a7 7 0 0 1 14 0"/><path d="M8 15a4 4 0 0 1 8 0"/><circle cx="12" cy="18" r="1.4" fill="currentColor" stroke="none"/></svg>Your show</h2>
      <p class="cp-sub">CrowdPlay is hosted for you &mdash; there's nothing to deploy and no port to open. This player is already connected; just give your show a name.</p>
      <label class="cp-lab">Show name <span class="cp-muted">&mdash; appears in your embed link; must be unique</span></label>
      <input type="text" id="showId" name="showId" value="<?= $h($showId) ?>" placeholder="my-show">
      <label class="cp-lab">License key <span class="cp-muted">(optional)</span></label>
      <input type="password" id="licenseKey" name="licenseKey" autocomplete="off" value="<?= $h($licenseKey) ?>" placeholder="paste your license key">
      <p class="cp-sub" style="margin:6px 0 0; font-size:12.5px">CrowdPlay works without a key. A license key is optional and raises the per-hour and control limits.<?php if ($upgradeUrl !== ''): ?> <a href="<?= $h($upgradeUrl) ?>" target="_blank" rel="noopener">Details</a>.<?php endif; ?></p>
      <!-- Relay URL is hosted for everyone; kept as a hidden field (feeds the embed
           snippet builder and the save path) rather than a self-host setting. -->
      <input type="hidden" id="relayUrl" name="relayUrl" value="<?= $h($relayUrl) ?>">
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
    <section class="cp-card" id="cpCardLoc">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-6-5.3-6-10a6 6 0 1 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2.3"/></svg>Location gating</h2>
      <p class="cp-sub">Optionally require a visitor to be near the show. Checked at the relay from the visitor's device; coordinates aren't stored.</p>
      <label class="cp-switch"><input type="checkbox" id="locEnabled" name="locEnabled" <?= !empty($loc['enabled']) ? 'checked' : '' ?>> Require visitors to be near the show</label>
      <div class="cp-inline" style="margin-top:12px">
        <div style="flex:1 1 150px"><label class="cp-lab">Latitude</label>
          <input type="text" id="locLat" name="locLat" value="<?= $h($loc['lat'] ?? '') ?>"></div>
        <div style="flex:1 1 150px"><label class="cp-lab">Longitude</label>
          <input type="text" id="locLon" name="locLon" value="<?= $h($loc['lon'] ?? '') ?>"></div>
        <button type="button" class="cp-btn ghost" onclick="cpUseFppLocation()">Use FPP's location</button>
        <button type="button" class="cp-btn ghost" onclick="cpUseMyLocation()">Use my location</button>
      </div>
      <p class="cp-sub" style="margin-top:6px">&ldquo;Use FPP's location&rdquo; pulls the coordinates FPP already has in <strong>Settings &rarr; Localization</strong> (works here on FPP's HTTP page). &ldquo;Use my location&rdquo; reads this device's GPS but needs an HTTPS page. You can also <a href="https://www.google.com/maps" target="_blank" rel="noopener">look up your coordinates</a> (right-click your spot in Google Maps) and type them in.</p>
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

    <!-- Bot protection -->
    <section class="cp-card" id="cpCardBot">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v5c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>Bot protection</h2>
      <p class="cp-sub">Add a Cloudflare &ldquo;confirm you&rsquo;re human&rdquo; check before a press goes through. <strong>Off by default</strong> &mdash; the relay already rate-limits every press (cooldown, per-visitor burst limit, hourly cap, duplicate-press guard), so most shows don&rsquo;t need this. Turning it on can occasionally show a challenge, and some phone browsers struggle with it inside an embedded page, so leave it off unless you&rsquo;re seeing abuse.</p>
      <label class="cp-switch"><input type="checkbox" id="tsRequired" name="tsRequired" <?= !empty($cfg['turnstileRequired']) ? 'checked' : '' ?>> Require a human check (Turnstile)</label>
    </section>

    <!-- Commands -->
    <section class="cp-card" id="cpCardCmd">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M7 9h6M7 13h10"/></svg>Exposed commands</h2>
      <p class="cp-sub">Only commands you add here can be triggered. For each one, pick a <b>Command Preset</b> you built in FPP (Content&nbsp;Setup&nbsp;&rarr;&nbsp;Command&nbsp;Presets &mdash; easiest, no JSON) or a raw FPP command with its arguments. What you pick stays on this player &mdash; the relay only learns the label, lane, cooldown, duration and (optional) button image.</p>
      <div class="cp-tablewrap">
        <table class="cp-tbl">
          <thead><tr>
            <th>Label</th><th>ID</th><th>Preset / command</th><th>Args (JSON)</th>
            <th>Mode</th><th>Lane</th><th>Cooldown&nbsp;(s)</th><th>Duration&nbsp;(s)</th><th>Public</th><th>Image</th><th></th>
          </tr></thead>
          <tbody id="cpCmdBody"></tbody>
        </table>
      </div>
      <button type="button" class="cp-add" onclick="cpAddRow()">+ Add command</button>
      <p class="cp-sub" style="margin:10px 0 0">Without a license key, a show can expose up to <b>3</b> controls and take <b>30</b> presses per hour (shared across all controls). A license key raises both limits. <b>Custom button images</b> are a licensed feature &mdash; add a PNG/JPG/WEBP/GIF (max 512&nbsp;KB) per control; free shows show plain text buttons.</p>
    </section>

    <!-- Widget appearance (premium) -->
    <section class="cp-card" id="cpCardStyle">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a9 9 0 1 0 0 18c1.2 0 2-.9 2-2 0-1.2-1-1.8-1-2.8 0-.7.6-1.2 1.4-1.2H17a4 4 0 0 0 4-4c0-4.4-4-8-9-8z"/><circle cx="7.5" cy="10.5" r="1.1" fill="currentColor" stroke="none"/><circle cx="12" cy="7.5" r="1.1" fill="currentColor" stroke="none"/><circle cx="16.5" cy="10.5" r="1.1" fill="currentColor" stroke="none"/></svg>Widget appearance</h2>
      <p class="cp-sub">Match the buttons to your site. Applied on licensed shows; without a license key the widget uses the default look. Every widget carries a small &ldquo;Powered by CrowdPlay&rdquo; badge. Re-run Start after changing.</p>
      <div class="cp-grid2">
        <div><label class="cp-lab">Button color</label>
          <input type="color" id="wgButtonColor" name="wgButtonColor" value="<?= $h(($widget['buttonColor'] ?? '') ?: '#6d5bff') ?>"></div>
        <div><label class="cp-lab">Text color</label>
          <input type="color" id="wgTextColor" name="wgTextColor" value="<?= $h(($widget['textColor'] ?? '') ?: '#ffffff') ?>"></div>
      </div>
      <label class="cp-lab">Headline <span class="cp-muted">&mdash; shown above the buttons; optional</span></label>
      <input type="text" id="wgHeadline" name="wgHeadline" maxlength="60" value="<?= $h($widget['headline'] ?? '') ?>" placeholder="Control the lights!">
    </section>

    <!-- Embed -->
    <section class="cp-card" id="cpCardEmbed">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 9l-4 3 4 3M16 9l4 3-4 3"/></svg>Embed</h2>
      <p class="cp-sub">Each control is its own button, so you can place them anywhere on your site or Remote Falcon page. Copy one snippet per control below &mdash; or grab all buttons as a single widget.</p>
      <label class="cp-lab">One button per control <span class="cp-muted">&mdash; paste each wherever you like</span></label>
      <textarea class="cp-embed" id="cpEmbed" rows="8" readonly onclick="this.select()"></textarea>
      <div><button type="button" class="cp-btn ghost cp-copy" onclick="cpCopyEmbed()">Copy per-control code</button></div>
      <details class="cp-adv">
        <summary>Or: all buttons as one widget</summary>
        <textarea class="cp-embed" id="cpEmbedAll" rows="3" readonly onclick="this.select()" style="margin-top:8px"></textarea>
        <div><button type="button" class="cp-btn ghost cp-copy" onclick="cpCopyEmbed('cpEmbedAll')">Copy single-widget code</button></div>
      </details>
    </section>

    <!-- Analytics -->
    <section class="cp-card" id="cpCardStats">
      <h2><svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>Analytics</h2>
      <p class="cp-sub">How many times each control has been pressed &mdash; live counts from the relay.</p>
      <div id="cpStats" class="cp-muted">Loading&hellip;</div>
      <div style="margin-top:12px"><button type="button" class="cp-btn ghost" onclick="cpLoadStats()">Refresh</button></div>
    </section>

    <div class="cp-actions">
      <button type="button" class="cp-btn primary" onclick="cpSave()">Save</button>
      <button type="button" class="cp-btn ghost" onclick="cpStart()">Start now</button>
      <button type="button" class="cp-btn ghost" onclick="cpStop()">Stop now</button>
      <span id="cpMsg"></span>
    </div>
    <p class="cp-sub" style="margin:8px 2px 0; font-size:12.5px"><b>Start now</b> works immediately. To have CrowdPlay start automatically before your show, add the <b>CrowdPlay&nbsp;-&nbsp;Start</b> command to your schedule &mdash; that command becomes available after you restart FPPD once (FPP shows a &ldquo;restart&rdquo; banner right after installing the plugin).</p>
  </form>
</div>

<script>
const CP_PLUGIN = 'fpp-CrowdPlay';
const cpUrl = (p) => 'plugin.php?plugin=' + CP_PLUGIN + '&nopage=1&page=www/' + p;
const EXISTING = <?= json_encode($commands ?: [], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function msg(t, err){ const m=document.getElementById('cpMsg'); m.textContent=t; m.style.color = err ? 'var(--cp-bad)' : 'var(--cp-good)'; }

// A control runs either a saved FPP Command Preset (easiest - you build it in
// FPP with dropdowns, no JSON) or a raw FPP command + args. Both lists are
// loaded from FPP: presets from /api/configfile/commandPresets.json, commands
// from /api/commands. An option's value is "preset:<name>" or "cmd:<name>".
let cpFppCmdNames = [];
let cpFppPresets  = [];
function cpMkOpt(type, name){
  const o=document.createElement('option');
  o.value=type+':'+name; o.dataset.type=type; o.dataset.name=name; o.textContent=name;
  return o;
}
// Show the Args box only for a raw command; a preset carries its own args.
function cpSyncArgsVisibility(sel){
  const tr=sel.closest('tr'); if(!tr) return;
  const args=tr.querySelector('.f-args'); if(!args) return;
  const opt=sel.selectedOptions && sel.selectedOptions[0];
  const isPreset = opt && opt.dataset.type==='preset';
  args.disabled = !!isPreset;
  args.placeholder = isPreset ? 'uses the preset' : '[]';
  args.style.opacity = isPreset ? '0.45' : '';
  if(isPreset) args.value='';
}
// Build a row's dropdown (grouped presets + commands), keeping the saved value
// even if the lists haven't loaded yet or FPP no longer lists it.
function cpFillCmdSelect(sel, selected){
  const want = (selected != null && selected !== '') ? selected : (sel.value || sel.dataset.value || '');
  sel.innerHTML = '';
  const ph=document.createElement('option'); ph.value=''; ph.textContent='— choose a command —'; sel.appendChild(ph);
  if(cpFppPresets.length){
    const g=document.createElement('optgroup'); g.label='Command Presets';
    cpFppPresets.slice().sort((a,b)=>a.localeCompare(b)).forEach(n=>g.appendChild(cpMkOpt('preset',n)));
    sel.appendChild(g);
  }
  const g2=document.createElement('optgroup'); g2.label = cpFppPresets.length ? 'FPP Commands (advanced)' : 'FPP Commands';
  cpFppCmdNames.slice().sort((a,b)=>a.localeCompare(b)).forEach(n=>g2.appendChild(cpMkOpt('cmd',n)));
  sel.appendChild(g2);
  sel.value = want || '';
  if((want||'') && sel.value !== want){            // saved value not in the lists - keep it anyway
    const i=want.indexOf(':'); const type=want.slice(0,i)==='preset'?'preset':'cmd'; const name=want.slice(i+1);
    sel.appendChild(cpMkOpt(type, name)); sel.value=want;
  }
  sel.dataset.value = sel.value;
  cpSyncArgsVisibility(sel);
  sel.onchange = () => { sel.dataset.value = sel.value; cpSyncArgsVisibility(sel); };
}
// Re-populate every row's dropdown once a list arrives, preserving each choice.
function cpRefreshCmdSelects(){
  document.querySelectorAll('#cpCmdBody .f-cmd').forEach(sel => cpFillCmdSelect(sel, sel.value || sel.dataset.value || ''));
}

function cpRow(c = {}) {
  const tr = document.createElement('tr');
  const cell = (html) => { const td = document.createElement('td'); td.innerHTML = html; return td; };
  tr.appendChild(cell('<input type="text" class="f-label">'));
  tr.appendChild(cell('<input type="text" class="f-id">'));
  tr.appendChild(cell('<select class="f-cmd"></select>'));
  tr.appendChild(cell('<input type="text" class="f-args" placeholder="[]">'));
  tr.appendChild(cell('<select class="f-mode"><option value="overlay">overlay</option><option value="interrupt">interrupt</option></select>'));
  tr.appendChild(cell('<input type="text" class="f-lane" value="default">'));
  const t7 = cell('<input type="number" class="f-cool" min="0" value="0">'); t7.className='cp-num'; tr.appendChild(t7);
  const t8 = cell('<input type="number" class="f-dur" min="0" value="0">'); t8.className='cp-num'; tr.appendChild(t8);
  const t9 = cell('<input type="checkbox" class="f-pub" checked>'); t9.className='cp-chk'; tr.appendChild(t9);
  const t10 = cell(
    '<div class="cp-imgwrap">'
    + '<img class="f-imgprev" alt="" style="display:none;width:34px;height:34px;object-fit:cover;border-radius:6px;vertical-align:middle">'
    + '<input type="hidden" class="f-img">'
    + '<input type="file" class="f-imgfile" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none">'
    + '<button type="button" class="cp-btn ghost f-imgbtn" style="padding:4px 8px;font-size:12px">Add</button>'
    + '<button type="button" class="cp-btn ghost f-imgclr" title="Remove image" style="display:none;padding:4px 8px;font-size:12px">&times;</button>'
    + '</div>');
  t10.className = 'cp-imgcell'; tr.appendChild(t10);
  tr.appendChild(cell('<button type="button" class="cp-rm" title="Remove" onclick="this.closest(\'tr\').remove()">&times;</button>'));
  tr.querySelector('.f-label').value = c.label || '';
  tr.querySelector('.f-id').value = c.id || '';
  tr.querySelector('.f-args').value = (c.args && c.args.length) ? JSON.stringify(c.args) : '';
  cpFillCmdSelect(tr.querySelector('.f-cmd'),
    c.preset ? ('preset:'+c.preset) : (c.fppCommand ? ('cmd:'+c.fppCommand) : ''));
  tr.querySelector('.f-mode').value = (c.mode === 'interrupt') ? 'interrupt' : 'overlay';
  tr.querySelector('.f-lane').value = c.lane || 'default';
  tr.querySelector('.f-cool').value = c.cooldownSec || 0;
  tr.querySelector('.f-dur').value = c.durationSec || 0;
  tr.querySelector('.f-pub').checked = (c.publicSafe !== false);
  tr.querySelector('.f-img').value = c.image || '';
  cpImgRender(tr);
  cpImgWire(tr);
  return tr;
}
function cpAddRow(c){ document.getElementById('cpCmdBody').appendChild(cpRow(c)); }

// Per-control image (premium). The stored value is the relay-relative path
// (/img/...) that register sends on; preview against the relay origin.
function cpImgRender(tr){
  const path = tr.querySelector('.f-img').value || '';
  const prev = tr.querySelector('.f-imgprev');
  const add  = tr.querySelector('.f-imgbtn');
  const clr  = tr.querySelector('.f-imgclr');
  if(path){
    // Load the preview through the plugin's own origin (img.php proxy): FPP's
    // UI sets a strict CSP (img-src 'self') that blocks loading the image
    // straight from the relay origin.
    prev.src = cpUrl('img.php') + '&src=' + encodeURIComponent(path);
    prev.style.display='inline-block'; clr.style.display='inline-block'; add.textContent='Change';
  } else {
    prev.removeAttribute('src'); prev.style.display='none'; clr.style.display='none'; add.textContent='Add';
  }
}
function cpImgWire(tr){
  const file = tr.querySelector('.f-imgfile');
  tr.querySelector('.f-imgbtn').onclick = ()=> file.click();
  tr.querySelector('.f-imgclr').onclick = ()=>{ tr.querySelector('.f-img').value=''; file.value=''; cpImgRender(tr); };
  file.onchange = async ()=>{
    const f = file.files && file.files[0]; if(!f) return;
    if(f.size > 512*1024){ msg('Image too large (max 512 KB).', true); file.value=''; return; }
    const add = tr.querySelector('.f-imgbtn'); const was = add.textContent; add.textContent='Uploading…'; add.disabled=true;
    try{
      const res = await fetch(cpUrl('upload.php'), { method:'POST', headers:{'Content-Type': f.type}, body: f });
      const d = await res.json().catch(()=>({}));
      if(res.ok && d && d.ok && d.url){ tr.querySelector('.f-img').value = d.url; cpImgRender(tr); msg('Image added — press Save, then Start.', false); }
      else { msg((d && d.message) || 'Upload failed.', true); }
    } catch(e){ msg('Upload failed: '+e, true); }
    finally { add.disabled=false; if(tr.querySelector('.f-img').value==='') add.textContent=was; file.value=''; }
  };
}

function cpCollect(){
  const rows=[];
  document.querySelectorAll('#cpCmdBody tr').forEach(tr=>{
    const v=(s)=>tr.querySelector(s);
    const label=v('.f-label').value.trim();
    const sel=v('.f-cmd'); const opt=sel.selectedOptions && sel.selectedOptions[0];
    const type=opt?opt.dataset.type:''; const name=opt?(opt.dataset.name||''):'';
    if(!label && !name) return;
    const row={ id:v('.f-id').value.trim(), label,
      mode:v('.f-mode').value, lane:v('.f-lane').value.trim()||'default',
      cooldownSec:parseInt(v('.f-cool').value||'0',10), durationSec:parseInt(v('.f-dur').value||'0',10),
      publicSafe:v('.f-pub').checked, image:v('.f-img').value.trim() };
    if(type==='preset'){ row.preset=name; row.fppCommand=''; row.args=''; }
    else { row.preset=''; row.fppCommand=name; row.args=v('.f-args').value.trim(); }
    rows.push(row);
  });
  return rows;
}

async function cpSave(){
  const fd=new FormData(document.getElementById('cpForm'));
  fd.set('locEnabled', document.getElementById('locEnabled').checked ? '1' : '');
  fd.set('tsRequired', document.getElementById('tsRequired').checked ? '1' : '');
  fd.set('commandsJson', JSON.stringify(cpCollect()));
  try{
    const res=await fetch(cpUrl('save.php'),{method:'POST',body:fd,cache:'no-store'});
    const d=await res.json();
    if(d.status==='OK'){
      if(d.relaySync==='ok') msg('Saved ✓ ('+(d.commands??0)+' commands) — pushed to the relay, changes are live.');
      else if(d.relaySync==='failed') msg('Saved ✓ ('+(d.commands??0)+' commands), but couldn’t reach the relay — run CrowdPlay → Start to sync.', true);
      else msg('Saved ✓ ('+(d.commands??0)+' commands).');
      cpUpdateEmbed(); cpRefreshStatus(); }
    else msg(d.message||'Save failed', true);
  }catch(e){ msg('Network error saving', true); }
}

// Whether the "CrowdPlay - Start" FPP Command is registered yet (set by
// cpLoadFppCommands from /api/commands). null = unknown, false = not registered
// (needs an fppd restart before it can be SCHEDULED - the buttons below work
// either way because they run the start/stop scripts directly, not via fppd).
let cpStartCmdRegistered = null;

async function cpPluginAction(file){
  try{
    const r = await fetch(cpUrl(file), {method:'POST', cache:'no-store'});
    return await r.json();
  }catch(e){ return { ok:false, message:'request failed — is FPPD running?' }; }
}
async function cpStart(){
  const d = await cpPluginAction('start.php');
  if(d && d.ok){
    let m = 'Started ✓ — ' + (d.message || 'running.');
    if(cpStartCmdRegistered === false){
      m += '  Tip: to schedule a start before your show, restart FPPD once so the “CrowdPlay - Start” command registers.';
    }
    msg(m, false);
    cpRefreshStatus();
  } else {
    msg('Start failed: ' + ((d && d.message) || 'unknown error'), true);
  }
}
async function cpStop(){
  const d = await cpPluginAction('stop.php');
  msg((d && d.ok) ? 'Stopped ✓' : ('Stop failed: ' + ((d && d.message) || 'unknown error')), !(d && d.ok));
}

function cpUseMyLocation(){
  if(!navigator.geolocation){ msg('This browser has no geolocation.', true); return; }
  // FPP's settings page is served over plain HTTP; browsers only expose
  // geolocation on a secure context (HTTPS or localhost), so this button
  // can't work from a LAN IP. Guide the user to manual entry instead.
  if(window.isSecureContext === false){
    msg('Your browser blocks location on this non-HTTPS page. Enter latitude/longitude by hand below — use the "look it up" link.', true);
    return;
  }
  msg('Getting location…');
  navigator.geolocation.getCurrentPosition(
    p=>{ document.getElementById('locLat').value=p.coords.latitude.toFixed(6);
         document.getElementById('locLon').value=p.coords.longitude.toFixed(6); msg('Location captured ✓'); },
    err=>{
      const m = (err&&err.code===1)?'permission denied':(err&&err.code===2)?'position unavailable':(err&&err.code===3)?'timed out':'unknown error';
      msg('Could not get location ('+m+'). On a non-HTTPS page browsers often block this — enter latitude/longitude by hand below.', true);
    },
    {enableHighAccuracy:true,timeout:10000});
}

// Pull the show's coordinates from FPP Settings > Localization. The plugin page
// is served BY FPP, so these requests are same-origin and work over plain HTTP
// (unlike browser geolocation). FPP returns {name,value,...}; lat/lon are .value.
function cpFetchFppLatLon(){
  return Promise.all([
    fetch('/api/settings/Latitude',{cache:'no-store'}).then(function(r){return r.ok?r.json():null;}).catch(function(){return null;}),
    fetch('/api/settings/Longitude',{cache:'no-store'}).then(function(r){return r.ok?r.json():null;}).catch(function(){return null;})
  ]).then(function(a){
    var lat=parseFloat(a[0]&&a[0].value), lon=parseFloat(a[1]&&a[1].value);
    if(!isFinite(lat)||!isFinite(lon)||(lat===0&&lon===0)) return null;
    return {lat:lat, lon:lon};
  });
}
function cpUseFppLocation(){
  msg('Reading FPP location…');
  cpFetchFppLatLon().then(function(c){
    if(!c){ msg('Could not read FPP’s location. Set it in FPP Settings → Localization, or enter coordinates by hand.', true); return; }
    document.getElementById('locLat').value=c.lat.toFixed(6);
    document.getElementById('locLon').value=c.lon.toFixed(6);
    msg('Filled from FPP Settings → Localization ✓');
  });
}
// On load, if the show has no coordinates yet, seed them from FPP's Localization
// so the gate is centered correctly without the owner hunting for lat/lon. Only
// fills empty fields - never overwrites coordinates the owner already entered.
(function(){
  var la=document.getElementById('locLat'), lo=document.getElementById('locLon');
  if(la&&lo&&!la.value.trim()&&!lo.value.trim()){
    cpFetchFppLatLon().then(function(c){
      if(c&&!la.value.trim()&&!lo.value.trim()){
        la.value=c.lat.toFixed(6); lo.value=c.lon.toFixed(6);
      }
    });
  }
})();

function cpEmbedBase(){ return document.getElementById('relayUrl').value.trim().replace(/\/+$/,''); }
// Mirror save.php's id derivation so the embed's cmd id matches what the relay
// stores (id if given, else a slug of the label).
function cpCmdId(c){
  let id=(c.id||'').trim();
  if(!id) id=(c.label||'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
  return id;
}
// One <iframe> per public-safe control, each firing just that command.
function cpEmbedPerCommand(){
  const relay=cpEmbedBase(), show=document.getElementById('showId').value.trim();
  if(!relay || !show) return '(set a Show name, add a control, then Save)';
  const rows=cpCollect().filter(c=>c.publicSafe && cpCmdId(c) && c.label);
  if(!rows.length) return '(add at least one public control above, then Save)';
  const showE=encodeURIComponent(show);
  return rows.map(c=>{
    const id=encodeURIComponent(cpCmdId(c));
    return '<!-- '+c.label+' -->\n<iframe src="'+relay+'/widget?show='+showE+'&cmd='+id+'"\n        width="240" height="96" style="border:0" allow="geolocation"></iframe>';
  }).join('\n\n');
}
// All controls stacked in one widget.
function cpEmbedCode(){
  const relay=cpEmbedBase(), show=encodeURIComponent(document.getElementById('showId').value.trim());
  return (relay && show)
    ? '<iframe src="'+relay+'/widget?show='+show+'"\n        width="320" height="420" style="border:0"\n        allow="geolocation"></iframe>'
    : '(set a Show name and Save)';
}
function cpUpdateEmbed(){
  document.getElementById('cpEmbed').value = cpEmbedPerCommand();
  const a=document.getElementById('cpEmbedAll'); if(a) a.value = cpEmbedCode();
}
function cpCopyEmbed(which){
  const t=document.getElementById(which||'cpEmbed'); t.select();
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
    const names=[];
    (Array.isArray(list)?list:[]).forEach(c=>{ const name=(typeof c==='string')?c:(c&&c.name);
      if(name) names.push(name); });
    cpFppCmdNames = names;
    cpStartCmdRegistered = names.includes('CrowdPlay - Start');
    cpRefreshCmdSelects();
  }catch(e){}
}

// Saved FPP Command Presets (build them in FPP with dropdowns, then pick one
// here - no JSON). Loaded from FPP's commandPresets.json.
async function cpLoadPresets(){
  try{
    const res=await fetch('/api/configfile/commandPresets.json',{cache:'no-store'});
    const data=await res.json();
    const cmds=(data && Array.isArray(data.commands)) ? data.commands : [];
    cpFppPresets = cmds.map(p=>p && p.name).filter(Boolean);
    cpRefreshCmdSelects();
  }catch(e){}
}

function cpEsc(s){ const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }
async function cpLoadStats(){
  const el=document.getElementById('cpStats'); el.textContent='Loading…';
  try{
    const r=await fetch(cpUrl('stats.php'),{cache:'no-store'});
    const d=await r.json();
    if(d.error){
      el.textContent = d.error==='not-configured' ? 'Save your show first, then presses will show up here.'
        : (d.error==='relay-unreachable' ? 'Relay not reachable from this player.' : 'Could not load analytics.');
      return;
    }
    const cap = d.hourlyCap ? (d.lastHourTotal+' / '+d.hourlyCap+' this hour') : (d.lastHourTotal+' this hour (uncapped)');
    let html='<div style="margin-bottom:10px"><b style="text-transform:capitalize">'+cpEsc(d.tier||'free')+'</b> tier &middot; '+cap+'</div>';
    if(!d.commands || !d.commands.length){ html+='<div class="cp-muted">No presses yet.</div>'; }
    else{
      html+='<table class="cp-stats"><thead><tr><th>Control</th><th style="text-align:right">Total</th><th style="text-align:right">Last hour</th></tr></thead><tbody>';
      d.commands.forEach(c=>{ html+='<tr><td>'+cpEsc(c.label)+'</td><td class="n">'+(c.total|0)+'</td><td class="n">'+(c.lastHour|0)+'</td></tr>'; });
      html+='</tbody></table>';
    }
    el.innerHTML=html;
  }catch(e){ el.textContent='Could not load analytics.'; }
}

(EXISTING.length ? EXISTING : [{}]).forEach(cpAddRow);
cpLoadFppCommands();
cpLoadPresets();
cpUpdateEmbed();
cpLoadStats();
// Relay URL is fixed (hosted for everyone) - only the show name affects the embed.
document.getElementById('showId').addEventListener('input', ()=>{ cpUpdateEmbed(); cpRefreshStatus(); });
// Keep the per-control embeds in sync as labels/ids/public toggles change.
document.getElementById('cpCmdBody').addEventListener('input', cpUpdateEmbed);
document.getElementById('cpCmdBody').addEventListener('change', cpUpdateEmbed);

// --- First-run guided walkthrough (spotlight + popup over this page) ---
const CP_TOUR_STEPS = [
  { selector:'.cp-hero', title:'Welcome to CrowdPlay',
    text:"Let your show's visitors trigger effects from a button on your own site — without exposing this player to the internet. Here's the 60-second setup." },
  { selector:'#cpCardRelay', title:'1. Name your show',
    text:"Good news — you're already connected. CrowdPlay is hosted for you, so there's nothing to deploy. Just give your show a name (it becomes part of your embed link). Paste a license key to lift the free-tier limit, or leave it blank to start free." },
  { selector:'#cpCardLoc', title:'2. Location (optional)',
    text:'Want only people AT the show to play? Turn this on, drop your location, and set a radius. Leave it off to let anyone with the link join.' },
  { selector:'#cpCardCmd', title:'3. Choose what they can trigger',
    text:'Add each FPP command you want to expose — only these can run; the raw command never leaves this player. Set a cooldown, a lane so repeats for one prop queue up, and whether it’s public.' },
  { selector:'#cpCardStyle', title:'4. Make it yours',
    text:'With a license key, set the button colors and a headline so the widget matches your site. Without one, the widget uses the default look with a small “Powered by CrowdPlay” badge.' },
  { selector:'#cpCardEmbed', title:'5. Put it on your site',
    text:'Each control is its own button — copy one snippet per control and place them anywhere, or grab all buttons as a single widget. Works even where custom scripts are stripped.' },
  { selector:'#cpCardStats', title:'6. See what lands',
    text:'Watch how many times each control gets pressed — live counts from the relay. Handy for spotting the crowd favorites.' },
  { selector:'.cp-actions', title:'7. Go live',
    text:'Save, then press Start now to go live immediately — your controls light up in the widget within seconds. To schedule CrowdPlay to start automatically before your show, restart FPPD once (FPP shows a “restart” banner right after you install the plugin) so the “CrowdPlay - Start” command is available to the scheduler. That’s it — the show is in their hands.' }
];
let cpTourIndex = -1, cpTourReposition = null;
function cpTourBuild(){
  if(document.getElementById('cp-tour-popup')) return;
  // Append inside #cpWrap so the fixed-position tour elements inherit the
  // scoped, theme-aware --cp-* color tokens (which aren't defined on <body>).
  const root=document.getElementById('cpWrap')||document.body;
  const hl=document.createElement('div'); hl.id='cp-tour-highlight'; root.appendChild(hl);
  const arrow=document.createElement('div'); arrow.id='cp-tour-arrow'; root.appendChild(arrow);
  const p=document.createElement('div'); p.id='cp-tour-popup';
  p.innerHTML='<div class="t-of" id="cp-tour-of"></div><div class="t-title" id="cp-tour-title"></div>'+
    '<div class="t-text" id="cp-tour-text"></div><div class="t-row">'+
    '<button class="t-skip" id="cp-tour-skip">Skip</button><span class="spacer"></span>'+
    '<button id="cp-tour-back">Back</button><button class="t-next" id="cp-tour-next">Next</button></div>';
  root.appendChild(p);
  document.getElementById('cp-tour-back').addEventListener('click', ()=>cpTourGo(cpTourIndex-1));
  document.getElementById('cp-tour-next').addEventListener('click', ()=>cpTourGo(cpTourIndex+1));
  document.getElementById('cp-tour-skip').addEventListener('click', cpTourEnd);
}
function cpTourStart(){ cpTourBuild(); cpTourGo(0); }
function cpTourGo(i){
  if(i<0) return;
  if(i>=CP_TOUR_STEPS.length){ cpTourEnd(); return; }
  cpTourIndex=i; const step=CP_TOUR_STEPS[i];
  const target=document.querySelector(step.selector);
  if(!target){ cpTourGo(i+1); return; }
  document.getElementById('cp-tour-of').textContent='Step '+(i+1)+' of '+CP_TOUR_STEPS.length;
  document.getElementById('cp-tour-title').textContent=step.title;
  document.getElementById('cp-tour-text').textContent=step.text;
  document.getElementById('cp-tour-back').disabled=(i===0);
  document.getElementById('cp-tour-next').textContent=(i===CP_TOUR_STEPS.length-1)?'Finish':'Next';
  target.scrollIntoView({behavior:'smooth', block:'center'});
  clearTimeout(cpTourReposition); cpTourReposition=setTimeout(cpTourPosition, 280);
}
function cpTourPosition(){
  const step=CP_TOUR_STEPS[cpTourIndex]; const target=step && document.querySelector(step.selector);
  if(!target) return;
  const r=target.getBoundingClientRect(), pad=6;
  const hl=document.getElementById('cp-tour-highlight');
  hl.style.top=(r.top-pad)+'px'; hl.style.left=(r.left-pad)+'px';
  hl.style.width=(r.width+pad*2)+'px'; hl.style.height=(r.height+pad*2)+'px';
  const popup=document.getElementById('cp-tour-popup'), arrow=document.getElementById('cp-tour-arrow');
  const popupW=popup.offsetWidth||320;
  const spaceBelow=window.innerHeight-r.bottom;
  const below=spaceBelow>=200 || spaceBelow>=r.top;
  const left=Math.max(8, Math.min(r.left, window.innerWidth-popupW-8));
  popup.style.left=left+'px';
  if(below){ popup.style.top=(r.bottom+pad+12)+'px'; popup.style.bottom=''; }
  else{ popup.style.bottom=(window.innerHeight-r.top+pad+12)+'px'; popup.style.top=''; }
  const aLeft=Math.max(left+14, Math.min(r.left+r.width/2-9, left+popupW-26));
  arrow.style.left=aLeft+'px';
  arrow.className=below?'cp-arrow-above':'cp-arrow-below';
  if(below){ arrow.style.top=(r.bottom+pad)+'px'; arrow.style.bottom=''; }
  else{ arrow.style.bottom=(window.innerHeight-r.top+pad)+'px'; arrow.style.top=''; }
}
function cpTourEnd(){
  cpTourIndex=-1;
  ['cp-tour-highlight','cp-tour-arrow','cp-tour-popup'].forEach(id=>{ const el=document.getElementById(id); if(el) el.remove(); });
  fetch(cpUrl('mark_onboarding_seen.php'), {method:'POST', cache:'no-store'}).catch(()=>{});
}
window.addEventListener('resize', ()=>{ if(cpTourIndex>=0) cpTourPosition(); });
window.addEventListener('scroll', ()=>{ if(cpTourIndex>=0) cpTourPosition(); }, true);
const cpReplay=document.getElementById('cpReplayTour');
if(cpReplay) cpReplay.addEventListener('click', e=>{ e.preventDefault(); cpTourStart(); });

if(!<?= $onboardingSeen ? 'true' : 'false' ?> && <?= $tourEnabled ? 'true' : 'false' ?>){
  setTimeout(cpTourStart, 450);
}
</script>
