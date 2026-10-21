# StoreSiphon — Brand Guide v1.0

> Draft for approval · 2026-10-21 · Companion docs: [design-system.md](design-system.md), [tokens/tokens.css](tokens/tokens.css)

## 1. Positioning and personality

**What StoreSiphon is:** a precise, dependable tool that turns any public WooCommerce catalogue into clean, structured data you can use straight away.

**Positioning line (working):** *Point it at a store. Get the catalogue, structured.*

| Personality | Means in practice | Not |
|---|---|---|
| Precise | Exact numbers, explicit states, honest errors | Vague "magic" claims |
| Dependable | Calm surfaces, consistent layout, predictable motion | Flashy gradients, neon |
| Efficient | Short copy, dense-but-readable tables, fast feedback | Decorative clutter |
| Responsible | Respects store owners: robots.txt, rate limits, opt-out | "Scrape anything, bypass anything" |

Voice: plain, specific and technical where it helps; second person; no hype words ("blazing", "ultimate", "magic").

## 2. The idea behind the mark

**The Siphon S.** The letter S is drawn as one continuous tube: in at the top, through two bends, out at the bottom. At the end of the tube, the tail breaks off as a **single square, the record**. That record is the product: one clean, structured row of data that has been drawn out of the store.

- The **tube** stands for flow and extraction (the siphon) without drawing a pipe or a funnel.
- The **square record** stands for structured output. It is the brand's ownable detail and reappears as the dot of the *i* in the wordmark, in the loader animation, in square-leaning UI corners and in data-table bullets.
- The S is built from exact geometry: two half-rings of equal radius joined tangentially, plus flat bars. It reads as precise and engineered, not hand-wavy.

Category clichés I deliberately avoided: blue/purple gradients, database cylinders, funnels, magnifying glasses, spider/bot icons, shopping bags/carts and download arrows. The reference product's branding (purple/cyan gradient, "WOO" lettering) was not used or imitated.

The concepts I explored are in `concepts/` (see `concepts/concepts-overview.png`):
- **A · Siphon S:** chosen.
- **B · Siphon arc:** read too much like a faucet or a question mark at small sizes.
- **C · Catalog to rows:** too close to a generic "layout" icon.

## 3. Colour

| Role | Name | HEX | RGB | Use |
|---|---|---|---|---|
| Primary | **Ink 900** | `#0B1F2E` | 11 31 46 | Logo, primary text, dark surfaces |
| Primary accent | **Siphon Teal 600** | `#0E9F8A` | 14 159 138 | The record square; large graphics only (3.3:1 on white, non-text) |
| Accent (text) | Teal 700 | `#0A7D6D` | 10 125 109 | Links, primary buttons, text on light (5.0:1) |
| Accent (dark) | Teal 300 | `#4FD8BF` | 79 216 191 | The record and primary actions on dark (10.4:1 on Ink 950) |
| Secondary | **Signal Amber** | `#F2A33A` | 242 163 58 | Highlights, Bitcoin checkout accents, "new" badges. Text on it = Ink 900 (8.1:1) |
| Neutral | Paper 50 | `#F6F8F7` | 246 248 247 | Light page background |
| Neutral | Mist 200 | `#DCE3E8` | 220 227 232 | Borders on light |
| Neutral | Slate 600 / 500 | `#4A5D6C` / `#5E7181` | — | Secondary / muted text on light (6.8:1 / 5.0:1) |
| Neutral | Ink 950 | `#081620` | 8 22 32 | Dark page background |

Status colours (success `#1A7A43`, warning `#946200`, danger `#C2382B`, plus dark-theme tints) are in `tokens/tokens.css`. Every text pairing listed there meets WCAG 2.2 AA (≥4.5:1). I calculated the ratios; they're documented in [design-system.md](design-system.md#2-colour-and-contrast).

**Proportion:** about 70 % neutrals, 20 % Ink, 8 % Teal, 2 % Amber. Teal marks the "data" moments; Amber is rare and purposeful (money, attention).

Print values (approximate; proof before printing): Ink 900 ≈ C90 M65 Y45 K70; Teal 600 ≈ C80 M0 Y55 K0. No Pantone match has been verified.

## 4. Typography

| Use | Family | Licence |
|---|---|---|
| Logo wordmark | **Custom-constructed letters** (geometric: same stroke logic as the symbol; square *i* dot) | Original artwork; no font licence involved |
| UI and marketing | **Inter** (400/500/600/700) | SIL Open Font License 1.1: free for commercial use, embedding and self-hosting |
| Data, code, SKUs, IDs, BTC amounts | **JetBrains Mono** (400/500) | SIL Open Font License 1.1 |

- Self-host the woff2 files with the app (no third-party font CDN, which is better for privacy and CSP). Keep each font's OFL text alongside the files.
- Never set the wordmark in Inter or any other font. Always use the SVG files.
- Fallback stacks are defined in `--font-sans` / `--font-mono`.

## 5. Logo system

| Asset | Files (in `logo/`) | Use |
|---|---|---|
| Horizontal lockup (primary) | `storesiphon-horizontal-{color, color-on-dark, black, white, mono-ink, mono-teal}.svg` | Site header, emails, documents, invoices |
| Stacked lockup | `storesiphon-stacked-*.svg` | Square spaces, splash/auth screens, social |
| Symbol | `storesiphon-symbol-*.svg` | App chrome, avatars, small spaces |
| Symbol, small-size cut | `storesiphon-symbol-small-{color,black}.svg` | Below 32 px. Heavier stroke and wider gap so the record stays separate |
| Wordmark | `storesiphon-wordmark-*.svg` | Where the symbol already appears nearby |
| App icon / favicon | `icons/` | See §7 |

**Construction (256-unit symbol grid):** stroke *t* = 40; centre-line radius = (224 − *t*) / 4 = 46; outer radius 66, inner 26; the two bowls share the same radius and meet tangentially on the vertical centre line; the record is *t* × *t* = 40 × 40 with a gap of 0.35 *t* (14). In the lockup, the symbol height equals the artboard height (256) and the wordmark cap height is 118 (≈ 0.53 of the symbol). The gap between symbol and wordmark is 56 (1.4 *t*).

**Clear space:** keep at least **1 record** (the square's width, *t*) of empty space on all sides, and 2 *t* in hero or marketing placements.

**Minimum sizes:**

| Asset | Minimum size | Smaller than that |
|---|---|---|
| Symbol (digital) | 20 px | Small-size cut from 32 px down; favicon below 20 px |
| Horizontal lockup | 112 px wide / 28 px tall | Use the symbol alone |
| Print | 8 mm (symbol), 30 mm wide (lockup) | — |

**Backgrounds:**

| Variant | Use on |
|---|---|
| `color` | White, Paper and light-grey backgrounds |
| `color-on-dark` | Ink 900/950 and other dark backgrounds |
| `white` | Teal, photos and busy backgrounds (with a scrim) |
| `black` | Single-colour printing, fax-like output, embossing |

**Don't:**
- Recolour the record in anything other than Teal or the one-colour version.
- Add gradients, shadows, outlines or glows.
- Rotate, skew or stretch the logo.
- Re-type the wordmark.
- Move the record or reattach it to the tail.
- Place the ink version on dark backgrounds.
- Animate the logo in any way other than the approved motion (§8).

## 6. Iconography and visual style

- **Icons:** 24 px grid, 1.75 px stroke, square line caps and mitred joins, 2 px corner radius. Lucide (ISC licence) is a good base set; adjust the stroke to match. Use Teal only for the active state.
- **The record motif:** small squares as bullets, the progress "records extracted" counter, table-row loading placeholders and the empty-state illustration (a lone record). No hearts, rockets or sparkles.
- **Illustration:** flat Ink line drawings with Teal records. No 3D, isometric or stock photography of people at laptops.
- **Data visualisation:** Teal for the primary series, Ink-700 and Slate for the others, Amber only for highlighted values.

## 7. App icons and favicon

The files are in `icons/`. All of them use the small-size cut at 64 % of the tile on Ink 900, with a Teal 300 record.

| File | Use |
|---|---|
| `favicon.svg` | Modern browsers |
| `favicon.ico` (16/32/48) | Legacy browsers |
| `favicon-16.png`, `favicon-32.png` | PNG favicons |
| `apple-touch-icon.png` (180) | iOS home screen |
| `icon-192.png`, `icon-512.png` | PWA |
| `icon-maskable-512.png` | Android adaptive (52 % safe zone) |
| `app-icon.svg` | Dark tile (default), source |
| `app-icon-light.svg` | Light tile alternative, source |
| `site.webmanifest` | Web app manifest |

`<head>` snippet for the future app:

```html
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="manifest" href="/icons/site.webmanifest">
<meta name="theme-color" content="#0B1F2E">
```

## 8. Motion

**Principle:** motion shows **flow, then structure**. Things move along a path and come to rest in a grid. Movement is decisive and short, and never decorative or looping, except for the work-in-progress indicator.

| Asset (`animation/`) | Behaviour | Duration |
|---|---|---|
| `storesiphon-symbol-animated[-on-dark].svg` | The S draws itself along its tube (masked centre-line), then the record slides out of the tail and settles | ≈1.1 s, plays once |
| `storesiphon-horizontal-animated[-on-dark].svg` | Same, with the wordmark easing in from 550 ms | ≈1.1 s, plays once |
| `storesiphon-loader[-on-dark].svg` | Static S; records stream out of the tail in a 1.6 s loop | Loop, only while work is running |

**How it's built:**
- Plain SVG with an embedded `<style>`: CSS keyframes, one mask, no JavaScript, no external files.
- 1–3 KB per file, so it doesn't block rendering.
- The final frame is identical to the static logo.
- `prefers-reduced-motion: reduce` disables the motion and shows the finished logo (the loader shows a single, still record).

**Easing:** draw = `cubic-bezier(.65,0,.35,1)`; record settle = `cubic-bezier(.22,1,.36,1)`.

**Integrating it later:**
1. **Marketing header and auth pages.** Use `<img src="/brand/storesiphon-horizontal-animated.svg" width="… " height="…" alt="StoreSiphon">`. Always set width and height to prevent layout shift. Play it only on the first page view of a session; on later navigations, swap in the static `logo/` file (one server-side or Livewire check).
2. **Dashboard.** Use the static logo. Never replay the intro on every navigation.
3. **Extraction jobs.** Use the loader next to "Extracting… 128 / 420 products", with `role="status"` text beside it. The SVG is decorative there, so give it `alt=""`.
4. **Inlining.** If you inline the SVG to theme it with CSS variables, give each inline copy a unique ID prefix. `build_animation.py` takes a prefix, which avoids duplicate `mask` IDs.
5. **CSP.** The files contain an internal `<style>` only. When loaded as `<img>`, they need no CSP changes. Inline SVG styles need `style-src 'self'` plus a nonce or hash, or keep them as `<img>`.

The demo page is `animation/demo.html`. Serve it over HTTP, e.g. `http://localhost/StoreSiphon/brand/animation/demo.html`.

I didn't produce a video (MP4/GIF): the brief asks to avoid video for a simple symbol, and the animated SVG is itself the standalone animation file. If you later need a video for social media, the `hyperframes` skill is installed and can render one from these SVGs.

## 9. Files

```
brand/
├── README.md                  ← this guide
├── design-system.md           ← tokens, components, themes, motion rules
├── source/
│   ├── build_brand.py         ← EDITABLE SOURCE: all logo/icon geometry (python brand/source/build_brand.py)
│   └── build_animation.py     ← EDITABLE SOURCE: animated SVGs (python build_animation.py, run from brand/source)
├── logo/      26 SVG masters  (symbol, small cut, wordmark, horizontal, stacked × color/on-dark/black/white/mono)
├── icons/     favicon.svg/.ico/png, app icons (svg + png), maskable icon, site.webmanifest
├── png/       high-res renders (symbol 1024², horizontal 2400 w and 1200 w, stacked 1200²)
├── animation/ 6 animated SVGs + demo.html
├── tokens/    tokens.css (source) + tokens.json (generated)
└── concepts/  exploration SVGs + concepts-overview.png
```

**Editable or rendered:**

| Kind | Files |
|---|---|
| Editable source of truth | `source/*.py`, `tokens/tokens.css` |
| Editable vectors | Every `.svg` (clean paths, open in Figma, Illustrator or Inkscape) |
| Rendered outputs (regenerate, don't edit) | `png/*`, `icons/*.png`, `icons/favicon.ico`, `concepts/concepts-overview.png`, `tokens/tokens.json` |

**Regenerate:**
1. `python brand/source/build_brand.py`
2. `cd brand/source && python build_animation.py`
3. Render the PNGs with the logo-design skill's `render_png.py` (it uses headless Chrome on this machine). The exact commands are in the session notes.

## 10. Open items

- **Trademark:** I haven't run any clearance check. Before launch, run a professional trademark search for "StoreSiphon" and a reverse-image search on the symbol.
- **Print:** I haven't converted the CMYK/Pantone values or proofed them.
- **No AI/EPS/PDF masters:** there's no vector editor on this machine. The SVGs open in any of them, so export from there if a printer asks.
- **Font files:** the Inter and JetBrains Mono woff2 files haven't been downloaded yet. They're needed when the app is built.
