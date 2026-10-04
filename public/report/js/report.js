import { createIcons, icons } from "lucide";
import { requireAuth, apiFetchFull, apiUpload, redirectToLogin } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { toast } from "/shared/components/toast/toast.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import {
  initMap,
  initGeolocate,
  initAddressTouched,
  getLatLng,
  isInsideBounds,
  pauseReverseGeocode,
} from "./map.js";
import { initPhotoInput, getSelectedFiles } from "./photos.js";

const el = (id) => document.getElementById(id);
const state = window.__PAGE_STATE__ || {};
const bounds = state.bounds || { latMin: 6.89, latMax: 7.01, lngMin: 126.13, lngMax: 126.27 };

function showError(key, message) {
  const slot = document.querySelector(`[data-error-for="${key}"]`);
  if (!slot) return;
  if (message !== undefined) {
    const span = slot.querySelector("span");
    if (span) span.textContent = message;
  }
  slot.classList.remove("hidden");
  slot.classList.add("flex");
}

function clearError(key) {
  const slot = document.querySelector(`[data-error-for="${key}"]`);
  if (!slot) return;
  slot.classList.add("hidden");
  slot.classList.remove("flex");
}

function initDescCounter() {
  const field = el("animal_description");
  const counter = el("desc-count");
  if (!field || !counter) return;
  const update = () => {
    counter.textContent = `${field.value.length} / 2000`;
  };
  field.addEventListener("input", update);
  update();
}

async function uploadPhotos(reportId) {
  const files = getSelectedFiles();
  if (files.length === 0) return;
  const formData = new FormData();
  for (const file of files) {
    formData.append("photos[]", file);
  }
  await apiUpload(`/reports/${encodeURIComponent(reportId)}/media`, formData);
}

async function handleSubmit(e) {
  e.preventDefault();
  pauseReverseGeocode();
  bootstrapPageAuth();

  const description = el("animal_description").value.trim();
  const pin = getLatLng();
  const addressText = (el("address_text")?.value || "").trim();
  let valid = true;

  clearError("animal_description");
  clearError("photos");
  clearError("location");

  if (!description) {
    showError("animal_description");
    valid = false;
  }
  if (getSelectedFiles().length === 0) {
    showError("photos");
    valid = false;
  }
  if (!pin) {
    showError("location");
    valid = false;
  } else if (!isInsideBounds(pin.lat, pin.lng, bounds)) {
    showError("location", "Location is outside Mati City.");
    valid = false;
  }
  if (!valid) return;

  const ok = await confirmDialog({
    title: "Create this report?",
    message: "This will submit a new stray-animal report.",
    confirmText: "Create report",
  });
  if (!ok) return;

  const submit = el("report-submit");
  submit.disabled = true;
  const label = submit.querySelector("span");
  if (label) label.textContent = "Submitting…";

  try {
    const payload = await apiFetchFull("/reports", {
      method: "POST",
      body: {
        animal_description: description,
        latitude: pin.lat,
        longitude: pin.lng,
        ...(addressText ? { address_text: addressText } : {}),
      },
    });

    const report = (payload && payload.data && payload.data.report) || (payload && payload.data);
    if (!report || !report.id) throw new Error("Unexpected server response.");
    const flaggedDuplicate =
      report.validation_status === "flagged_duplicate" || report.duplicate_of_report_id != null;

    try {
      await uploadPhotos(report.id);
    } catch (uploadErr) {
      toast(`Report created, but some photos failed to upload: ${uploadErr.message}`, {
        type: "error",
        duration: 6000,
      });
    }

    sessionStorage.setItem(
      "furescue_flash",
      JSON.stringify({
        type: flaggedDuplicate ? "info" : "success",
        message: flaggedDuplicate
          ? "A similar report exists nearby — yours was flagged for review."
          : "Report submitted! Our team will verify it shortly.",
      })
    );
    window.location.href = "/reports/";
  } catch (err) {
    submit.disabled = false;
    if (label) label.textContent = "Submit report";
    if (err.status === 401) {
      toast("Your session expired. Please sign in again.", { type: "error" });
      setTimeout(redirectToLogin, 1200);
      return;
    }
    if (err.code === "OUT_OF_BOUNDS") {
      showError("location", "Location is outside Mati City.");
      return;
    }
    toast(err.message || "Could not submit the report.", { type: "error" });
  }
}

async function boot() {
  bootstrapPageAuth();
  if (!requireAuth()) return;
  createIcons({ icons });
  el("report-form")?.addEventListener("submit", handleSubmit);
  initDescCounter();
  initPhotoInput({
    onChange: () => {
      if (getSelectedFiles().length > 0) clearError("photos");
    },
  });
  initAddressTouched();
  try {
    const mapApi = await initMap(bounds, {
      onPin: () => clearError("location"),
    });
    initGeolocate(bounds, mapApi);
  } catch (err) {
    toast(err.message || "Map failed to load. Enter coordinates inside Mati City.", {
      type: "error",
    });
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    void boot();
  });
} else {
  void boot();
}
