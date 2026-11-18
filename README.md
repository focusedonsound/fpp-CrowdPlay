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
the themed widget, badge removal, and per-show analytics. Keys are validated at the
relay against the shared license server; everything about obtaining one lives off
the plugin (the plugin carries no pricing, promotion, or payment links, per FPP's
plugin guidelines).

## Config (`plugindata/fpp-CrowdPlay/crowdplay.json`)

```jsonc
{
  "relayUrl": "",                                                   // blank = built-in hosted relay
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
- **Released (v1.0.1).** The relay is deployed
  (`crowdplay-relay.nscilingo.workers.dev`) with a Durable Object queue, tiered rate
  limits, Turnstile bot protection, license validation, per-command embeds, and the
  themed widget. The plugin registers on start, long-polls, and runs allowlisted
  commands locally; register/poll/complete are authenticated with a per-show key.
  License tiers are enforced at the relay and keys are validated against the shared
  license server. Verified live on fpp-dev (visitor press → overlay effect), via the
  deployed relay, and with a clean-box install on real hardware.

## License

TODO — match the license used across the `fpp-*` line.
