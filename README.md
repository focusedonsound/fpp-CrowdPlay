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

## Plans

One hosted relay for everyone; a license key (validated at the relay, issued by the
same license server as `fpp-EncoreRadio`) sets the tier. One key unlocks one show.

| Plan | Price | Presses/hour | Controls | Styling & analytics | Badge |
|------|-------|--------------|----------|---------------------|-------|
| **Free** | $0 | 30 (shared across all controls) | up to 3 | default look | "Powered by CrowdPlay" |
| **Personal** | $5 one-time | 300 | unlimited | custom colors + headline, per-show analytics | none |
| **Commercial** | contact | uncapped | unlimited | same as Personal | none |

Personal and Commercial unlock the same premium features; the difference is the
hourly press cap (and Commercial covers ticketed / for-profit shows). The hourly
cap is per show, counted across all of its controls.

The plugin's **Get a key** button and `upgradeUrl` point at the relay's hosted
purchase page (`/buy`), which mirrors the EncoreRadio signup flow: pick a tier,
pay (PayPal, one-time $5 for Personal), receive a key by email, paste it into the
**License key** field. Keys are minted into the `crowdplay-licenses` D1 with
`scripts/mint-key.sh` (the manual half of that flow today; a PayPal webhook can
call the same insert to automate it). The Personal buy button is wired to the
`PAYPAL_BUY_URL` Worker var — set it to a PayPal.me or hosted-button URL to go
live; until then the button routes to "contact to purchase."

## Config (`plugindata/fpp-CrowdPlay/crowdplay.json`)

```jsonc
{
  "relayUrl": "https://<your-show>.<you>.workers.dev",
  "upgradeUrl": "https://crowdplay-relay.nscilingo.workers.dev/buy",  // "Get a key" link
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
- **Phase 1 (this scaffold):** plugin skeleton — manifest, menu, commands, poll loop,
  local executor + allowlist, settings page. **Not yet functional end-to-end** (needs the
  relay API contract finalized — marked `TODO(relay)` in the code).

## License

TODO — match the license used across the `fpp-*` line.
