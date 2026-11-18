# Release Notes

## v1.0.0-rc1 — Release Candidate 1

First public release candidate of **CrowdPlay** — *put the show in their hands.*

CrowdPlay lets the people watching your light show trigger your FPP Commands from
a button you embed on your own website or Remote Falcon page — without ever
exposing your show player to the internet. The plugin never opens an inbound port:
it polls a hosted relay **outbound** and runs only the commands you explicitly
allow.

### Highlights

- **Zero setup.** The relay is hosted for you. On install the plugin generates a
  unique Show ID and a private Show Key automatically — there's nothing to deploy
  and no port to open. Just name your show and pick your commands.
- **You choose exactly what visitors can do.** Only the commands you add to the
  allowlist can be triggered. The raw FPP command and its arguments stay on your
  player — the relay only ever sees the label, cooldown, and duration.
- **Execution modes per command.** Overlay an effect on top of the running show
  (main sequence untouched), or interrupt/take over, RF-style — your choice, per
  command, with per-command cooldowns and concurrency lanes.
- **Embed anywhere.** Copy a ready-made `<iframe>` snippet per control, or an
  all-in-one block, and drop it into your site or Remote Falcon page.
- **Abuse protection built in.** Visitor presses are gated at the relay with
  invisible bot protection (Cloudflare Turnstile) and per-IP + per-show rate
  limiting, so one person (or a bot) can't spam your show.
- **Optional location gate.** Require a visitor to be within a set distance of the
  show before their press counts — checked at the relay, coordinates not retained.
- **Guided onboarding.** A built-in walkthrough tour walks first-time operators
  through naming the show, choosing controls, embedding, and going live.
- **Themed widget & analytics** (with a license key): match the buttons to your
  site and see how many presses each control is getting.

### Free to use

The hosted relay is free to use (up to 3 controls and 30 presses/hour per show).
A license key is **optional** and raises those limits; details are on the project
website.

### Installing

1. Install **CrowdPlay** from FPP's Plugin Manager.
2. When prompted (or automatically on first install), **restart FPPD** so the
   *CrowdPlay – Start* / *Stop* commands register.
3. Open the CrowdPlay page, add the commands you want to expose, and copy the embed
   snippet onto your site.
4. Schedule or run **CrowdPlay – Start** shortly before your show, and
   **CrowdPlay – Stop** when it ends.

### Notes

- Requires FPP 9+ (tested on current FPP 10 and nightly).
- Works with any controller/pixel setup — the widget is RF-agnostic (it triggers
  FPP Commands, not pixels directly).
