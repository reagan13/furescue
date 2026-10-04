# FurEscue — Design System

## Color Palette

| Role | Name | Hex |
|------|------|-----|
| Primary | Green | `#3D7432` |
| Secondary | Light Gray | `#F6F6F6` |
| White | White | `#FFFFFF` |
| Tertiary | Soft Gray | `#E8E4E4` |

Tokens live in `public/assets/css/tokens.css` (`:root` / `.dark`) and are mapped in `tailwind.config.js`. Prefer tokens over raw hex. The Tailwind entry is `public/assets/css/input.css`; the compiled sheet is `public/assets/css/style.css`.

## Typography

- **UI / KPI cards:** DM Sans (`--font-dash`)
- **Body / app chrome:** Nunito (`--font-sans`)
- **Display (legacy):** Fraunces (`--font-display`) — do not use on KPI values

---

## KPI / summary stat card

Shared primitive for admin summary stats (dashboard, reports, cases, rescuers, health). White card on the paper page background: **icon well left**, **label / value / trend** stacked right.

**Source of truth:** `public/assets/css/components/kpi.css`  
**JS helper:** `public/assets/js/components/kpi-card.js`

### Do not use

The old clipped-corner `.kpi-tile` pattern (diagonal cut, punch-hole circle, serif giant numbers, all-caps labels, gray icon squares) is deprecated. Do not copy it. New work uses `.kpi-card` only.

### Class API

| Class | Role |
|-------|------|
| `.kpi-grid` | Responsive row of cards |
| `.kpi-grid--compact` | Tighter gap and `minmax(10rem)` tracks |
| `.kpi-card` | White card surface |
| `.kpi-card--compact` | Smaller padding, icon well, and value |
| `.kpi-card--interactive` | Clickable card (`<a>` or `<button>`) with hover + `:focus-visible` |
| `.kpi-card__icon` | 1:1 pastel rounded-square well |
| `.kpi-card__icon--jungle` | Green well — totals / reports |
| `.kpi-card__icon--coral` | Peach well — pending / alert |
| `.kpi-card__icon--sky` | Blue well — in progress |
| `.kpi-card__icon--amber` | Gold well — resolved / 4th metric |
| `.kpi-card__icon--ink` | Neutral well |
| `.kpi-card__body` | Text stack |
| `.kpi-card__label` | Small muted label |
| `.kpi-card__value` | Large bold number |
| `.kpi-card__trend` | Subtext |
| `.kpi-card__trend--up` | Positive (green), e.g. `+3 Today` |
| `.kpi-card__trend--down` | Negative (coral) |
| `.kpi-card__trend--neutral` | Muted, e.g. `No change today` |

### Tones

| `tone` | Well token | Icon token | Typical use |
|--------|------------|------------|-------------|
| `jungle` | `--kpi-jungle-well` | `--kpi-jungle` | Totals / reports |
| `coral` | `--kpi-coral-well` | `--kpi-coral` | Pending / alert |
| `sky` | `--kpi-sky-well` | `--kpi-sky` | In progress |
| `amber` | `--kpi-amber-well` | `--kpi-amber` | Resolved / 4th |
| `ink` | `--kpi-ink-well` | `--kpi-ink` | Neutral |

Trend: `--kpi-trend-up`, `--kpi-trend-down`. Neutral uses `--muted-foreground`. Tailwind: `bg-kpi-jungle-well`, `text-kpi-jungle`, `text-kpi-trend-up`, etc.

### JS helper

```js
import { KpiCard, KpiGrid } from "/assets/js/components/kpi-card.js";

KpiCard({
  icon: "folder-kanban",       // Lucide name → data-lucide
  tone: "jungle",              // jungle | coral | sky | amber | ink
  label: "Total Reports",
  value: 603,
  trend: "+3 Today",           // or { text, tone }
  trendTone: "up",             // up | down | neutral (ignored if trend is an object)
  href: "/admin/reports/",     // optional → <a class="kpi-card kpi-card--interactive">
  interactive: false,          // true without href → <button> (bind click after render)
  className: "",
  attrs: "",                   // extra HTML attributes
});

KpiGrid({ items: [...], id: "kpi-grid" });
```

`onClick` is not serialized into HTML. Use `href`, or `interactive: true` plus `attrs: 'data-filter="pending"'` and bind after `lucide.createIcons()`.

### Markup example

```html
<div class="kpi-grid" id="kpi-grid">
  <article class="kpi-card" aria-label="Total Reports: 603">
    <div class="kpi-card__icon kpi-card__icon--jungle" aria-hidden="true">
      <i data-lucide="folder-kanban"></i>
    </div>
    <div class="kpi-card__body">
      <p class="kpi-card__label">Total Reports</p>
      <p class="kpi-card__value">603</p>
      <p class="kpi-card__trend kpi-card__trend--up">+3 Today</p>
    </div>
  </article>
</div>
```

Call `lucide.createIcons()` after injecting HTML.

### Responsive

Mobile-first, no fixed widths:

- **&lt; 768px:** one column (stack; no overflow at 375px)
- **768px+:** two columns
- **1024px+:** `auto-fit` / `minmax` so four cards share one row on desktop (1440px)

Page padding comes from `.admin-main`. Cards use fluid padding (`clamp(1rem, 2vw, 1.5rem)`) and `minmax(0, 1fr)` tracks.

Compact variant (Cases, Listings, Applications): `.kpi-card--compact` / `.kpi-grid--compact`, or `compact: true` on `KpiCard` / `KpiGrid`. Verify 375 / 768 / 1440 — cards wrap, no horizontal overflow.
