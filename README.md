# ⚡ CrowdPlay — Put the Show in Their Hands

### *FPP Plugin: Let Your Audience Trigger Your Show — Live*

[![FPP Compatible](https://img.shields.io/badge/FPP-9.x%20%7C%2010.x%2B-red?style=for-the-badge&logo=raspberry-pi)](https://github.com/FalconChristmas/fpp)
[![Platform](https://img.shields.io/badge/Platform-Pi%20%7C%20BBB%20%7C%20Debian-c51a4a?style=for-the-badge&logo=raspberry-pi)](https://github.com/FalconChristmas/fpp)
[![Powered by Cloudflare](https://img.shields.io/badge/Relay-Cloudflare%20Workers-F38020?style=for-the-badge&logo=cloudflare)](https://workers.cloudflare.com/)
[![No Inbound Ports](https://img.shields.io/badge/Security-Zero%20Inbound%20Ports-2ea44f?style=for-the-badge&logo=letsencrypt)](#-the-security-story-why-this-is-safe)
[![License](https://img.shields.io/badge/License-Noncommercial-green?style=for-the-badge)](LICENSE)
[![GitHub](https://img.shields.io/badge/GitHub-focusedonsound-181717?style=for-the-badge&logo=github)](https://github.com/focusedonsound/fpp-CrowdPlay)

---

> [!IMPORTANT]
> **📣 CrowdPlay isn't in the FPP Plugin Manager list _yet_.** It's pending listing in the official FPP plugin directory ([FalconChristmas/fpp-data#313](https://github.com/FalconChristmas/fpp-data/pull/313)). Until that merges, install it **manually from this repo** — it takes about a minute. Jump to **[Installation](#-installation)** for the steps.

---

> **Turn every spectator into part of the show. CrowdPlay lets the people watching your display reach out and *make something happen* — wave a tree, fire a snow machine, launch a finale — from a button you drop onto your own website or your Remote Falcon page. One tap and the magic is in their hands… and your show player never touches the open internet. ⚡**

<p align="center">
  <img src="https://raw.githubusercontent.com/focusedonsound/fpp-CrowdPlay/master/icon.png"
       alt="CrowdPlay — tap the button, trigger the show"
       width="200" style="border-radius:16px;" />
  <br/>
  <em>Tap the button. Trigger the show. That's CrowdPlay.</em>
</p>

---

## 🎄 Table of Contents

- [What Is CrowdPlay?](#-what-is-crowdplay)
- [Feature Overview](#-feature-overview)
- [How It Works (Architecture)](#-how-it-works-architecture)
- [The Visitor Press Flow](#-the-visitor-press-flow)
- [The Security Story (Why This Is Safe)](#-the-security-story-why-this-is-safe)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Embedding the Button](#-embedding-the-button-anywhere)
- [Location Gate (Be-Near-the-Show)](#-location-gate-be-near-the-show)
- [Bot Protection](#-bot-protection)
- [Free vs. Licensed](#-free-vs-licensed)
- [Config Reference](#-config-reference)
- [Credits](#-credits)
- [License](#-license)

---

## ⚡ What Is CrowdPlay?

**CrowdPlay** is a Falcon Player plugin that hands the controls of your light show to your audience — safely.

Hosted viewer pages (and Remote Falcon) are fantastic, but they only let visitors *request* or *vote* on whole sequences that queue up and play later. CrowdPlay fills the missing gap: the **fire-now, non-disruptive moment**. A visitor taps a button and *right now* an effect overlays onto a prop, a snow machine kicks on, or your finale rips — layered on top of your running show without interrupting it.

You embed a button — on your **own website**, your **Remote Falcon** viewer page, anywhere that takes an `<iframe>` — and that's it. Behind the scenes the plugin holds a single **outbound** connection to a hosted relay and pulls down only the commands **you** explicitly allowed. Your show player is **never reachable from the internet** — no port forwarding, no exposed hostname, no tunnel pointing at your box.

It's built by a Christmas lighting enthusiast, for Christmas lighting enthusiasts: install from the FPP Plugin Manager, name your show, pick the commands to expose, paste one embed snippet, hit Start. Go let the crowd play your show. ⚡

---

## ✨ Feature Overview

| Feature | Description |
|---|---|
| 🎮 Audience Triggers | Visitors fire your FPP **Commands** from a button you embed |
| 🧩 Embed Anywhere | Self-contained `<iframe>` widget — your site, Remote Falcon, any page |
| 🔒 Zero Inbound Ports | Player polls **outbound** only — never exposed to the internet |
| ✅ Allowlist-Only | The relay can only run commands you explicitly configured — never arbitrary ones |
| 🎚️ Per-Command Modes | **Overlay** (layer on top, show untouched) or **Interrupt** (take over) |
| 🕹️ One Button Per Control | Each exposed command is independently embeddable |
| ⏱️ Smart Rate Limiting | Per-command cooldowns, per-IP burst limits, hourly caps, duplicate suppression |
| 🔗 Shared Cooldown Groups | Props that share a machine (fog/bubbles) lock together; every viewer sees the live countdown |
| 📍 Location Gate | Optional — require visitors to be physically near the show (GPS + geofence) |
| 🛡️ Bot Protection | Optional per-show Cloudflare Turnstile check |
| 🎨 Themed Widget | Custom button colors, headline, and badge removal (licensed) |
| 📊 Per-Show Analytics | See which controls get pressed, and how often |
| 🚀 Zero-Setup Hosting | Auto-generated Show ID + private key — a fresh install just works |
| 🧭 Guided Onboarding | Built-in walkthrough tour right on the settings page |

---

## 🏗️ How It Works (Architecture)

```
   ┌──────────────┐        taps a button        ┌────────────────────────────┐
   │   Visitor's  │ ──────────────────────────► │  Your webpage / Remote      │
   │    phone     │                             │  Falcon page (iframe widget)│
   └──────────────┘                             └──────────────┬─────────────┘
                                                               │  POST /api/trigger
                                                               ▼
                               ┌──────────────────────────────────────────────┐
                               │            CrowdPlay Relay                    │
                               │     (Cloudflare Worker + Durable Object)      │
                               │                                               │
                               │  • per-show command queue + resource lanes    │
                               │  • cooldowns · per-IP burst · hourly caps     │
                               │  • optional Turnstile bot check               │
                               │  • optional location (geofence) gate          │
                               │  • license validation (tier → limits)         │
                               └──────────────────────────────┬────────────────┘
                                                              ▲│
                   outbound long-poll / WebSocket ONLY        ││  queued command id
                   (NO inbound ports on the player)           │▼
                               ┌──────────────────────────────────────────────┐
                               │            fpp-CrowdPlay plugin               │
                               │  • registers your allowlist on start          │
                               │  • long-polls the relay for queued presses    │
                               │  • runs ONLY allowlisted commands locally      │
                               └──────────────────────────────┬────────────────┘
                                                              │  POST /api/command
                                                              ▼
                               ┌──────────────────────────────────────────────┐
                               │         FPP  →  your lights / props           │
                               │   (Effect Start · Overlay Model · Command     │
                               │    Preset · snow machine · finale · …)        │
                               └──────────────────────────────────────────────┘
```

| Repo | What |
|---|---|
| **`fpp-CrowdPlay`** (this) | The FPP plugin: settings UI, outbound poll loop, local command execution + allowlist. |
| **`fpp-CrowdPlay-relay`** | The Cloudflare Worker + Durable Object relay: queue, rate limits, Turnstile, location gate, license validation, and the embeddable widget. |

---

## 🎬 The Visitor Press Flow

1. A visitor opens your page and taps a CrowdPlay button (on your site or Remote Falcon).
2. The widget `POST`s the request to the relay — with Turnstile and/or GPS coordinates if you enabled them.
3. The relay checks the rules: is the command allowed? off cooldown? under the IP/hourly limits? near enough? human?
4. If it passes, the press is **queued** in the show's Durable Object and the visitor sees a live status (queued / in-progress / cooldown countdown).
5. Your plugin — already holding an **outbound** long-poll — pulls the queued command id.
6. The plugin matches it against your **local allowlist** and runs the real FPP command via `POST /api/command`.
7. The lights react **live**, the lane is held for the effect's duration, then released so the next press can fire.

No inbound connection ever reaches your player. The relay can only ever name a command you configured — it can never make your box run something arbitrary.

---

## 🔒 The Security Story (Why This Is Safe)

This is the heart of CrowdPlay, so it's worth being explicit:

- **No port forwarding. No exposed hostname. No tunnel to your box.** The plugin makes *outbound* connections only and waits for work.
- **Allowlist-enforced locally.** Your player trusts only its own configured command list. The relay sends a command *id*; the plugin maps that id to the real FPP command. A command you didn't configure simply cannot run.
- **Owned by a private key.** Each show mints a private `showKey` on first claim (trust-on-first-use). Only your player can register/poll/complete for your show; the public Show ID in the embed URL can't be used to take it over.
- **The relay does the dirty work.** Bot protection, rate limiting, duplicate suppression, and the geofence all run at the edge — abuse is filtered *before* it ever becomes a queued command.

---

## 📦 Installation

> [!NOTE]
> CrowdPlay is **not in the Plugin Manager's known-plugins list yet** (listing PR [fpp-data#313](https://github.com/FalconChristmas/fpp-data/pull/313) is pending). Install it manually from this repository using the steps below. Once it's listed, it'll appear in the normal plugin list and this manual step won't be needed.

**Manual install (until it's listed):**

1. In FPP, open **Content Setup → Plugin Manager**.
2. Scroll to the **manual install** field (the "install a plugin not listed above" / GitHub repository box at the bottom of the page).
3. Enter this repository and click **Install**:
   ```
   https://github.com/focusedonsound/fpp-CrowdPlay
   ```
   (`focusedonsound/fpp-CrowdPlay` also works.)
4. When it finishes, open the **CrowdPlay** page from the FPP menu.
5. The first visit auto-generates your **Show name/ID** and a private key, and pre-fills the hosted relay — a fresh install works with zero setup.
6. Follow the built-in **walkthrough tour**: name your show, add the commands to expose, copy the embed snippet, and click **Start**.

<details>
<summary>Alternative: install from the command line</summary>

SSH into your FPP device and clone into the plugins directory:

```bash
cd /home/fpp/media/plugins
git clone https://github.com/focusedonsound/fpp-CrowdPlay.git
/home/fpp/media/plugins/fpp-CrowdPlay/fpp_install.sh
```

Then restart FPPD (or reboot) so the **CrowdPlay – Start / Stop** commands register.
</details>

> **Tip:** scheduled **CrowdPlay – Start / Stop** commands register after a one-time FPPD restart. The **Start now / Stop now** buttons on the settings page work immediately, even before that restart.

---

## ⚙️ Configuration

Everything lives on the plugin's settings page:

- **Your show** — show name, optional license key (hosted relay is pre-filled for you).
- **Commands** — the allowlist editor. For each control you expose, set:
  - a **label** (what the visitor sees),
  - a **Command Preset** *or* an FPP **Command** + args,
  - an **execution mode** (overlay / interrupt),
  - a **group/lane**, **cooldown**, **duration**, and whether it's **public-safe** (shown to visitors).
- **Location gate**, **Bot protection**, **Widget appearance**, and **Analytics** — see below.

Hit **Save** and your settings apply to the relay instantly — no Stop/Start needed.

---

## 🧩 Embedding the Button (Anywhere)

The settings page generates ready-to-paste snippets. Each public-safe control gets its own self-contained `<iframe>`, so you can place buttons independently across your page:

```html
<iframe
  src="https://crowdplay-relay.nscilingo.workers.dev/widget?show=YOUR_SHOW_ID&cmd=wave"
  width="240" height="96" style="border:0;"
  allow="geolocation"
  title="CrowdPlay — Wave the tree"></iframe>
```

- Works on your own website **and** on **Remote Falcon** (RF keeps `<iframe>` embeds).
- `allow="geolocation"` lets the location gate work when you enable it.
- Drop one iframe per control, or embed the all-in-one widget — your call.

---

## 📍 Location Gate (Be-Near-the-Show)

Want only the people *actually standing at your display* to trigger it? Turn on the **location gate**:

- The widget reads the visitor's **GPS** (browser Geolocation, needs HTTPS — the hosted relay is HTTPS).
- The relay checks the distance against your show's coordinates, server-side (haversine).
- A visitor is allowed when `distance ≤ radius + min(their GPS accuracy, your tolerance)` — it adapts to real-world GPS drift while capping spoofed accuracy claims.
- Pull your show's center straight from **FPP Settings → Localization** with one click, or enter lat/lon manually.
- Coordinates are used for the distance check only — they are **not retained**.

---

## 🛡️ Bot Protection

CrowdPlay ships with an **optional per-show Cloudflare Turnstile** check (off by default). The relay already hard-limits every press (cooldowns, per-IP burst, hourly caps, duplicate suppression, and the geofence), so most shows don't need it — but if you want an extra human-verification layer, flip it on from the **Bot protection** card.

---

## 🎟️ Free vs. Licensed

CrowdPlay is **fully usable for free.** Without a license key a show can expose up to **3 controls** and take **30 presses/hour** (shared across all controls) — enough to light up a yard and delight a crowd.

An **optional** license key, validated at the relay, raises those limits and unlocks the extras:

| | Free | Licensed |
|---|---|---|
| Exposed controls | up to 3 | more |
| Presses per hour | 30 | raised |
| Themed widget (colors, headline) | — | ✅ |
| "Powered by CrowdPlay" badge | shown | removable |
| Per-show analytics | — | ✅ |

Everything about obtaining a license lives **off the plugin** — the plugin itself carries no pricing, promotion, or payment links, per FPP's plugin guidelines. See the project website for details.

---

## 🗂️ Config Reference

`plugindata/fpp-CrowdPlay/crowdplay.json`:

```jsonc
{
  "relayUrl": "",                                 // blank = built-in hosted relay
  "showId": "",                                   // auto-generated on first run
  "licenseKey": "",                               // blank = Free tier
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
  },
  "turnstileRequired": false
}
```

---

## 🙌 Credits

Built by **Nick Scilingo** (focusedonsound) for the FPP / Christmas lighting community.
Part of the `fpp-*` plugin family alongside EncoreRadio, SLED, and more.

---

## 📄 License

Free for personal, hobbyist, and noncommercial use under the
[PolyForm Noncommercial License 1.0.0](LICENSE).

Using this in a commercial or paid-event display? A separate commercial license is
required — contact **license.request@christmasinboontontwp.com** to arrange one.
