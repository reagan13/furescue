import { toast } from "/shared/components/toast/toast.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import { updateAnimal, upsertAnimalMedical, createAdoptionListing, deleteAnimal } from "/assets/js/admin/admin-data.js";
import { ui, record, paint, reloadRecord } from "./context.js";
import {
  openAllVaccinationsDrawer,
  openVaccinationExplainer,
  openVaccineScheduleDrawer,
} from "./components/vaccinations.js";
import { openVaccinationDialog, deleteSelectedVaccinations } from "./dialogs/vaccinations.js";
import { openVitalDialog } from "./dialogs/vitals.js";
import { openDocumentDialog, openDocumentPreview, openAllDocumentsDrawer } from "./dialogs/documents.js";

export const HEALTH_READY_HINT =
  "Animal must have a vaccination record and vitals before it can be listed for adoption.";

export function isHealthReady(r = record) {
  if (!r) return false;
  const hasVaccination = Array.isArray(r.vaccinations) && r.vaccinations.length > 0;
  const hasVital = Array.isArray(r.vitals) && r.vitals.length > 0;
  return hasVaccination && hasVital;
}

export function gateAdoptionUi(root = document) {
  const ready = isHealthReady(record);
  root.querySelectorAll('[data-action="post-for-adoption"]').forEach((btn) => {
    btn.disabled = !ready;
    if (!ready) {
      btn.setAttribute("title", HEALTH_READY_HINT);
      btn.setAttribute("aria-disabled", "true");
    } else {
      btn.removeAttribute("title");
      btn.removeAttribute("aria-disabled");
    }
  });
}

async function saveRecord() {
  if (!record || !record.id || ui.saving) return;
  const name = (document.getElementById("hr-name")?.value || "").trim();
  const adoptionStatus = document.getElementById("hr-adoption-status-value")?.value || record.adoptionStatus || "";
  const deworming = document.getElementById("hr-deworming-value")?.value || record.overview?.deworming || "";
  const neutered = document.getElementById("hr-neutered-value")?.value || record.overview?.neutered || "";
  if (!name) {
    toast("Name is required.", { type: "error" });
    return;
  }
  if (adoptionStatus === "available" && !isHealthReady(record)) {
    toast(HEALTH_READY_HINT, { type: "error" });
    return;
  }
  ui.saving = true;
  const ok = await confirmDialog({
    title: "Save changes to this health record?",
    message: `Save demographics and status for "${name}"?`,
    confirmText: "Save",
    cancelText: "Cancel",
    run: async () => {
      await updateAnimal(record.id, { name, adoption_status: adoptionStatus });
      await upsertAnimalMedical(record.id, { deworming_status: deworming, neutered });
    },
  });
  ui.saving = false;
  if (!ok) return;
  toast("Demographics and status saved.", { type: "success" });
  ui.editing = false;
  ui.mode = null;
  await reloadRecord();
}

async function postForAdoption() {
  if (!record || !record.id) return;
  if (!isHealthReady(record)) {
    toast(HEALTH_READY_HINT, { type: "error" });
    return;
  }
  const ok = await confirmDialog({
    title: "List this animal for adoption?",
    message: `Post "${record.name || "this animal"}" as available for adoption?`,
    confirmText: "Post",
    cancelText: "Cancel",
    run: () => createAdoptionListing(record.id),
  });
  if (!ok) return;
  toast("Listed for adoption.", { type: "success" });
  await reloadRecord();
}

async function deleteRecord() {
  if (!record || !record.id) return;
  const ok = await confirmDialog({
    title: "Delete this animal?",
    message: `Hide "${record.name || "this animal"}"? The record stays in the database and no longer appears in the shelter list.`,
    confirmText: "Delete",
    cancelText: "Cancel",
    danger: true,
    run: () => deleteAnimal(record.id),
  });
  if (!ok) return;
  toast("Animal deleted.", { type: "success" });
  window.location.href = "/admin/health-records/";
}

export function handleAction(actionEl) {
  const action = actionEl.getAttribute("data-action");
  switch (action) {
    case "edit-record":
      ui.editing = !ui.editing;
      ui.mode = ui.editing ? "edit" : null;
      ui.openForm = null;
      ui.vaxSelecting = false;
      paint();
      return;
    case "add-record":
      if (ui.editing && ui.mode === "add") {
        ui.editing = false;
        ui.mode = null;
      } else {
        ui.editing = true;
        ui.mode = "add";
      }
      ui.openForm = null;
      ui.vaxSelecting = false;
      paint();
      return;
    case "save-record":
      saveRecord();
      return;
    case "post-for-adoption":
      postForAdoption();
      return;
    case "delete-record":
      deleteRecord();
      return;
    case "open-vaccination-modal":
      openVaccinationDialog();
      return;
    case "vax-toggle-select":
      ui.vaxSelecting = !ui.vaxSelecting;
      paint();
      return;
    case "vax-delete-selected":
      deleteSelectedVaccinations();
      return;
    case "vax-edit-row": {
      const idx = parseInt(actionEl.getAttribute("data-idx") || "-1", 10);
      if (!Number.isNaN(idx) && idx >= 0) openVaccinationDialog(idx);
      return;
    }
    case "view-all-vaccinations":
      openAllVaccinationsDrawer(record.vaccinations);
      return;
    case "view-all-documents":
      openAllDocumentsDrawer(record && record.documents);
      return;
    case "explain-vaccinations":
      openVaccinationExplainer(record.vaccinations);
      return;
    case "vaccine-schedule":
      openVaccineScheduleDrawer(record);
      return;
    case "open-vital-modal":
      openVitalDialog();
      return;
    case "edit-vital":
      openVitalDialog(actionEl.getAttribute("data-label") || null);
      return;
    case "open-document-modal":
      openDocumentDialog();
      return;
    case "edit-document": {
      const ei = parseInt(actionEl.getAttribute("data-idx") || "-1", 10);
      if (!Number.isNaN(ei) && ei >= 0) openDocumentDialog(ei);
      return;
    }
    case "view-document": {
      const di = parseInt(actionEl.getAttribute("data-idx") || "-1", 10);
      if (!Number.isNaN(di) && di >= 0) openDocumentPreview(di);
      return;
    }
    default:
      return;
  }
}

function startAdoptionGate() {
  const apply = () => gateAdoptionUi(document.getElementById("app") || document);
  const boot = () => {
    apply();
    const app = document.getElementById("app");
    if (app && !app.dataset.adoptionGateBound) {
      app.dataset.adoptionGateBound = "1";
      new MutationObserver(apply).observe(app, { childList: true });
    }
  };
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => setTimeout(boot, 0));
  } else {
    setTimeout(boot, 0);
  }
}

startAdoptionGate();
