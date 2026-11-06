import { Badge } from "/shared/components/badge/badge.js";
import { esc, caseStampCls } from "./util.js";
import { shortId, titleCase, timeAgo } from "/admin/js/helpers.js";
import { state } from "../state.js";

function InfoRow(label, value) {
  return `
    <div class="dialog-info-row">
      <span class="dialog-info-label">${esc(label)}</span>
      <span class="dialog-info-value">${value}</span>
    </div>`;
}

function finiteCoord(value) {
  if (typeof value === "string") value = value.trim();
  if (value === null || value === undefined || value === "" || typeof value === "boolean") return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

function locationLabel(report) {
  const address = String((report && report.address_text) || "").trim();
  if (address) return address;
  const lat = finiteCoord(report && report.latitude);
  const lng = finiteCoord(report && report.longitude);
  if (lat === null || lng === null) return "";
  return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
}

function pinMapHtml(report) {
  const lat = finiteCoord(report && report.latitude);
  const lng = finiteCoord(report && report.longitude);
  if (lat === null || lng === null) return "";
  return `<div id="case-pin-map" class="case-pin-map map-canvas map-canvas--leaflet mt-4" data-lat="${esc(lat.toFixed(6))}" data-lng="${esc(lng.toFixed(6))}" role="img" aria-label="Map pin for this report"></div>`;
}

export function renderCaseInfo(caseData) {
  const c = caseData;
  const rescuer = c.assigned_rescuer_id
    ? Badge({ text: c.rescuer_name || "Unassigned", variant: "secondary", icon: "user" })
    : Badge({ text: "Unassigned", variant: "outline" });
  const rows = [
    InfoRow("Case", shortId(c.id)),
    InfoRow("Status", `<span class="stamp stamp--sm ${caseStampCls(c.status)}">${esc(titleCase(c.status))}</span>`),
    InfoRow("Rescuer", rescuer),
    InfoRow("Source report", c.report_id ? shortId(c.report_id) : "—"),
    InfoRow("Created", c.created_at ? timeAgo(c.created_at) : "—"),
    InfoRow("Updated", c.updated_at ? timeAgo(c.updated_at) : "—"),
  ];
  return `
    <div class="panel case-detail-panel">
      <div class="panel-head">
        <div class="panel-title-wrap">
          <i data-lucide="clipboard-list"></i>
          <h2 class="panel-title">Case details</h2>
        </div>
      </div>
      <div class="panel-body"><div class="dialog-info">${rows.join("")}</div></div>
    </div>`;
}

export function renderSourceReport(caseData) {
  const report = caseData.report || state.report;
  if (!report) {
    return `
      <div class="panel case-detail-panel">
        <div class="panel-head"><div class="panel-title-wrap"><i data-lucide="file-text"></i><h2 class="panel-title">Source report</h2></div></div>
        <div class="panel-body"><div class="empty-state"><i data-lucide="file-x"></i><span>No linked report.</span></div></div>
      </div>`;
  }
  const animal = report.animal_description || "—";
  const location = locationLabel(report);
  return `
    <div class="panel case-detail-panel">
      <div class="panel-head">
        <div class="panel-title-wrap">
          <i data-lucide="file-text"></i>
          <h2 class="panel-title">Source report &middot; ${esc(shortId(report.id))}</h2>
        </div>
      </div>
      <div class="panel-body">
        <div class="dialog-info">
          ${InfoRow("Animal", esc(animal))}
          ${InfoRow("Location", location ? esc(location) : "—")}
          ${InfoRow("Validation", esc(report.validation_status || "—"))}
          ${InfoRow("Report status", esc(report.status || "—"))}
        </div>
        ${pinMapHtml(report)}
      </div>
    </div>`;
}
