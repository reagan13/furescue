import { createIcons, icons } from "lucide";
import { AppShell } from "/assets/js/admin/app-shell.js";
import { Button } from "/shared/components/button/button.js";
import { state } from "../state.js";
import { buildKpis, KpiTile } from "./kpis.js";
import { FilterTabs, FilterToolbar } from "./filters.js";
import { ListingTable } from "./table.js";
import { panelTitle } from "./util.js";

function PageHead() {
  return `
  <div class="page-head">
    <div>
      <span class="stamp stamp--accent">Adoption</span>
      <h1 class="page-title">Listings</h1>
      <p class="page-sub">Review community adoption posts. Approving a listing sets the animal as available.</p>
    </div>
    <div class="page-head-actions">
      ${Button({ text: "Export CSV", variant: "outline", icon: "download", attrs: 'data-export="csv"' })}
    </div>
  </div>`;
}

function ListingsPanel() {
  return `
  <div class="panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="home"></i>
        <h2 class="panel-title" id="listing-panel-title">${panelTitle(state.filter)}</h2>
      </div>
      <div class="panel-head-tools">
        <div id="listing-tabs-wrap">${FilterTabs()}</div>
      </div>
    </div>
    <div id="listing-filters">${FilterToolbar()}</div>
    <div id="listing-table" class="panel-body">${ListingTable()}</div>
  </div>`;
}

export function ListingsPage(user) {
  const kpis = buildKpis().map(KpiTile).join("");
  return AppShell({
    user,
    notifications: 0,
    badges: {},
    activeNav: "listings",
    children: [PageHead(), `<div id="listing-kpis" class="kpi-grid kpi-grid--compact">${kpis}</div>`, ListingsPanel()].join(""),
  });
}

export function rerenderAll() {
  const kpis = document.getElementById("listing-kpis");
  if (kpis) kpis.innerHTML = buildKpis().map(KpiTile).join("");
  const title = document.getElementById("listing-panel-title");
  if (title) title.textContent = panelTitle(state.filter);
  const tabs = document.getElementById("listing-tabs-wrap");
  if (tabs) tabs.innerHTML = FilterTabs();
  const table = document.getElementById("listing-table");
  if (table) table.innerHTML = ListingTable();
  createIcons({ icons });
}
