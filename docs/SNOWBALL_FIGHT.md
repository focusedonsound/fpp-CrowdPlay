# Snowball Fight — Design Spec

> A premium multiplayer game **mode** for fpp-CrowdPlay. Two show-site visitors lob
> snowballs at each other across the physical light display. Player 1 sets angle and
> power, a snowball arcs across the matrix / megatrees / props, and lands (hit or miss)
> on Player 2's side. Then Player 2 answers. Best of N wins.
>
> Status: **architecture, not yet built.** Core remote-overlay mechanism verified live
> on a real show (2026-11-22). Target repo: `fpp-CrowdPlay` (this repo).

---

## 1. Why this is a CrowdPlay mode, not a new plugin

Snowball Fight reuses almost all of CrowdPlay's existing plumbing:

- **Poll-out relay** (`crowdplay-relay.nscilingo.workers.dev`) — the FPP player never
  faces the internet; it polls out. Unchanged.
- **Per-show Durable Object** — already the authoritative, single-threaded per-show
  state holder. It becomes the **game server**.
- **Widget / iframe** — becomes the game controller (aim UI).
- **Turnstile, rate-limit, hourly cap, geofence/location gate** — reused as-is.
- **EncoreRadio license server + PayPal** — reused; Snowball Fight is a new **premium
  SKU** above Personal $5/yr, gated by a feature flag in the plugin.

What's genuinely new: a **match state machine** in the DO, a **WebSocket** channel to
the widgets (for instant turn handoff), and the FPP-side **Arena Coordinator** that
paints the animation across the display.

## 2. The decisive design choice: turn-based artillery

The game is **turn-based** (Scorched-Earth / Worms style), not real-time. This is a
hard requirement, not a preference:

- CrowdPlay's FPP side **polls out** — every input carries poll-interval latency.
  Twitch gameplay would feel broken; turn-based hides the latency completely because a
  player is always waiting on the *other player*, never on a frame.
- An arcing projectile with angle + power + **wind** is exactly what reads beautifully
  across lights, and it's a skill game that's fun to watch from the street.

Turn loop: `P1 aims → throw → ~1.5s arc animates → hit/miss resolves → P2 aims → …`

## 3. Show-agnostic Arena (the central abstraction)

The game must run on **any** show, knowing nothing about a specific yard. Physics runs
in a **logical 0–100 × 0–100 field**; a per-show **Arena** maps that field onto real
pixels.

### 3.1 Geometry comes from `virtualdisplaymap` (free, automatic)

FPP generates `virtualdisplaymap` from the xLights RGB Effects layout and it is
uploaded alongside sequences. It lists **every pixel in the show**:

```
# Preview Size
15018,3245
# Model: 'Frosty_The_Snowman', 450 nodes
x, y, z, absoluteChannel, channelCount, colorOrder, <flag>
```

- Preview canvas `width,height`; **y increases upward**; **z = depth** (megatrees sit
  at large negative z, set back from the street).
- Per pixel: physical `(x,y,z)` **and** the absolute FPP channel number.

So the Arena is **auto-imported**: the plugin parses this file, reconstructs the whole
layout, and the owner only has to (a) pick which models are the playfield and (b) tag
the two forts. Adjacent sub-models (e.g. a house matrix split into 17 sections) are
auto-stitched by matching x-adjacency + shared y-band.

> Nick's show (reference): canvas 15018×3245, 347 models, 33,559 pixels, channels
> 1–114,401. House matrix = `House_Matrix_Section_1..17` (800px each, one contiguous
> band). Roof = `Left Roof Matrix` + `Right roof matrix`. Flanks = `Megatree 1` (right),
> `Megatree 2` (left, deep -z). Character props already exist: `Santa`, `Frosty_The_Snowman`,
> `Penguin EFL`, `Polar Bear`, gingerbread family, `Clarice`.

### 3.2 Arena schema (draft)

```jsonc
{
  "arenaId": "default",
  "logical": { "w": 100, "h": 100 },
  "source": { "virtualdisplaymap": "<hash>", "importedAt": "..." },
  "zones": [
    {
      "id": "playfield",
      "role": "playfield",              // playfield | fort-left | fort-right | obstacle | bridge | decor
      "models": ["House_Matrix_Section_1","…","House_Matrix_Section_17"],
      "rect": { "x": 10, "y": 30, "w": 80, "h": 40 }   // placement on the logical field
    },
    { "id": "fortL", "role": "fort-left",  "models": ["Santa"],             "prop": true },
    { "id": "fortR", "role": "fort-right", "models": ["Frosty_The_Snowman"], "prop": true }
  ],
  "spawns":  { "p1": {"x":8,"y":55}, "p2": {"x":92,"y":55} },
  "wind":    { "enabled": true, "maxAbs": 20 }
}
```

The physics engine only ever touches logical coords. The **Coordinator** owns the
logical→pixel→channel→controller mapping, so the engine is fully display-independent.

## 4. Multi-controller reality & the rendering mechanism

> This is the part that was de-risked by live investigation on 2026-11-22. See the
> appendix for the raw findings.

Big shows run **MultiSync**: a fleet of FPP **remotes** each self-render their slice of
the player's global channel space. A single snowball crossing the house physically
crosses several controllers. An overlay drawn only on the player would light nothing,
because the player doesn't output those pixels.

**The enabling fact:** FPP auto-creates Pixel Overlay models from the xLights layout,
and **they exist identically on every instance, addressed in global channel space**
(`House_Matrix_Section_1` @ ch 2 everywhere, `Frosty_The_Snowman` @ ch 32876
everywhere). Setting a model's pixels only produces light on the instance that
physically **outputs** those channels. So:

1. Build a **channel → controller** routing table from `/api/fppd/multiSyncSystems`
   (each remote reports its `channelRanges` in global space).
2. To light a pixel, POST the overlay write to **the remote that owns that channel
   range**, not the player.
3. Use **Transparent** overlay state so the running show shows through — the fight
   happens mid-sequence without stopping playback.

Because a snowball occupies one small region at a time, each frame touches only 1–2
remotes — a trickle of sparse HTTP, well within budget even on a Pi 3B+ player.

> This is the same capability as **AnnouncementAssistant backlog issue #4**
> ("AA-on-player painting a remote overlay"). Building this coordinator solves Tune To's
> remote-overlay problem as a byproduct — share the implementation.

### 4.1 Topology detection (keeps it show-agnostic)

- **Topology A — centralized output** (player drives dumb controllers via E1.31/DDP, or
  a single-box show): one overlay writer on the player covers everything. Fast path.
- **Topology B — distributed multisync remotes** (Nick's show): fan-out to owning
  remotes per the routing table.

The Coordinator detects which, per channel range, from the multisync roster + active
channel-output config, and routes accordingly. B is the general case; A is an
optimization when a range is player-output.

### 4.2 Overlay write API (verified)

Coarse, whole-model (verified live, ~43 ms round-trip to a remote):

```
POST http://<owner-ip>/api/command
  {"command":"Overlay Model State","args":["<model>","Transparent"]}   // arm once
  {"command":"Overlay Model Fill", "args":["<model>","Enabled","#RRGGBB"]}
  {"command":"Overlay Model Clear","args":["<model>"]}
  {"command":"Overlay Model State","args":["<model>","Disabled"]}       // restore
```

`Fill` is whole-model — perfect for lighting a **prop** on a hit (flash all of Frosty).
For the **moving snowball**, write the model's buffer directly (verified live):

```
PUT  http://<owner-ip>/api/overlays/model/{name}/data
     {"Data": "<base64 of raw channel bytes>"}      // length = ChannelCount
GET  http://<owner-ip>/api/overlays/model/{name}/data
     -> {"data":[int,...], "rle":false, ...}          // read back / confirm
```

Overlay models **do** carry 2D geometry — the JSON keys are lowercase `width`/`height`
(e.g. `House_Matrix_Section_1` = 20×40, `StartCorner:"BL"`, `Orientation:"vertical"`,
`ChannelCount:2400`), so we can address by (x,y) within a model and also cross-reference
`virtualdisplaymap` for cross-model trajectories.

> **Verified on remote Audrey (2026-11-23):** wrote 6 specific pixels into Section_1's
> 2400-channel buffer via the base64 `PUT`; read-back matched exactly; **25 ms** for the
> full 800-pixel buffer to a remote. The entire render path (arm → transparent → write →
> read-back → clear → disable) is proven on real hardware. Note: the body must be
> `{"Data": base64}` — a plain `{"data":[ints]}` POST returns 400/404.

## 5. The Arena Coordinator (FPP-side daemon)

A persistent process (Tune-To-style), running on the player or any LAN host that can
reach the remotes:

1. **On load:** parse `virtualdisplaymap` → `{pixel: (x,y,z,globalChannel)}`; fetch the
   multisync roster → channel→controller table; pre-compute each arena model's pixel
   list + owning controller.
2. **Per shot** (from the relay/DO): compute the parabola in logical space (with wind);
   sample to a path of arena pixels; resolve hit/miss (authoritative copy also in DO).
3. **Per frame (~15–20 fps):** group lit pixels by owning controller; POST sparse
   per-controller overlay writes; clear the trailing pixels so only a short glowing
   comet + trail is lit.
4. **Forts/characters:** drive the real `Santa` / `Frosty` overlay models on their
   owning remotes for wind-up and hit reactions (whole-model `Fill`).

## 6. DO match machine (relay-side)

The per-show Durable Object is the authoritative game server:

- **Lobby / matchmaking:** first two widgets to join become P1/P2; the rest spectate or
  queue. Shareable join link.
- **State machine:** `waiting → p1_turn → resolving → p2_turn → … → gameover`, with a
  per-turn **~20 s timeout** so an idle player can't stall.
- **Authoritative physics + hit detection** live here (never on the widget) — the DO is
  the single source of truth for both players and the lights.
- **Score / best-of-N.**
- **WebSocket** to each widget for instant turn handoff and live "incoming…" feedback
  (widgets can hold a live socket even though FPP cannot).

The FPP poller pulls the next **resolved shot** from the relay and hands it to the
Coordinator; it never computes outcomes itself.

## 7. Characters & customization

- **Built-in packs ship first:** throwers/forts (snowman, elf, penguin, Yeti, Grinch,
  Santa), swappable projectiles (snowball, ornament, candy cane), hit reactions, impact
  FX (splat burst, growing snow pile, screen-shake via overlay offset).
- **Two fort rendering styles:**
  - *Sprite on matrix* — for shows whose playfield is a matrix.
  - *Bind to a real prop* — for shows with character props (Santa/Frosty). A 200-node
    prop can't show a sprite, so its "reaction" is color/animation on the whole prop.
- **Custom characters = top-tier add-on** (later): upload sprite packs / an in-widget
  pixel-art editor. A character pack = a small manifest + PNG frames at a reference
  resolution, scaled to the target zone.

## 8. Tiering

| Tier | Includes |
|---|---|
| CrowdPlay Personal ($5/yr) | existing command-triggering |
| CrowdPlay Premium | Snowball Fight: arena, matchmaking, built-in packs, leaderboard |
| Character Customization (add-on / top tier) | custom sprite packs + pixel editor |

All on the existing EncoreRadio license server + PayPal path — new SKUs + a feature
flag in the plugin.

## 9. Phasing

0. **Static proof** — pre-render 2–3 FSEQ arcs, trigger them as plain CrowdPlay
   Commands from a two-button widget. Proves the pipeline with zero new render code.
1. **Live daemon, single matrix** — real angle/power → computed trajectory drawn on one
   matrix overlay (single player "throw").
2. **DO match machine** — lobby, turns, authoritative physics, score, WebSocket widget.
3. **Cross-controller Coordinator** — channel→controller routing, per-remote fan-out,
   Transparent overlays; solves AA issue #4.
4. **Characters + polish** — forts/props, hit animations, wind FX, sound hook (tie into
   a Command), spectator view, leaderboard / tournament nights.

## 10. Open items before/at build

1. ~~Per-pixel overlay raw-data payload + latency~~ — **RESOLVED 2026-11-23** (base64
   `PUT .../data`, 25 ms/800px buffer to a remote; see §4.2).
2. **Coordinate normalization** — 15018×3245, y-up, z-depth → logical 0–100; decide
   pure-2D (front-facing, z≈0) vs. depth-aware (megatrees as back-row forts).
3. **Addressing** — route by global channel vs. (model, pixel-index); likely hybrid.
4. **Arena freshness** — re-import `virtualdisplaymap` on sequence upload, or a
   "re-import layout" button; warn if the map hash changed.
5. **Pricing** — set the Premium / add-on price points.

---

## Appendix A — Live findings (Nick's show, 2026-11-22)

Player **Clark** `192.168.1.218` (Pi 3B+, FPP v10.4, mode=player, multisync=true),
global channels **1–114,401**. `ci-universes.json` empty (not ingesting) ;
`co-universes.json` DDP bridge present but `active:0` (red herring — live path is
multisync).

**MultiSync channel ownership (global space):**

| Remote | Global channels |
|---|---|
| Audrey (.11, K8-B BBB) | 1–15,660 |
| Margo (.206) | 15,661–34,764 |
| Snots (.7) | 34,765–40,308 |
| Ruby-Sue (.8) | 40,309–53,658 |
| Rusty (.15) | 53,659–58,533 |
| Uncle-Lewis (.46, F8-PB) | 0–511, 58,534–69,333 |
| Megatree (.5) | 69,334–84,765 |
| Clark (local) | 84,766–114,401 |

So the house matrix spans **Audrey + Margo + Ruby-Sue**; a natural matchup is
**Santa (Megatree ctrl, left) vs Frosty (Margo, right)**. (Off-season, remotes power
down; only Clark + Audrey were online during the investigation.)

**Overlay models** are identical on Clark and Audrey, in global channel space, e.g.
`House_Matrix_Section_1 @2`, `Frosty_The_Snowman @32876`, `Clarice @80492`. Registered
as `Type:"Channel"` (Width/Height null).

**Write test (remote Audrey, `House_Matrix_Section_1`):** `Overlay Model Fill`
→ `isActive=1`, HTTP 200, **43 ms**; `Overlay Model Clear` + `Overlay Model State
Disabled` → `isActive=0`. Overlay composited over the remote's own output, non-persistent.
