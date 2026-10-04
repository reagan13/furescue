import { createIcons, icons } from "lucide";
import * as api from "/assets/js/admin/admin-data.js";
import { toast } from "/shared/components/toast/toast.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import { showLoader, hideLoader } from "/shared/components/loader/loader.js";
import { Button } from "/shared/components/button/button.js";
import { Label } from "/shared/components/label/label.js";
import { Select, initSelect } from "/shared/components/select/select.js";
import { shortId } from "/admin/js/helpers.js";
import { state, loadCaseDetail } from "../state.js";
import { CaseDetailPage } from "./actions.js";
import { renderLocation } from "./location.js";

const MAX_ATTACH_BYTES = 10 * 1024 * 1024;
const MAX_PROOF_BYTES = 5 * 1024 * 1024;
const MAX_FILES = 8;

function paintUploadNames(form) {
  const input = form.querySelector('input[type="file"]');
  const names = form.querySelector(".cd-upload-names");
  const error = form.querySelector(".cd-upload-error");
  if (error) {
    error.hidden = true;
    error.textContent = "";
  }
  const files = Array.from((input && input.files) || []);
  if (!names) return;
  if (!files.length) {
    names.hidden = true;
    names.textContent = "";
    return;
  }
  names.hidden = false;
  names.textContent = files.map((file) => file.name).join(", ");
}

function validateUploadFiles(files, kind) {
  if (!files.length) return "Choose at least one file.";
  if (files.length > MAX_FILES) return `Up to ${MAX_FILES} files at a time.`;
  const maxBytes = kind === "proof" ? MAX_PROOF_BYTES : MAX_ATTACH_BYTES;
  const oversized = files.find((file) => file.size > maxBytes);
  if (oversized) {
    const mb = Math.round(maxBytes / (1024 * 1024));
    return `"${oversized.name}" is larger than ${mb} MB.`;
  }
  return "";
}

function setUploadError(form, message) {
  const error = form.querySelector(".cd-upload-error");
  if (!error) return;
  error.textContent = message || "";
  error.hidden = !message;
}

async function submitCaseUpload(form) {
  const kind = form.dataset.cdUpload;
  const input = form.querySelector('input[type="file"]');
  const files = Array.from((input && input.files) || []);
  const problem = validateUploadFiles(files, kind);
  if (problem) {
    setUploadError(form, problem);
    return;
  }
  const reportId = (state.caseData && (state.caseData.report_id || (state.caseData.report && state.caseData.report.id))) || "";
  const id = caseId();
  if (kind === "attachments" && !reportId) {
    setUploadError(form, "This case has no linked report.");
    return;
  }
  if (kind === "proof" && !id) {
    setUploadError(form, "Case is missing.");
    return;
  }
  const ok = await confirmDialog({
    title: kind === "proof" ? "Upload rescue proof?" : "Upload attached files?",
    message: kind === "proof"
      ? "This will add proof photos to the case."
      : "This will add files to the source report.",
    confirmText: "Upload",
  });
  if (!ok) return;
  const submit = form.querySelector('button[type="submit"]');
  if (submit) submit.disabled = true;
  showLoader("Uploading…");
  try {
    const formData = new FormData();
    for (const file of files) {
      formData.append(kind === "proof" ? "files[]" : "photos[]", file);
    }
    if (kind === "proof") {
      await api.uploadCaseProof(id, formData);
      toast("Rescue proof uploaded.", { type: "success" });
    } else {
      await api.uploadReportMedia(reportId, formData);
      toast("Attached files uploaded.", { type: "success" });
    }
    await loadAndRemount();
  } catch (err) {
    const message = (err && err.message) || "Could not upload files.";
    setUploadError(form, message);
    toast(message, { type: "error" });
  } finally {
    hideLoader();
    if (submit) submit.disabled = false;
    createIcons({ icons });
  }
}

export function mountCaseDetail() {
  const app = document.getElementById("app");
  if (!app) return;
  app.innerHTML = CaseDetailPage(state.caseData);
  initCaseDetailEvents();
  createIcons({ icons });
}

function caseId() {
  return (state.caseData && state.caseData.id) || state.caseId
    || new URLSearchParams(window.location.search).get("id");
}

export function initCaseDetailEvents() {
  if (!state.caseData && window.__PAGE_STATE__ && window.__PAGE_STATE__.caseData) {
    Object.assign(state, window.__PAGE_STATE__);
  }
  const root = document.getElementById("app");
  if (!root) return;
  if (!root.dataset.cdEventsBound) {
    root.dataset.cdEventsBound = "1";
    root.addEventListener("click", async (e) => {
      const btn = e.target.closest("[data-cd-action]");
      if (!btn || !root.contains(btn)) return;
      const action = btn.dataset.cdAction;
      const id = caseId();
      if (action === "resolve" && id) await resolveCase(id);
      if (action === "assign" && id) await assignCase(id, state.caseData && state.caseData.report_id);
      if (action === "location") renderLocation(state.caseData || {});
    });
    root.addEventListener("change", (e) => {
      const form = e.target.closest("[data-cd-upload]");
      if (!form || !root.contains(form)) return;
      paintUploadNames(form);
    });
    root.addEventListener("submit", async (e) => {
      const form = e.target.closest("[data-cd-upload]");
      if (!form || !root.contains(form)) return;
      e.preventDefault();
      await submitCaseUpload(form);
    });
  }
  createIcons({ icons });
}

async function loadAndRemount() {
  if (window.__PAGE_STATE__) {
    window.location.reload();
    return;
  }
  await loadCaseDetail(state.caseId);
  mountCaseDetail();
}

async function resolveCase(caseId) {
  const ok = await confirmDialog({
    title: "Resolve this case?",
    message: `Mark case ${shortId(caseId)} as resolved? This closes the rescue.`,
    confirmText: "Resolve",
    cancelText: "Cancel",
    run: () => api.resolveCase(caseId),
  });
  if (!ok) return;
  toast("Case resolved.");
  await loadAndRemount();
}

async function openAssignDialog(caseId, reportId) {
  let rescuers = [];
  try {
    const { items } = await api.fetchRescuers();
    rescuers = items || [];
  } catch {
    rescuers = [];
  }
  const onDuty = rescuers.filter(
    (u) => u.role === "rescuer" && u.account_status === "active" && (u.duty_status || "off_duty") === "on_duty"
  );
  const options = onDuty.map((u) => ({ value: u.id, label: u.full_name || "Unnamed rescuer" }));

  return new Promise((resolve) => {
    const overlay = document.createElement("div");
    overlay.className = "dialog-overlay";
    overlay.innerHTML = `
      <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="cd-assign-title">
        <div class="dialog-head">
          <div class="dialog-title-wrap">
            <i data-lucide="user-plus" class="dialog-icon"></i>
            <h3 class="dialog-title" id="cd-assign-title">Assign rescuer</h3>
          </div>
          <button type="button" class="dialog-x" aria-label="Close"><i data-lucide="x"></i></button>
        </div>
        <div class="dialog-body">
          <p class="dialog-message">Assign a rescuer to case ${shortId(caseId)}${reportId ? ` (report ${shortId(reportId)})` : ""}. Only on-duty rescuers can be assigned.</p>
          ${options.length
            ? `${Label({ htmlFor: "cd-assign-rescuer", className: "dialog-label", required: true, children: "Rescuer" })}
               ${Select({ id: "cd-assign-rescuer", options, placeholder: "Select a rescuer…", className: "w-full" })}`
            : `<div class="empty-state"><i data-lucide="siren"></i><span>No on-duty rescuers available.</span></div>`}
        </div>
        <div class="dialog-foot">
          ${Button({ text: "Cancel", variant: "outline", attrs: 'data-act="cancel"' })}
          ${Button({ text: "Assign", variant: "default", attrs: 'data-act="ok"', className: options.length ? "" : "hidden" })}
        </div>
      </div>`;

    document.body.appendChild(overlay);
    createIcons({ icons });

    let selected = "";
    initSelect(overlay, { "cd-assign-rescuer": (val) => { selected = val; } });
    if (options.length) {
      const trigger = overlay.querySelector("#cd-assign-rescuer [data-select-value]");
      if (trigger) trigger.textContent = "";
    }

    const close = () => {
      overlay.remove();
      resolve(null);
    };

    let busy = false;
    const submit = async () => {
      if (busy) return;
      if (!selected) {
        toast("Please select a rescuer.", { type: "error" });
        return;
      }
      const name = onDuty.find((u) => u.id === selected);
      const isReassign = !!(state.caseData && state.caseData.assigned_rescuer_id);
      busy = true;
      const payload = await confirmDialog({
        title: isReassign ? "Reassign this case?" : "Assign this case?",
        message: `${isReassign ? "Reassign" : "Assign"} case ${shortId(caseId)} to ${(name && name.full_name) || "this rescuer"}?`,
        confirmText: isReassign ? "Reassign" : "Assign",
        cancelText: "Cancel",
        run: () => api.assignRescuer(caseId, selected),
      });
      if (!payload) {
        busy = false;
        return;
      }
      overlay.remove();
      resolve(payload);
      toast(`Case ${shortId(caseId)} assigned to ${(name && name.full_name) || "rescuer"}.`, { type: "success" });
    };

    overlay.querySelector('[data-act="ok"]').addEventListener("click", submit);
    overlay.querySelector('[data-act="cancel"]').addEventListener("click", close);
    overlay.querySelector(".dialog-x").addEventListener("click", close);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) close();
    });
  });
}

async function assignCase(caseId, reportId) {
  try {
    const payload = await openAssignDialog(caseId, reportId);
    if (!payload) return;
    await loadAndRemount();
  } catch (e) {
    toast(e.message || "Failed to assign rescuer.", { type: "error" });
  }
}

export function initCaseDetail() {
  if (!state.caseData) return;
  mountCaseDetail();
  createIcons({ icons });
}
