import { esc } from "/assets/js/lib/format.js";

export const KPI_TONES = ["jungle", "coral", "sky", "amber", "ink"];
export const KPI_TREND_TONES = ["up", "down", "neutral"];

const TONES = new Set(KPI_TONES);
const TREND_TONES = new Set(KPI_TREND_TONES);

function safeTone(tone) {
  return TONES.has(tone) ? tone : "jungle";
}

function safeTrendTone(tone) {
  return TREND_TONES.has(tone) ? tone : "neutral";
}

/**
 * Shared KPI / summary stat card.
 *
 * @param {object} opts
 * @param {string} [opts.icon] Lucide icon name (data-lucide)
 * @param {"jungle"|"coral"|"sky"|"amber"|"ink"} [opts.tone]
 * @param {string} [opts.label]
 * @param {string|number} [opts.value]
 * @param {string|{text?: string, tone?: string}} [opts.trend] Subtext, e.g. "+3 Today", or `{ text, tone }`
 * @param {"up"|"down"|"neutral"} [opts.trendTone]
 * @param {string|null} [opts.href] Renders as a link when set
 * @param {boolean} [opts.interactive] Focusable card without href (bind click after render)
 * @param {boolean} [opts.compact] Smaller padding, icon, and value
 * @param {string} [opts.className]
 * @param {string} [opts.attrs] Extra HTML attributes (trusted)
 */
export function KpiCard({
  icon = "activity",
  tone = "jungle",
  label = "",
  value = "",
  trend = "",
  trendTone = "neutral",
  href = null,
  interactive = false,
  compact = false,
  className = "",
  attrs = "",
} = {}) {
  const trendIsObject = trend && typeof trend === "object";
  const trendText = trendIsObject ? String(trend.text ?? "") : String(trend ?? "");
  const iconTone = safeTone(tone);
  const trendMod = safeTrendTone(trendIsObject ? trend.tone || trendTone : trendTone);
  const isInteractive = Boolean(href) || interactive;
  const cls = ["kpi-card", compact ? "kpi-card--compact" : "", isInteractive ? "kpi-card--interactive" : "", className]
    .filter(Boolean)
    .join(" ");
  const extra = attrs ? ` ${attrs}` : "";
  const aria = ` aria-label="${esc(`${label}: ${value}`)}"`;
  const trendHtml = trendText
    ? `<p class="kpi-card__trend kpi-card__trend--${trendMod}">${esc(trendText)}</p>`
    : "";
  const inner = `
    <div class="kpi-card__icon kpi-card__icon--${iconTone}" aria-hidden="true"><i data-lucide="${esc(icon)}"></i></div>
    <div class="kpi-card__body">
      <p class="kpi-card__label">${esc(label)}</p>
      <p class="kpi-card__value">${esc(value)}</p>
      ${trendHtml}
    </div>`;

  if (href) {
    return `<a href="${esc(href)}" class="${cls}"${aria}${extra}>${inner}</a>`;
  }
  if (interactive) {
    return `<button type="button" class="${cls}"${aria}${extra}>${inner}</button>`;
  }
  return `<article class="${cls}"${aria}${extra}>${inner}</article>`;
}

/**
 * Responsive row of KPI cards.
 *
 * @param {object} opts
 * @param {object[]} [opts.items] Passed through to KpiCard
 * @param {string} [opts.id]
 * @param {string} [opts.className]
 * @param {boolean} [opts.compact]
 */
export function KpiGrid({ items = [], id = "", className = "", compact = false } = {}) {
  const idAttr = id ? ` id="${esc(id)}"` : "";
  const cls = ["kpi-grid", compact ? "kpi-grid--compact" : "", className].filter(Boolean).join(" ");
  return `<div class="${cls}"${idAttr}>${items.map((item) => KpiCard({ ...item, compact: compact || item.compact })).join("")}</div>`;
}
