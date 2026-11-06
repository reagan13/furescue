import { createIcons, icons } from "lucide";
import { API_BASE_URL, getAccessToken } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { initShell } from "/assets/js/admin/app-shell.js";
import { initDropdownMenu } from "/shared/components/dropdown-menu/dropdown-menu.js";
import { toast } from "/shared/components/toast/toast.js";
import { OVERVIEW_LABELS } from "./format.js";
import { renderAll } from "./render.js";
import { initDateRangePicker } from "/shared/components/date-range-picker/date-range-picker.js";

const state = {
  range: { start: "", end: "" },
  overview: [],
  trends: [],
  updates: [],
  personnel: [],
  rescue: null,
  places: [],
};

const EXPORT_PATHS = {
  overview: "analytics/overview/export",
  "adoption-trends": "analytics/adoption-trends/export",
  "health-updates": "health/updates/export",
};

const JSON_PATHS = {
  overview: "analytics/overview",
  trends: "analytics/adoption-trends",
  updates: "health/updates",
};

function rangeQuery() {
  const params = new URLSearchParams();
  if (state.range.start) params.set("start", state.range.start);
  if (state.range.end) params.set("end", state.range.end);
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

async function apiFetchJson(path) {
  const headers = {};
  const token = getAccessToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  const res = await fetch(`${API_BASE_URL}/${path.replace(/^\//, "")}`, { headers });
  let payload = null;
  try {
    payload = await res.json();
  } catch {
    /* non-JSON response */
  }
  if (!res.ok) {
    throw new Error((payload && payload.error && payload.error.message) || `Request failed (${res.status})`);
  }
  return payload;
}

async function apiData(path) {
  const payload = await apiFetchJson(path);
  return payload && payload.data;
}

async function refreshData() {
  const qs = rangeQuery();
  const [overview, trends, updates, personnel] = await Promise.all([
    apiData(JSON_PATHS.overview + qs),
    apiData(JSON_PATHS.trends + qs),
    apiData(JSON_PATHS.updates + qs),
    apiData("users?role=rescuer&account_status=active"),
  ]);
  const stats = (overview && overview.stats) || {};
  state.overview = Object.entries(stats)
    .filter(([key]) => OVERVIEW_LABELS[key])
    .map(([key, value]) => ({ key, value }));
  state.trends = (trends && trends.trends) || [];
  state.updates = (updates && updates.updates) || [];
  state.personnel = Array.isArray(personnel) ? personnel : [];
  state.rescue = stats.rescue_activity || null;
  state.places = Array.isArray(stats.geographic) ? stats.geographic : [];
  renderAll(state);
  createIcons({ icons });
}

async function downloadExport(metric) {
  const path = EXPORT_PATHS[metric];
  if (!path) return;
  const headers = {};
  const token = getAccessToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  const res = await fetch(`${API_BASE_URL}/${path}${rangeQuery()}`, { headers });
  if (!res.ok) {
    throw new Error(`Export failed (${res.status})`);
  }
  const blob = await res.blob();
  const match = /filename="([^"]+)"/.exec(res.headers.get("Content-Disposition") || "");
  const name = match
    ? match[1]
    : `furescue-${metric}-${new Date().toISOString().slice(0, 10)}.csv`;
  const href = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = href;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(href), 1000);
}

function setBusy(busy) {
  document.querySelectorAll("[data-export]").forEach((el) => {
    el.setAttribute("aria-busy", busy ? "true" : "false");
    if (el.disabled !== undefined) el.disabled = busy;
  });
}

function bindEvents() {
  document.querySelectorAll("[data-export]").forEach((el) => {
    el.addEventListener("click", async (e) => {
      e.preventDefault();
      if (el.getAttribute("aria-busy") === "true") return;
      setBusy(true);
      try {
        await downloadExport(el.dataset.export);
        toast("CSV downloaded.", { type: "success" });
      } catch (err) {
        console.error(err);
        toast(err.message || "Export failed.", { type: "error" });
      } finally {
        setBusy(false);
      }
    });
  });

  const rangeWrap = document.getElementById("analytics-range");
  initDateRangePicker(rangeWrap, {
    "analytics-range": async ({ start, end }) => {
      state.range.start = start || "";
      state.range.end = end || "";
      setBusy(true);
      try {
        await refreshData();
      } catch (err) {
        console.error(err);
        toast(err.message || "Could not refresh analytics.", { type: "error" });
      } finally {
        setBusy(false);
      }
    },
  });
}

function initDate() {
  const el = document.getElementById("admin-date");
  if (!el) return;
  el.textContent = new Date().toLocaleDateString("en-US", {
    weekday: "short",
    month: "short",
    day: "numeric",
  });
}

document.addEventListener("DOMContentLoaded", () => {
  bootstrapPageAuth();
  Object.assign(state, window.__PAGE_STATE__ || {});
  createIcons({ icons });
  initShell();
  initDropdownMenu(document);
  initDate();
  bindEvents();
});
