import { createIcons, icons } from "lucide";
import { AppShell } from "/assets/js/admin/app-shell.js";
import { Button } from "/shared/components/button/button.js";
import { SkeletonRescuers } from "/shared/components/skeleton/skeleton.js";
import { setNavBadge } from "/assets/js/lib/swr.js";
import { state } from "../state.js";
import { buildKpis, KpiTile, applicationCounts } from "./kpis.js";
import { FilterTabs, FilterToolbar } from "./filters.js";
import { ApplicationTable } from "./table.js";

function PageHead() {
  return `
  <div class="page-head">
    <div>
      <span class="stamp stamp--coral">Adoption</span>
      <h1 class="page-title">Applications</h1>
      <p class="page-sub">Review adoption applications, approve or decline pending requests, and complete approved placements.</p>
    </div>
    <div class="page-head-actions">
      ${Button({ text: "Export CSV", variant: "outline", icon: "download", attrs: 'data-export="csv"' })}
    </div>
  </div>`;
}

function ApplicationsPanel() {
  return `
  <div class="panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="file-check"></i>
        <h2 class="panel-title">Applications</h2>
      </div>
      <div class="panel-head-tools">
        <div id="application-tabs-wrap">${FilterTabs()}</div>
      </div>
    </div>
    <div id="application-filters">${FilterToolbar()}</div>
    <div id="application-table" class="panel-body">${ApplicationTable()}</div>
  </div>`;
}

export function ApplicationsPage(user, { loading = false } = {}) {
  const pending = applicationCounts().pending;
  if (loading) {
    return AppShell({
      user,
      notifications: 0,
      badges: { applications: pending },
      activeNav: "applications",
      children: SkeletonRescuers(),
    });
  }
  const kpis = buildKpis().map(KpiTile).join("");
  return AppShell({
    user,
    notifications: 0,
    badges: { applications: pending },
    activeNav: "applications",
    children: [PageHead(), `<div id="application-kpis" class="kpi-grid kpi-grid--compact">${kpis}</div>`, ApplicationsPanel()].join(""),
  });
}

export function rerenderAll() {
  const kpis = document.getElementById("application-kpis");
  if (kpis) kpis.innerHTML = buildKpis().map(KpiTile).join("");
  const tabs = document.getElementById("application-tabs-wrap");
  if (tabs) tabs.innerHTML = FilterTabs();
  const table = document.getElementById("application-table");
  if (table) table.innerHTML = ApplicationTable();
  const pending = applicationCounts().pending;
  const navBadge = document.querySelector('.sidebar-link[href="/admin/applications/"] .sidebar-badge');
  if (navBadge) navBadge.textContent = String(pending);
  setNavBadge("applications", pending);
  createIcons({ icons });
}
