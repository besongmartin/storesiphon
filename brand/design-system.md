# StoreSiphon Design System v1.0

The tokens live in [`tokens/tokens.css`](tokens/tokens.css) and are the single source of truth; `tokens.json` is generated from it. Components must use **semantic tokens** (`--color-primary`), never raw palette values (`--ss-teal-700`). That's what makes light and dark mode work everywhere.

Surfaces covered: marketing site · auth · dashboard · extraction workflow · product preview · pricing · Bitcoin checkout · invoices · admin.

---

## 1. Principles

1. **Data first.** Tables, numbers and states are the product. Chrome stays quiet: neutral surfaces, one accent.
2. **Explicit states.** Every job, invoice and export has a visible, named state with a matching badge and an explanation. There are no silent failures (the reference's biggest flaw).
3. **Square-leaning.** Small radii and square bullets echo the record. Round shapes are reserved for avatars and the logo's bowls.
4. **One accent at a time.** Teal for actions and data, Amber only for money and attention. Never both on one control.
5. **Accessible by default.** WCAG 2.2 AA contrast, visible focus, 44 px touch targets, reduced motion respected.

## 2. Colour and contrast

### Semantic tokens

| Token | Light | Dark | Use |
|---|---|---|---|
| `--color-bg` | Paper 50 `#F6F8F7` | Ink 950 `#081620` | Page |
| `--color-surface` | White | Ink 800 `#0F2636` | Cards, tables, panels |
| `--color-surface-raised` | White + `--shadow-md` | `#13304A` | Menus, dialogs |
| `--color-surface-sunken` | Mist 100 `#EBF0F2` | `#06111A` | Inputs, code blocks, table headers |
| `--color-border` | Mist 200 `#DCE3E8` | Ink 700 `#1C3A4F` | Dividers, card borders |
| `--color-text` | Ink 900 | `#EEF3F5` | Body text |
| `--color-text-secondary` | Slate 600 | `#C7D2D9` | Labels, secondary text |
| `--color-text-muted` | Slate 500 | Slate 300 | Hints, timestamps |
| `--color-primary` / `--color-on-primary` | Teal 700 / White | Teal 300 / Ink 900 | Primary buttons, links, active nav |
| `--color-accent` / `--color-on-accent` | Amber 500 / Ink 900 | same | Pay-with-Bitcoin CTA, highlights |
| `--color-success` / `-warning` / `-danger` / `-info` | see tokens | see tokens | Status text and icons; use the `-bg` token for tinted backgrounds |
| `--color-focus` | Teal 600 | Teal 300 | Focus ring |

### Verified contrast (WCAG 2.x ratio)

| Pair | Ratio | Passes |
|---|---|---|
| Ink 900 on Paper / White | 15.8 / 16.8 | AAA |
| Slate 600 on White | 6.8 | AA |
| Slate 500 on White | 5.0 | AA |
| Teal 700 on White (links, button bg with white text) | 5.0 | AA |
| White on Teal 800 (button hover) | 6.5 | AA |
| Ink 900 on Teal 300 (dark-mode primary button) | 9.5 | AAA |
| Teal 300 on Ink 950 | 10.4 | AAA |
| Slate 300 on Ink 950 / Ink 800 | 8.9 / 7.5 | AAA |
| Ink 900 on Amber 500 | 8.1 | AAA |
| Amber 700 `#9A5B00` on White | 5.4 | AA |
| Success `#1A7A43` / Danger `#C2382B` on White | 5.4 / 5.4 | AA |
| Teal 600 on White | 3.3 | **Non-text only** (logo, ≥24 px graphics) |

Never put normal-size text in Teal 600 or Amber 500 on light backgrounds. Colour is never the only signal: badges always include a label and icon.

## 3. Typography scale

The base size is 16 px. Use Inter for everything except data, and JetBrains Mono (tabular) for SKUs, IDs, prices in tables, BTC amounts, invoice references and code.

| Token | Size / line height | Weight | Use |
|---|---|---|---|
| `--text-5xl` | 52 / 1.15, tracking −0.02em | 700 | Marketing hero only |
| `--text-4xl` | 40 / 1.15 | 700 | Page hero, pricing headline |
| `--text-3xl` | 30 / 1.3 | 600 | Section titles (marketing) |
| `--text-2xl` | 24 / 1.3 | 600 | App page titles |
| `--text-xl` | 20 / 1.3 | 600 | Card titles, dialog titles |
| `--text-lg` | 18 / 1.55 | 400 | Lead paragraphs |
| `--text-base` | 16 / 1.55 | 400 | Body, form inputs |
| `--text-sm` | 14 / 1.55 | 400/500 | Table cells, labels, buttons |
| `--text-xs` | 12 / 1.4, caps tracking 0.06em | 500/600 | Badges, overlines, meta |

Rules:
- Use `font-variant-numeric: tabular-nums` in tables, counters and money.
- Keep body text to a maximum line length of `--content-max` (720 px).
- Use sentence case for UI; no all-caps buttons.
- Show BTC amounts to 8 decimals in mono, always with the fiat equivalent and the rate timestamp.

## 4. Spacing and layout

- The base unit is 4 px (`--space-1` … `--space-24`). Spacing inside components is 8, 12 or 16; between components 24 or 32; between sections 64 or 96.
- **Marketing layout:** 12-column grid, `--container-max` 1200 px. Gutters are 24 px on desktop and 16 px on mobile.
- **App shell:** left sidebar `--sidebar-w` 248 px (collapses to an icon rail at < 1024 px and to a drawer at < 768 px), top bar `--topbar-h` 56 px, content padded at 24 px (16 px on mobile).
- **Breakpoints:** 480 / 768 / 1024 / 1280.
- **Density:** tables default to 44 px rows, with a compact option of 36 px for admin.

## 5. Radius, elevation, surfaces

| Token | Value | Use |
|---|---|---|
| `--radius-xs` | 2 | Badges, checkboxes, the record bullet |
| `--radius-sm` | 4 | Inputs (small), tags |
| `--radius-md` | 6 | Buttons, inputs, dropdown items |
| `--radius-lg` | 10 | Cards, panels, table containers |
| `--radius-xl` | 14 | Dialogs, pricing cards, app icon context |
| `--radius-full` | — | Avatars, toggle tracks only |

**Elevation:** cards are flat with a 1 px border; use `--shadow-sm` on hover only if the card is clickable. Menus and popovers get `--shadow-md`; dialogs get `--shadow-lg` plus a scrim of Ink 950 at 60 %. In dark mode, elevation comes from a lighter surface (`--color-surface-raised`) rather than from a shadow.

## 6. Components

### Buttons

| Variant | Light | Dark | Use |
|---|---|---|---|
| Primary | bg `--color-primary`, text `--color-on-primary` | Teal 300 bg / Ink text | One per view: "Start extraction", "Export" |
| Secondary | Surface bg, 1 px `--color-border-strong`, Ink text | same tokens | Cancel, Back, secondary actions |
| Ghost | Transparent, `--color-text-secondary`; hover `--color-surface-sunken` | same | Toolbar and table actions |
| Danger | `--color-danger` bg, white text | `--color-danger` text on `--color-danger-bg` | Delete account, cancel job |
| Pay (Bitcoin) | `--color-accent` bg, `--color-on-accent` text, BTC glyph | same | Checkout CTA only |

- **Sizes:** small 32 px, medium 40 px (default) and large 48 px (marketing). Horizontal padding 12, 16 and 20.
- **Shape and text:** `--radius-md`, weight 600 at `--text-sm`.
- **States:**
  - Hover: `--color-primary-hover`.
  - Focus: `--ring-focus` plus a 2 px offset.
  - Disabled: 45 % opacity, no pointer.
  - Loading: label stays, the loader record replaces the leading icon, and the width doesn't change.

### Inputs

- 40 px high, `--radius-md`, `--color-surface` bg and 1 px `--color-border-strong`.
- Focus: border `--color-focus` plus `--ring-focus`.
- Label above (14/500); hint below (12, muted); error below (12, `--color-danger`, with an icon), with the border also switching to danger.
- URL fields (e.g. the store URL) use mono, show a validated preview chip ("example.com · WooCommerce detected") after the preflight check, and never auto-submit on paste.

### Cards

- `--color-surface`, 1 px border, `--radius-lg`, padding 24 (16 on mobile).
- Header row: title `--text-xl` plus actions aligned right.
- Stat cards: overline label, value at `--text-3xl` in tabular figures, and a delta badge.

### Navigation

- **Marketing top bar:** horizontal logo at 28 px high; links at `--text-sm`/500; primary CTA at the right. It becomes sticky with a bottom border after scrolling.
- **App sidebar:** symbol at 24 px plus the wordmark (or the symbol only when collapsed).
  - Items are 36 px tall with `--radius-md`.
  - The active item gets a `--color-primary-subtle` background, `--color-primary` text, and a 2 × 16 px square record marker at the left.
  - Sections: Dashboard, New extraction, Jobs, Exports, Billing, Settings; Admin is visible to admins only.
- **Breadcrumbs:** `--text-sm`, muted, with `/` separators.

### Badges (status)

12 px / 600, `--radius-xs`, 2 × 8 padding, with a leading 6 px **square** dot.

| Domain | State → tone |
|---|---|
| Jobs | queued → neutral · preflight/running → info (dot pulses unless reduced motion) · succeeded → success · partial → warning · failed → danger · blocked → danger with a "why" link · cancelled → neutral |
| Invoices | pending → neutral · detected/confirming → info with a confirmations count "1/2" · confirmed → success · expired → neutral · underpaid/overpaid → warning · failed → danger · refunded → neutral |

### Tables (product preview, jobs, invoices, admin)

- Sticky header on `--color-surface-sunken`, 12 px/600 headers, 44 px rows and 1 px row dividers. Numbers are right-aligned; monospace for SKUs and IDs.
- The toolbar holds search, filters (category, stock, type, price range) and a column picker; pagination is server-side (25/50/100 rows).
- Variant rows nest under their parent with a 16 px indent and a square bullet. The parent shows "4 variants" and expands.
- **Empty state:** a single Teal record, one sentence and one action.
- **Loading:** skeleton rows with a shimmer, which is static under reduced motion.

### Bitcoin checkout and invoices

- Two-column layout: order summary (plan, period, fiat price) and payment panel (QR code at 240 px on a white tile even in dark mode, address in mono with a copy button, BTC amount, rate snapshot plus timestamp, expiry countdown).
- The status rail shows Created → Payment detected → Confirming (n/N) → Confirmed, with the current step in Teal.
- Copy: "Access activates after the payment is confirmed on-chain." Never claim success from a redirect alone.
- Invoices are printable A4/Letter: horizontal logo at 32 px, invoice reference in mono, line items, BTC amount, rate, transaction ID, and status badge.

### Toasts and alerts

- Inline alerts use the `-bg` tint, a 1 px border in the status colour at 30 %, an icon and a title.
- Toasts appear bottom-right, last 6 s, can be dismissed, and are announced via `aria-live="polite"`. Errors persist until dismissed.

## 7. Light and dark theme behaviour

- The default follows `prefers-color-scheme`. A user setting stored as `<html data-theme="light|dark">` overrides it (Settings → Appearance: System / Light / Dark).
- Logo: use `color` on light and `color-on-dark` on dark. Inline usage can bind fills to `--logo-ink` / `--logo-record` so the theme switches automatically.
- QR codes, product images and charts stay on white tiles in dark mode for scannability and colour fidelity.
- Never invert brand colours automatically; use the semantic tokens.

## 8. Logo sizing and placement

| Surface | Asset | Size |
|---|---|---|
| Marketing header | Horizontal (animated on first view) | 28–32 px high |
| Marketing footer | Horizontal, static | 24 px high |
| Auth pages | Stacked, centred above the card | 72 px symbol height |
| App sidebar (expanded / collapsed) | Symbol + wordmark / symbol only | 24 px |
| Loading screen | Loader | 48 px |
| Emails | Horizontal PNG (`png/…-1200.png` at 240 px wide) | 24 px high |
| Invoices (print) | Horizontal, black or colour SVG | 32 px high |
| Favicon / app icon | `icons/` | — |

Keep clear space of at least one record (*t*) on every side, and always place the logo top-left in the app chrome.

## 9. Motion and transitions

| Token | Value | Use |
|---|---|---|
| `--dur-instant` | 80 ms | Hover colour, checkbox |
| `--dur-fast` | 140 ms | Buttons, toggles, tooltips |
| `--dur-base` | 220 ms | Menus, popovers, row expand |
| `--dur-slow` | 360 ms | Dialogs, drawers, page-level transitions |
| `--dur-logo` | 1100 ms | Logo intro only |

- **Easing:** `--ease-standard` for most motion, `--ease-out` for entering elements (they "settle" like the record), and `--ease-in-out` for path or progress movement.
- **Movement:** enter by fading plus a ≤ 8 px translate along the direction of flow. Exits are faster than entries (about 70 %).
- **Progress:** extraction progress uses a determinate bar plus the records counter. Use spinners only for under 2 s, unknown waits. Never block the whole page; jobs run in the background.
- **Reduced motion:** `prefers-reduced-motion: reduce` sets the durations to 0. Pulsing dots and shimmers become static, and logo animations show their final frame.
- **Never animate:** prices, BTC amounts (no count-up animation on money), table re-sorts, or anything during typing.

## 10. Accessibility checklist

- Contrast meets §2.
- Focus is always visible (`--ring-focus`).
- Targets are at least 44 × 44 px on touch.
- Form errors are linked with `aria-describedby`.
- Status changes (job state, payment state) are announced via `role="status"`.
- Tables have `<caption>` and `scope` attributes.
- Countdowns (invoice expiry) announce at most once a minute.
- The logo `<img>` uses `alt="StoreSiphon"`; decorative loaders use `alt=""`.
