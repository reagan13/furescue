import { apiFetch, apiFetchFull, apiUpload } from "/assets/js/lib/api.js";
import { parsePhotos } from "./status.js";

function asCase(raw) {
  if (!raw || typeof raw !== "object") return null;
  const nested = raw.case && typeof raw.case === "object" ? raw.case : raw;
  const report = nested.report && typeof nested.report === "object" ? nested.report : {};
  return {
    ...nested,
    animal_description: nested.animal_description || report.animal_description || "",
    address_text: nested.address_text || report.address_text || "",
    photo_urls: parsePhotos(nested.photo_urls || report.photo_urls),
    latitude: nested.latitude ?? report.latitude ?? null,
    longitude: nested.longitude ?? report.longitude ?? null,
    species: nested.species || report.species || "",
    health: nested.health || null,
    resolution_photos: parsePhotos(nested.resolution_photos || nested.proof),
    status: String(nested.status || ""),
    report,
  };
}

export function unwrapCase(payload) {
  if (!payload) return null;
  if (payload.case) return asCase(payload.case);
  if (payload.data) return unwrapCase(payload.data);
  return asCase(payload);
}

export async function fetchCases(status = "") {
  const params = new URLSearchParams({ page: "1", per_page: "50" });
  if (status) params.set("status", status);
  const payload = await apiFetchFull(`/cases?${params}`);
  const rows = Array.isArray(payload.data) ? payload.data : [];
  return rows.map(asCase).filter(Boolean);
}

export async function fetchCase(id) {
  const payload = await apiFetchFull(`/cases/${encodeURIComponent(id)}`);
  let item = unwrapCase(payload);
  const needsReport =
    item && item.report_id && (!item.animal_description || !item.address_text || !item.photo_urls.length);
  if (!needsReport) return item;

  try {
    const reportPayload = await apiFetchFull(`/reports/${encodeURIComponent(item.report_id)}`);
    const report = reportPayload.data && reportPayload.data.report
      ? reportPayload.data.report
      : reportPayload.data;
    if (report) {
      item = asCase({
        ...item,
        report,
        animal_description: item.animal_description || report.animal_description,
        address_text: item.address_text || report.address_text,
        photo_urls: item.photo_urls.length ? item.photo_urls : report.photo_urls,
      });
    }
  } catch {
    /* report join is optional */
  }
  return item;
}

export function acceptCase(id) {
  return apiFetch(`/cases/${encodeURIComponent(id)}/accept`, { method: "POST", body: {} });
}

export function declineCase(id) {
  return apiFetch(`/cases/${encodeURIComponent(id)}/decline`, { method: "POST", body: {} });
}

export function toggleDuty(id, status) {
  return apiFetch(`/rescuers/${encodeURIComponent(id)}/duty`, {
    method: "PATCH",
    body: { status },
  });
}

export function uploadProof(id, files) {
  const formData = new FormData();
  for (const file of files) {
    formData.append("files[]", file);
  }
  return apiUpload(`/cases/${encodeURIComponent(id)}/proof`, formData);
}

export function proofFromUpload(payload) {
  const data = payload && payload.data ? payload.data : payload;
  if (!data) return [];
  if (Array.isArray(data.proof)) return parsePhotos(data.proof);
  if (data.case) return parsePhotos(data.case.resolution_photos || data.case.proof);
  return parsePhotos(data.resolution_photos);
}
