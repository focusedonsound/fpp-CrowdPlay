# fpp-CrowdPlay

**Put the show in their hands.** 🎮

An FPP plugin that lets your website visitors trigger your FPP **Commands** — overlay
an effect on a prop, run a snow machine, fire a finale — from a button you embed in
your own site or your Remote Falcon viewer page, **without ever exposing your show
player to the internet**.

> Design spec (living doc): https://claude.ai/artifact/4FyR6sgjrbQULN6x6KeuVH

## How it works

```
Visitor's browser ──► Owner's webpage / RF page (embedded iframe widget)
        │
        ▼
   CrowdPlay relay  ◄── Turnstile + rate limiting + license check (Cloudflare Worker + Durable Object)
   (per-show command queue, resource lanes)
        ▲
        │  outbound long-poll / WebSocket only  (no inbound ports on the player)
   fpp-CrowdPlay plugin  ──► FPP local command API (POST /api/command)
```

The plugin **never opens an inbound port**. It holds an outbound long-poll (or a
hibernatable WebSocket) to the relay, pulls queued visitor commands, and runs only the
commands you have **explicitly allowlisted** — the relay can never make the box run an
arbitrary command.

## Repos

| Repo | What |
| --- | --- |
| `fpp-CrowdPlay` (this) | The FPP plugin: settings UI, outbound poll loop, local command execution + allowlist. |
| `fpp-CrowdPlay-relay` (planned) | The Cloudflare Worker + Durable Object relay: queue, rate limits, Turnstile, location gate, license validation, the embeddable widget. |

Licensing reuses the pattern from `fpp-EncoreRadio-license-server` (Cloudflare Worker + D1).

## Execution modes (per command)

- **Overlay / concurrent** (default) — `Effect Start` / `FSEQ Effect Start` with `bg=true`,
  or an `Overlay Model` command: layers on top of the running show, main sequence untouched,
  auto-stops when the effect ends.
- **Insert / interrupt** — takes over the show (RF-style), for when you want it to.

## Licensing

CrowdPlay is fully usable for free. Without a license key, a show can expose up to
**3 controls** and take **30 presses/hour** (shared across all controls). An
**optional** license key, validated at the relay, raises those limits and enables
the themed widget, badge removal, and per-show analytics. Per FPP's plugin
guidelines the plugin UI carries no pricing or promotion — pricing and sign-up live
off the plugin, on the project website (`upgradeUrl`).

**How keys are issued (architecture).** Keys live in the `crowdplay-licenses` D1
and are validated by the relay. Fulfilment reuses `fpp-EncoreRadio-license-server`:
its PayPal IPN handler mints a key into that D1 and emails it over SMTP, routed to
CrowdPlay by the PayPal button's item name / id. `scripts/mint-key.sh` (relay repo)
issues a manual or comp key directly.

## Config (`plugindata/fpp-CrowdPlay/crowdplay.json`)

```jsonc
{
  "relayUrl": "https://<your-show>.<you>.workers.dev",
  "upgradeUrl": "https://christmasinboontontwp.com/crowdplay-signup/",  // "Get a key" link
  "showId": "",
  "licenseKey": "",                                                 // blank = Free tier
  "fppApi": "http://127.0.0.1/api/command",
  "poll": { "mode": "longpoll", "holdSec": 25 },
  "commands": [
    {
      "id": "wave",
      "label": "Wave the tree",
      "fppCommand": "FSEQ Effect Start",
      "args": ["wave.fseq", false, true, true],   // effect, loop, bg, ifNotRunning
      "mode": "overlay",
      "lane": "prop-tree",
      "cooldownSec": 10,
      "durationSec": 0,                            // 0 = read FSEQ length; >0 = fixed hold then Stop
      "publicSafe": true
    }
  ],
  "location": {
    "enabled": false,
    "lat": null, "lon": null,
    "radiusM": 300, "toleranceM": 50,
    "onDenied": "strict"
  }
}
```

## Status

- **Design:** complete (see spec doc above).
- **Phase 0 verified on fpp-dev** (FPP 10, `POST /api/command` with `{command,args[]}`):
  `Effect Start` / `FSEQ Effect Start` support `bg` (concurrent), `loop`, and
  `ifNotRunning`; `/api/effects` is the running-effects list; content lists for the
  settings UI come from `/api/models?simple=true`, `/api/sequence`, `/api/overlays/effects`.
- **Functional end-to-end (Release Candidate 1).** The relay is deployed
  (`crowdplay-relay.nscilingo.workers.dev`) with Durable Object queue, tiered rate
  limits, Turnstile bot protection, license validation, per-command embeds, and the
  themed widget. The plugin registers on start, long-polls, and runs allowlisted
  commands locally; register/poll/complete are authenticated with a per-show key.
  Premium tiers (Free / Personal $5·yr / Commercial) are enforced, and paid keys are
  sold + auto-delivered via PayPal → the shared license server → email. Verified live
  on fpp-dev (visitor press → overlay effect) and via the deployed relay.
- **Before 1.0:** a clean-box install smoke test, a live $5 PayPal test subscription,
  and written GitHub release notes.

## License

TODO — match the license used across the `fpp-*` line.
