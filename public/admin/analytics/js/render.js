import { emptyState, esc, mapHealthUpdate, mapPersonnel, OVERVIEW_LABELS } from "./format.js";

function setWrapMode(wrap, isEmpty) {
  wrap.classList.toggle("queue-empty", isEmpty);
  wrap.classList.toggle("table-wrap", !isEmpty);
}

export function renderOverview(rows) {
  const wrap = document.getElementById("table-overview");
  if (!wrap) return;
  setWrapMode(wrap, !rows.length);
  if (!rows.length) {
    wrap.innerHTML = emptyState("inbox", "No records.");
    return;
  }
  wrap.innerHTML = `
    <table class="table">
      <thead><tr class="table-head"><th>Metric</th><th class="table-cell--right">Value</th></tr></thead>
      <tbody>${rows.map((r) => `
        <tr>
          <td class="table-cell">${esc(OVERVIEW_LABELS[r.key] ?? r.key ?? "")}</td>
          <td class="table-cell table-cell--mono table-cell--strong table-cell--right">${esc(r.value ?? 0)}</td>
        </tr>`).join("")}</tbody>
    </table>`;
}

export function renderTrends(rows) {
  const wrap = document.getElementById("table-trends");
  if (!wrap) return;
  setWrapMode(wrap, !rows.length);
  if (!rows.length) {
    wrap.innerHTML = emptyState("bar-chart-3", "No completed adoptions in this range.");
    return;
  }
  wrap.innerHTML = `
    <table class="table">
      <thead><tr class="table-head"><th>Day</th><th class="table-cell--right">Completed adoptions</th></tr></thead>
      <tbody>${rows.map((t) => `
        <tr>
          <td class="table-cell table-cell--mono">${esc(t.day ?? "")}</td>
          <td class="table-cell table-cell--mono table-cell--strong table-cell--right">${esc(t.completed ?? 0)}</td>
        </tr>`).join("")}</tbody>
    </table>`;
}

export function renderUpdates(rows) {
  const wrap = document.getElementById("table-health");
  if (!wrap) return;
  setWrapMode(wrap, !rows.length);
  if (!rows.length) {
    wrap.innerHTML = emptyState("heart-pulse", "No health updates in this range.");
    return;
  }
  wrap.innerHTML = `
    <table class="table">
      <thead><tr class="table-head"><th>Update</th><th>Animal</th><th>Logged by</th><th>Status</th><th>When</th></tr></thead>
      <tbody>${rows.map(mapHealthUpdate).map((r) => `
        <tr>
          <td class="table-cell table-cell--mono table-cell--strong">${esc(r.id)}</td>
          <td class="table-cell">${esc(r.animal)}</td>
          <td class="table-cell">${esc(r.by)}</td>
          <td class="table-cell"><span class="stamp stamp--sm ${esc(r.statusCls)}">${esc(r.status)}</span></td>
          <td class="table-cell table-cell--mono table-cell--muted">${esc(r.when)}</td>
        </tr>`).join("")}</tbody>
    </table>`;
}

export function renderPersonnel(rows) {
  const wrap = document.getElementById("table-personnel");
  if (!wrap) return;
  setWrapMode(wrap, !rows.length);
  if (!rows.length) {
    wrap.innerHTML = emptyState("siren", "No active rescuers.");
    return;
  }
  wrap.innerHTML = `
    <table class="table">
      <thead><tr class="table-head"><th>Rescuer</th><th>Contact</th><th>Duty</th><th>Updated</th></tr></thead>
      <tbody>${rows.map(mapPersonnel).map((r) => `
        <tr>
          <td class="table-cell table-cell--strong">${esc(r.name)}</td>
          <td class="table-cell">${esc(r.contact)}</td>
          <td class="table-cell"><span class="stamp stamp--sm ${esc(r.dutyCls)}">${esc(r.duty)}</span></td>
          <td class="table-cell table-cell--mono table-cell--muted">${esc(r.when)}</td>
        </tr>`).join("")}</tbody>
    </table>`;
}

export function renderRescue(activity) {
  const el = document.getElementById("rescue-activity");
  if (!el || !activity) return;
  const verified = Number(activity.verified_reports) || 0;
  const resolved = Number(activity.resolved_cases) || 0;
  const rate = activity.rescue_rate == null ? "" : ` (${Math.round(Number(activity.rescue_rate) * 1000) / 10}%)`;
  const hours =
    activity.avg_hours_to_resolve == null
      ? "not enough resolved cases yet"
      : `${activity.avg_hours_to_resolve} hours`;
  el.innerHTML = `<p>Resolved cases ${esc(resolved)} of ${esc(verified)} verified reports${esc(rate)}.</p><p class="field-hint">Average time from assign to resolve: ${esc(hours)}.</p>`;
}

export function renderPlaces(rows) {
  const el = document.getElementById("geo-summary");
  if (!el) return;
  const list = Array.isArray(rows) ? rows : [];
  if (!list.length) {
    el.innerHTML = emptyState("map-pin", "No verified pins in this range.");
    return;
  }
  el.innerHTML = `<div class="table-wrap"><table class="table"><thead><tr class="table-head"><th>Place</th><th class="table-cell--right">Verified reports</th></tr></thead><tbody>${list
    .map(
      (row) =>
        `<tr><td class="table-cell">${esc(row.place || "")}</td><td class="table-cell table-cell--right">${esc(row.reports ?? 0)}</td></tr>`
    )
    .join("")}</tbody></table></div>`;
}

export function renderAll(state) {
  renderOverview(state.overview);
  renderTrends(state.trends);
  renderUpdates(state.updates);
  renderPersonnel(state.personnel || []);
  renderRescue(state.rescue);
  renderPlaces(state.places);
}
