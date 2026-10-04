import { openDrawer } from "/shared/components/drawer/drawer.js";
import { mountPinMap } from "/assets/js/lib/leaflet.js";
import * as api from "/assets/js/admin/admin-data.js";
import { shortId, titleCase } from "../helpers.js";
import { firstPhoto } from "../insights.js";
import { state } from "../state.js";
import { esc } from "./text.js";

function reportPhotoHtml(r) {
  const src = firstPhoto(r && (r.photo_urls || r.photoUrls));
  if (!src) {
    return `<div class="drawer-photo-empty"><i data-lucide="image-off"></i><span>No photo attached</span></div>`;
  }
  return `<img class="drawer-photo" src="${esc(src)}" alt="Report photo">`;
}

function typewriter(el, text, speed = 26) {
  let i = 0;
  const cursor = '<span class="tw-cursor">|</span>';
  const step = () => {
    if (i >= text.length) {
      el.innerHTML = text;
      return;
    }
    el.innerHTML = text.slice(0, i) + cursor;
    i += 1;
    setTimeout(step, speed);
  };
  step();
}

export function details(id) {
  const r = state.reportsPending.items.find((i) => i.id === id);
  return r
    ? [
        { label: "Case", value: shortId(r.id) },
        { label: "Barangay", value: titleCase(r.address_text) || "—" },
        { label: "Reporter", value: shortId(r.resident_id) },
        { label: "Animal description", value: r.animal_description || "—" },
        { label: "Latitude", value: r.latitude != null ? String(r.latitude) : "—" },
        { label: "Longitude", value: r.longitude != null ? String(r.longitude) : "—" },
        { label: "Validation", value: titleCase(r.validation_status) || "—" },
        { label: "Status", value: titleCase(r.status) || "—" },
        { label: "Submitted", value: new Date(r.created_at).toLocaleString() },
      ]
    : [];
}

export function open(id) {
  const r = state.reportsPending.items.find((i) => i.id === id);
  if (!r) return;
  const rows = details(id)
    .map(
      (row) => `
    <div class="dialog-info-row">
      <span class="dialog-info-label">${esc(row.label)}</span>
      <span class="dialog-info-value">${esc(row.value)}</span>
    </div>`
    )
    .join("");

  openDrawer({
    title: "Report details",
    body: `
      <div class="dialog-info">${rows}</div>
      <div id="report-detail-map" class="drawer-map"></div>
      <div class="drawer-reported">
        ${reportPhotoHtml(r)}
        <span class="drawer-reported-text" id="drawer-reported-text"></span>
      </div>`,
    onMount: (bodyEl) => {
      const lat = Number(r.latitude);
      const lng = Number(r.longitude);
      const mapEl = bodyEl.querySelector("#report-detail-map");
      if (mapEl && Number.isFinite(lat) && Number.isFinite(lng)) {
        void mountPinMap(mapEl, lat, lng, { popup: esc(r.address_text || "Report location") });
      }

      const capEl = bodyEl.querySelector("#drawer-reported-text");
      if (capEl) {
        typewriter(capEl, `Reported at ${r.address_text || "Mati City"}`);
      }
    },
  });
}

export const actions = {
  verify: {
    queue: "reports",
    title: "Verify this report?",
    confirmText: "Verify",
    run: (id) => api.verifyReport(id),
  },
  dismiss: {
    queue: "reports",
    title: "Dismiss this report?",
    confirmText: "Dismiss",
    danger: true,
    withReason: true,
    reasonLabel: "Dismiss reason",
    reasonRequired: true,
    run: (id, reason) => api.dismissReport(id, reason),
  },
};

export function confirmMessage(action, id) {
  if (action === "verify") {
    return `Verify report ${shortId(id)}? This will create a case.`;
  }
  if (action === "dismiss") {
    return `Dismiss report ${shortId(id)}? This report will be closed.`;
  }
  return null;
}

export function info(action, id) {
  if (action !== "verify" && action !== "dismiss") return null;
  const r = state.reportsPending.items.find((i) => i.id === id);
  return r
    ? [
        { label: "Case", value: shortId(r.id) },
        { label: "Barangay", value: r.address_text || "—" },
        { label: "Reporter", value: shortId(r.resident_id) },
        { label: "Submitted", value: new Date(r.created_at).toLocaleString() },
      ]
    : [];
}

export function toastMessage(action, id, res) {
  if (action === "verify") {
    const caseId = res && res.data && res.data.case_id;
    return caseId
      ? `Report ${shortId(id)} verified · Case ${shortId(caseId)} created.`
      : `Report ${shortId(id)} verified.`;
  }
  if (action === "dismiss") {
    return `Report ${shortId(id)} dismissed.`;
  }
  return null;
}
