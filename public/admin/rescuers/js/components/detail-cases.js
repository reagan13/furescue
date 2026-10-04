import { createIcons, icons } from "lucide";
import { esc } from "./util.js";
import { shortId, timeAgo, titleCase } from "/admin/js/helpers.js";
import { rescuerAvatar } from "/admin/js/components/util.js";
import { state } from "../state.js";
import * as api from "/assets/js/admin/admin-data.js";
import { RescuerMeta } from "./detail-profile.js";

function caseStampCls(status) {
  if (status === "in_progress" || status === "resolved") return "stamp--accent";
  return "stamp--coral";
}

function enrichCase(c) {
  const report = c.report_id ? state.reports.find((r) => r.id === c.report_id) : null;
  const status = String(c.status || "open");
  return {
    id: c.id,
    shortId: shortId(c.id),
    status: titleCase(status),
    statusRaw: status,
    statusCls: caseStampCls(status),
    animal: report ? report.animal_description || "—" : "—",
    brgy: report ? report.address_text || "—" : "—",
    created: timeAgo(c.created_at),
    createdAt: c.created_at,
    updated: timeAgo(c.updated_at || c.created_at),
  };
}

function rescuerCases() {
  return (state.selectedRescuerCases || [])
    .map(enrichCase)
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
}

function activityLeaf(a) {
  return `
  <li class="tree-leaf">
    <span class="tree-dot"></span>
    <div class="tree-leaf-body">
      <div class="tree-leaf-head">
        <span class="stamp stamp--sm ${caseStampCls(a.action === "resolved" ? "resolved" : "open")}">${esc(titleCase(a.action))}</span>
        <span class="tree-leaf-role">${esc(a.actor_role || "—")}</span>
        <span class="tree-leaf-time">${timeAgo(a.created_at)}</span>
      </div>
      ${a.notes ? `<div class="tree-leaf-note">${esc(a.notes)}</div>` : ""}
    </div>
  </li>`;
}

function caseNode(c) {
  return `
  <li class="tree-node" data-case="${esc(c.id)}">
    <button class="tree-toggle" data-case-toggle="${esc(c.id)}" aria-expanded="false">
      <i data-lucide="chevron-right" class="tree-chevron"></i>
      <span class="stamp stamp--sm ${c.statusCls}">${esc(c.status)}</span>
      <span class="tree-node-id">${esc(c.shortId)}</span>
      <span class="tree-node-animal">${esc(c.animal)}</span>
      <span class="tree-node-meta">${esc(c.brgy)}</span>
    </button>
    <ul class="tree-children" data-case-children="${esc(c.id)}" hidden></ul>
  </li>`;
}

export function caseTree(cases) {
  if (cases.length === 0) {
    return `<div class="empty-state empty-state--sm"><i data-lucide="folder-open"></i><span>No past cases for this rescuer.</span></div>`;
  }
  return `<ul class="tree">${cases.map(caseNode).join("")}</ul>`;
}

export function rescuerCaseList() {
  return rescuerCases();
}

function rCaseCard(c) {
  return `
  <div class="r-case" data-case="${esc(c.id)}">
    <button type="button" class="r-case-summary" aria-expanded="false">
      <i data-lucide="chevron-right" class="r-case-chevron"></i>
      <span class="stamp stamp--sm ${c.statusCls}">${esc(c.status)}</span>
      <span class="r-case-id">${esc(c.shortId)}</span>
      <span class="r-case-animal">${esc(c.animal)}</span>
      <span class="r-case-meta">${esc(c.brgy)}</span>
      <span class="r-case-when">${esc(c.created)}</span>
    </button>
    <div class="r-case-details" hidden></div>
  </div>`;
}

function rescuerModalCases() {
  const cases = rescuerCases();
  if (cases.length === 0) {
    return `<div class="empty-state empty-state--sm"><i data-lucide="folder-open"></i><span>No past cases for this rescuer.</span></div>`;
  }
  return `<div class="r-case-list">${cases.map(rCaseCard).join("")}</div>`;
}

function caseActivityView(activity) {
  if (!activity || activity.length === 0) {
    return `<div class="empty-state empty-state--sm"><i data-lucide="clock"></i><span>No activity recorded for this case.</span></div>`;
  }
  return `<ul class="tree">${activity.map(activityLeaf).join("")}</ul>`;
}

export function openRescuerModal() {
  const r = state.selectedRescuer;
  if (!r) return;
  const overlay = document.createElement("div");
  overlay.className = "dialog-overlay";
  overlay.innerHTML = `
    <div class="dialog dialog--wide" role="dialog" aria-modal="true" aria-labelledby="rescuer-modal-title">
      <div class="dialog-head rescuer-modal-head">
        <button type="button" class="dialog-x" aria-label="Close"><i data-lucide="x"></i></button>
        <div class="rescuer-modal-identity">
          ${rescuerAvatar(r.profile_photo_url, r.full_name)}
          <h3 class="dialog-title" id="rescuer-modal-title">${esc(r.full_name || "Rescuer")}</h3>
          <div class="dialog-sub">${esc(shortId(r.id))}</div>
        </div>
      </div>
      <div class="dialog-body rescuer-modal-body">
        ${RescuerMeta(r)}
        <div class="rescuer-detail-section">
          <div class="rescuer-detail-section-head">
            <i data-lucide="history"></i>
            <h3>Past cases</h3>
            <span class="stamp stamp--sm stamp--accent">${state.selectedRescuerCases.length}</span>
          </div>
          <div class="rescuer-modal-cases">${rescuerModalCases()}</div>
        </div>
      </div>
    </div>`;

  document.body.appendChild(overlay);
  createIcons({ icons });

  const close = () => overlay.remove();
  overlay.querySelector(".dialog-x").addEventListener("click", close);
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) close();
  });

  overlay.querySelector(".rescuer-modal-cases").addEventListener("click", async (e) => {
    const card = e.target.closest(".r-case");
    if (!card) return;
    e.preventDefault();
    const id = card.dataset.case;
    const details = card.querySelector(".r-case-details");
    const summary = card.querySelector(".r-case-summary");
    const open = !card.classList.contains("is-open");
    card.classList.toggle("is-open", open);
    summary.setAttribute("aria-expanded", String(open));
    if (open && !details.dataset.loaded) {
      details.dataset.loaded = "1";
      details.innerHTML = `<div class="r-case-loading"><i data-lucide="loader" class="spin"></i> Loading case details…</div>`;
      createIcons({ icons });
      let activity = [];
      try {
        activity = (await api.fetchCaseActivity(id)) || [];
      } catch {
        activity = [];
      }
      details.innerHTML = caseActivityView(activity);
      createIcons({ icons });
    }
    details.hidden = !open;
  });
}

export async function toggleCaseNode(caseId) {
  const node = document.querySelector(`.tree-node[data-case="${caseId}"]`);
  if (!node) return;
  const children = node.querySelector(".tree-children");
  const toggle = node.querySelector(".tree-toggle");
  const expanded = toggle.getAttribute("aria-expanded") === "true";
  if (expanded) {
    toggle.setAttribute("aria-expanded", "false");
    children.hidden = true;
    return;
  }
  if (!state.caseActivity[caseId]) {
    try {
      state.caseActivity[caseId] = await api.fetchCaseActivity(caseId);
    } catch {
      state.caseActivity[caseId] = [];
    }
  }
  const activity = state.caseActivity[caseId] || [];
  children.innerHTML =
    activity.length === 0
      ? `<li class="tree-leaf tree-leaf--empty">No activity recorded.</li>`
      : activity.map(activityLeaf).join("");
  createIcons({ icons });
  toggle.setAttribute("aria-expanded", "true");
  children.hidden = false;
}
