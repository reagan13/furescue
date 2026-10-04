import { state } from "../state.js";
import { loadChart } from "/assets/js/lib/load-chart.js";
import { whenVisible } from "/assets/js/lib/when-visible.js";
import { createIcons, icons } from "lucide";
import { Select } from "/shared/components/select/select.js";
import { Search } from "/shared/components/search/search.js";
import { Toolbar } from "/shared/components/toolbar/toolbar.js";
import { FilterTabs as TabStrip, FilterTab } from "/shared/components/filter-tabs/filter-tabs.js";
import { KpiCard, KpiGrid } from "/shared/components/kpi-card/kpi-card.js";
import { esc } from "/assets/js/lib/format.js";

const STATUS_COLORS = {
  open: "hsl(211, 71%, 38%)",
  assigned: "hsl(211, 71%, 38%)",
  in_progress: "hsl(199, 74%, 53%)",
  resolved: "hsl(215, 16%, 47%)",
};

const STATUS_PALETTE = {
  open: "hsl(211, 71%, 38%)",
  assigned: "hsla(211, 71%, 38%, 0.65)",
  in_progress: "hsl(199, 74%, 53%)",
  resolved: "hsl(215, 16%, 47%)",
};

export function caseCounts() {
  const count = (s) => state.cases.filter((c) => c.status === s).length;
  return {
    all: state.cases.length,
    open: count("open"),
    assigned: count("assigned"),
    in_progress: count("in_progress"),
    resolved: count("resolved"),
  };
}

export function buildKpis() {
  const c = caseCounts();
  return [
    { icon: "clipboard-list", value: c.all, label: "Total cases", tone: "jungle", desc: "Every case in the system, all statuses included." },
    {
      icon: "folder-open",
      value: c.open,
      label: "Open",
      tone: "coral",
      trend: c.open ? "Intake" : "",
      trendTone: "down",
      desc: "Newly reported cases not yet assigned to a rescuer.",
    },
    { icon: "user-plus", value: c.assigned, label: "Assigned", tone: "sky", desc: "Assigned to a rescuer, awaiting their acceptance." },
    { icon: "activity", value: c.in_progress, label: "In progress", tone: "sky", desc: "Rescues that are actively underway." },
    { icon: "check-circle-2", value: c.resolved, label: "Resolved", tone: "jungle", desc: "Cases successfully completed and closed." },
  ];
}

export function toKpiCardProps(k) {
  const aria = k.desc ? `${k.label}: ${k.value}. ${k.desc}` : `${k.label}: ${k.value}`;
  const extra = [`aria-label="${esc(aria)}"`];
  if (k.desc) extra.push(`title="${esc(k.desc)}"`);
  return {
    icon: k.icon,
    tone: k.tone,
    label: k.label,
    value: k.value,
    trend: k.trend || "",
    trendTone: k.trendTone || "neutral",
    attrs: extra.join(" "),
  };
}

export function KpiTile(k) {
  return KpiCard(toKpiCardProps(k));
}

export function KpiStrip() {
  return `
    ${KpiGrid({ items: buildKpis().map(toKpiCardProps), compact: true })}
    <div class="kpi-donut" id="kpi-donut-card">${StatusChart()}</div>`;
}

export const CASE_FILTERS = [
  { key: "all", label: "All" },
  { key: "open", label: "Open" },
  { key: "assigned", label: "Assigned" },
  { key: "in_progress", label: "In Progress" },
  { key: "resolved", label: "Resolved" },
];

export function CaseFilterTabs() {
  const c = caseCounts();
  const count = {
    all: c.all,
    open: c.open,
    assigned: c.assigned,
    in_progress: c.in_progress,
    resolved: c.resolved,
  };
  return TabStrip({
    id: "case-tabs",
    children: CASE_FILTERS.map((f) =>
      FilterTab({ key: f.key, label: `${f.label} &middot; ${count[f.key]}`, active: state.filter === f.key })
    ).join(""),
  });
}

export function CaseToolbar() {
  return Toolbar({
    children: `
    ${Search({ id: "case-search", placeholder: "Search case #, barangay, animal…", value: state.query })}
    <div class="report-sort">
      ${Select({
        id: "case-sort",
        options: [
          { value: "", label: "Sort" },
          { value: "newest", label: "Newest" },
          { value: "status", label: "Status" },
          { value: "updated", label: "Updated" },
        ],
        value: state.sort,
        placeholder: "Sort",
        className: "report-sort-control",
      })}
    </div>`,
  });
}

function tokenHsl(name, fallback) {
  const raw = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return raw ? `hsl(${raw})` : fallback;
}

function StatusChart() {
  const c = caseCounts();
  const legend = [
    { label: "Open", val: c.open, cls: "status-seg--open" },
    { label: "Assigned", val: c.assigned, cls: "status-seg--assigned" },
    { label: "In progress", val: c.in_progress, cls: "status-seg--live" },
    { label: "Resolved", val: c.resolved, cls: "status-seg--resolved" },
  ]
    .map((s) => `<span class="status-legend-item"><span class="status-dot ${s.cls}"></span>${s.label} &middot; ${s.val}</span>`)
    .join("");
  return `
  <div class="panel panel--padded">
    <div class="panel-title-wrap"><i data-lucide="pie-chart"></i><h2 class="panel-title panel-title--sm">Case status breakdown</h2></div>
    <div class="donut-wrap">
      <div class="donut${c.all ? "" : " donut--empty"}">
        <canvas id="status-donut"></canvas>
        <div class="donut-center"><span class="donut-total">${c.all}</span><span class="donut-label">Cases</span></div>
      </div>
      <div class="status-legend">${legend}</div>
    </div>
  </div>`;
}

let statusDonutInstance = null;
let statusDonutGen = 0;

async function mountStatusDonut() {
  const canvas = document.getElementById("status-donut");
  if (!canvas) return;
  const gen = ++statusDonutGen;
  if (statusDonutInstance) {
    statusDonutInstance.destroy();
    statusDonutInstance = null;
  }
  const c = caseCounts();
  const total = c.all;
  if (!total) return;
  await whenVisible(canvas);
  if (gen !== statusDonutGen || !canvas.isConnected) return;
  const Chart = await loadChart();
  if (!Chart || gen !== statusDonutGen || !canvas.isConnected) return;
  const muted = tokenHsl("--border", "hsl(0 6% 88%)");
  const card = tokenHsl("--card", "#fff");
  statusDonutInstance = new Chart(canvas, {
    type: "doughnut",
    data: {
      labels: total ? ["Open", "Assigned", "In progress", "Resolved"] : ["No data"],
      datasets: [
        {
          data: total ? [c.open, c.assigned, c.in_progress, c.resolved] : [1],
          backgroundColor: total
            ? [STATUS_PALETTE.open, STATUS_PALETTE.assigned, STATUS_PALETTE.in_progress, STATUS_PALETTE.resolved]
            : [muted],
          borderColor: card,
          borderWidth: 2,
          hoverOffset: total ? 4 : 0,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "68%",
      plugins: {
        legend: { display: false },
        tooltip: { enabled: Boolean(total) },
      },
    },
  });
}

export function renderStatusBreakdown() {
  const wrap = document.getElementById("kpi-donut-card");
  if (wrap) {
    wrap.innerHTML = StatusChart();
    createIcons({ icons });
    void mountStatusDonut();
  }
}
