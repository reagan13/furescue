import * as api from "/assets/js/admin/admin-data.js";
import { safe } from "/admin/js/helpers.js";

export const state = {
  overview: null,
  reports: [],
  cases: [],
  rescuers: [],
  filter: "all",
  query: "",
  sort: "newest",
  page: 1,
  pageSize: 20,
};

/** Copy PHP first-paint lists onto `state`. Safe to call more than once. */
export function applyPageState(source = typeof window !== "undefined" ? window.__PAGE_STATE__ : null) {
  if (!source || typeof source !== "object") return false;
  if (source.overview != null && typeof source.overview === "object") {
    state.overview = source.overview;
  }
  if (Array.isArray(source.reports)) state.reports = source.reports;
  if (Array.isArray(source.cases)) state.cases = source.cases;
  if (Array.isArray(source.rescuers)) state.rescuers = source.rescuers;
  return Array.isArray(source.reports);
}

applyPageState();

const EMPTY_OVERVIEW = {
  reports: 0,
  cases: 0,
  cases_resolved: 0,
  rescuers_on_duty: 0,
  reports_verified: 0,
};

export async function loadReports() {
  const [overview, reports, cases, rescuers] = await Promise.all([
    safe(api.fetchOverview(), null),
    safe(api.fetchAllReports(), { items: [], total: 0 }),
    safe(api.fetchCases(), { items: [] }),
    safe(api.fetchRescuers(), { items: [] }),
  ]);
  state.overview = overview || { ...EMPTY_OVERVIEW };
  state.reports = reports.items || [];
  state.cases = cases.items || [];
  state.rescuers = rescuers.items || [];
}

export async function reloadData() {
  const [overview, reports, cases] = await Promise.all([
    safe(api.fetchOverview(), null),
    safe(api.fetchAllReports(), { items: [], total: 0 }),
    safe(api.fetchCases(), { items: [] }),
  ]);
  state.overview = overview || state.overview || { ...EMPTY_OVERVIEW };
  state.reports = reports.items || [];
  state.cases = cases.items || [];
}
