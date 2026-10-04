import { createIcons, icons } from "lucide";
import { AppShell } from "/assets/js/admin/app-shell.js";
import { Button } from "/shared/components/button/button.js";
import { SkeletonCaseDetail } from "/shared/components/skeleton/skeleton.js";
import { renderWorkflow } from "./workflow.js";
import { renderCaseInfo, renderSourceReport } from "./info.js";
import { renderAttachments } from "./files.js";
import { renderProof } from "./files.js";
import { photos } from "./util.js";
import { state } from "../state.js";

function proofCount(caseData) {
  const fromCase = photos(caseData && caseData.resolution_photos);
  if (fromCase.length) return fromCase.length;
  return Array.isArray(state.proof) ? state.proof.length : 0;
}

function attachmentCount(caseData) {
  const fromReport = photos(caseData && caseData.report && caseData.report.photo_urls);
  if (fromReport.length) return fromReport.length;
  return Array.isArray(state.attachments) ? state.attachments.length : 0;
}

function resolveButtonHtml(caseData) {
  if (caseData.status !== "in_progress" || proofCount(caseData) < 1 || attachmentCount(caseData) < 1) return "";
  return Button({
    text: "Resolve",
    variant: "default",
    size: "sm",
    icon: "check-circle-2",
    attrs: 'data-cd-action="resolve"',
  });
}

export function renderActions(caseData) {
  const isResolved = caseData.status === "resolved";
  const locationBtn = Button({
    text: "See location",
    variant: "outline",
    size: "sm",
    icon: "map-pin",
    attrs: 'data-cd-action="location"',
  });
  if (isResolved) {
    return `<div class="cd-actions">${locationBtn}${Button({ text: "Resolved", variant: "outline", size: "sm", icon: "check-circle-2", attrs: "disabled" })}</div>`;
  }
  const assignLabel = caseData.assigned_rescuer_id ? "Reassign" : "Assign rescuer";
  return `
    <div class="cd-actions">
      ${locationBtn}
      ${Button({ text: assignLabel, variant: "outline", size: "sm", icon: "user-plus", attrs: 'data-cd-action="assign"' })}
      ${resolveButtonHtml(caseData)}
    </div>`;
}

function renderHeader(caseData) {
  return `
    <div class="page-head">
      <div>
        ${Button({ text: "Back to cases", variant: "outline", size: "sm", icon: "arrow-left", href: "/admin/cases/" })}
      </div>
      ${renderActions(caseData)}
    </div>`;
}

export function CaseDetailPage(caseData, { loading = false } = {}) {
  if (loading) {
    return AppShell({
      user: (caseData && caseData.user) || null,
      title: "Case detail",
      activeNav: "cases",
      children: SkeletonCaseDetail(),
    });
  }
  return AppShell({
    user: caseData.user,
    title: "Case detail",
    activeNav: "cases",
    children: [
      renderHeader(caseData),
      `<div class="case-detail-grid">`,
      `<div class="cd-col-workflow">${renderWorkflow(caseData)}</div>`,
      `<div class="cd-col-info">${renderCaseInfo(caseData)}${renderSourceReport(caseData)}</div>`,
      `<div class="cd-col-files">${renderAttachments(caseData)}</div>`,
      `<div class="cd-col-rescuer">${renderProof(caseData)}</div>`,
      `</div>`,
    ].join(""),
  });
}

function hydrateResolveButton(caseData) {
  const bar = document.querySelector(".cd-actions");
  if (!bar) return;
  const current = [...bar.querySelectorAll("button")].find((btn) => {
    const label = (btn.textContent || "").trim();
    return btn.dataset.cdAction === "resolve" || label === "Resolve";
  });
  const html = resolveButtonHtml(caseData);
  if (current) {
    if (html) current.outerHTML = html;
    else current.remove();
  } else if (html) {
    bar.insertAdjacentHTML("beforeend", html);
  }
}

function hydrateCaseDetail() {
  if (!window.__PAGE_STATE__ || !state.caseData) return;
  hydrateResolveButton(state.caseData);
  const filesCol = document.querySelector(".cd-col-files");
  if (filesCol) filesCol.innerHTML = renderAttachments(state.caseData);
  const proofCol = document.querySelector(".cd-col-rescuer");
  if (proofCol) proofCol.innerHTML = renderProof(state.caseData);
  const workflowCol = document.querySelector(".cd-col-workflow");
  if (workflowCol) workflowCol.innerHTML = renderWorkflow(state.caseData);
  createIcons({ icons });
}

if (typeof document !== "undefined") {
  document.addEventListener("DOMContentLoaded", () => {
    queueMicrotask(hydrateCaseDetail);
  });
}
