import { createIcons, icons } from "lucide";
import { readPageClick, readPageSizeChange } from "/shared/components/pagination/pagination.js";
import { queueState, queuePageSize, state, refreshQueue } from "./state.js";
import { toast } from "/shared/components/toast/toast.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import { openDrawer } from "/shared/components/drawer/drawer.js";
import { shortId } from "./helpers.js";
import {
  ReportsQueueInner,
  RescuersQueueInner,
  HealthQueueInner,
  AdoptionQueueInner,
  KpiGrid,
} from "./components.js";
import { esc } from "./queue/text.js";
import * as reports from "./queue/reports.js";
import * as rescuers from "./queue/rescuers.js";
import * as health from "./queue/health.js";
import * as adoptions from "./queue/adoptions.js";

const QUEUE_INNERS = {
  reports: ReportsQueueInner,
  rescuers: RescuersQueueInner,
  health: HealthQueueInner,
  adopt: AdoptionQueueInner,
};

const QUEUE_TAB_LABELS = {
  reports: "Reports",
  rescuers: "Rescuers",
  health: "Health",
  adopt: "Adoptions",
};

const QUEUES = {
  reports,
  rescuers,
  health,
  adopt: adoptions,
};

const ACTIONS = {
  ...reports.actions,
  ...rescuers.actions,
  ...adoptions.actions,
};

function detailRows(key, id) {
  const queue = QUEUES[key];
  return queue && queue.details ? queue.details(id) : [];
}

function openDetailsDrawer(key, id) {
  const rows = detailRows(key, id)
    .map(
      (row) => `
    <div class="dialog-info-row">
      <span class="dialog-info-label">${esc(row.label)}</span>
      <span class="dialog-info-value">${esc(row.value)}</span>
    </div>`
    )
    .join("");
  const queue = QUEUES[key];
  if (queue && queue.open) {
    queue.open(id, runAction);
    return;
  }
  openDrawer({
    title: key === "health" ? "Health update details" : "Details",
    body: `<div class="dialog-info">${rows}</div>`,
  });
}

export function initQueueTabs() {
  const tabs = document.querySelectorAll(".q-btn");
  const panels = document.querySelectorAll(".queue-panel");

  tabs.forEach((btn) => {
    btn.addEventListener("click", () => {
      panels.forEach((p) => p.classList.add("is-hidden"));
      const panel = document.getElementById("queue-" + btn.dataset.q);
      if (panel) panel.classList.remove("is-hidden");
      tabs.forEach((b) => b.classList.toggle("is-active", b === btn));
    });
  });
}

export function renderQueuePanel(key) {
  const panel = document.getElementById("queue-" + key);
  if (!panel) return;
  panel.innerHTML = QUEUE_INNERS[key]();
  createIcons({ icons });
}

export function initQueuePagination() {
  document.querySelectorAll(".queue-panel").forEach((panel) => {
    const key = panel.id.replace("queue-", "");
    panel.addEventListener("click", (e) => {
      const page = readPageClick(e.target, queueState[key]);
      if (!page) return;
      queueState[key] = page;
      renderQueuePanel(key);
    });
    panel.addEventListener("change", (e) => {
      const next = readPageSizeChange(e.target, queuePageSize[key]);
      if (!next) return;
      queuePageSize[key] = next;
      queueState[key] = 1;
      renderQueuePanel(key);
    });
  });
}

function updateTabCount(key) {
  const total =
    key === "reports"
      ? state.reportsPending.total
      : key === "rescuers"
        ? state.rescuersPending.total
        : key === "adopt"
          ? state.adoptionsPending.total
          : state.healthUpdates.total;
  const btn = document.querySelector(`.q-btn[data-q="${key}"]`);
  if (btn) btn.innerHTML = `${QUEUE_TAB_LABELS[key]} &middot; ${total}`;
}

function updateKpiGrid() {
  const el = document.getElementById("kpi-grid");
  if (!el) return;
  el.outerHTML = KpiGrid();
  createIcons({ icons });
}

function updateDecisionCount() {
  const el = document.getElementById("greeting-sub");
  if (el) {
    el.textContent = `${state.decisionCount} items need a decision today across reports, rescuers, health records, and adoptions.`;
  }
}

function firstDefined(values) {
  for (const value of values) {
    if (value != null) return value;
  }
  return null;
}

function confirmMessage(action, id) {
  return (
    firstDefined([
      reports.confirmMessage(action, id),
      rescuers.confirmMessage(action, id),
      adoptions.confirmMessage(action, id),
    ]) ?? `Are you sure you want to ${ACTIONS[action].confirmText.toLowerCase()} ${shortId(id)}?`
  );
}

function buildInfo(action, id) {
  return (
    firstDefined([
      reports.info(action, id),
      rescuers.info(action, id),
      adoptions.info(action, id),
    ]) ?? []
  );
}

function toastMessage(action, id, res) {
  return (
    firstDefined([
      reports.toastMessage(action, id, res),
      rescuers.toastMessage(action, id),
      adoptions.toastMessage(action, id),
    ]) ?? "Action completed."
  );
}

async function runAction(action, id) {
  const cfg = ACTIONS[action];
  if (!cfg || !id) return;
  const ok = await confirmDialog({
    title: cfg.title,
    message: confirmMessage(action, id),
    info: buildInfo(action, id),
    confirmText: cfg.confirmText,
    cancelText: "Cancel",
    danger: cfg.danger,
    withReason: cfg.withReason,
    reasonLabel: cfg.reasonLabel,
    reasonRequired: cfg.reasonRequired,
    run: ({ reason }) => cfg.run(id, reason),
  });
  if (!ok) return;

  toast(toastMessage(action, id, ok), { type: "success" });
  await refreshQueue(cfg.queue);
  renderQueuePanel(cfg.queue);
  updateTabCount(cfg.queue);
  updateKpiGrid();
  updateDecisionCount();
}

export function initQueueActions() {
  document.querySelectorAll(".queue-panel").forEach((panel) => {
    panel.addEventListener("click", async (e) => {
      const link = e.target.closest("a[data-action]");
      if (!link) return;
      e.preventDefault();
      const action = link.dataset.action;
      const id = link.dataset.id;
      if (action === "details") {
        const key = panel.id.replace("queue-", "");
        if (key === "reports") {
          reports.open(id);
        } else {
          openDetailsDrawer(key, id);
        }
        return;
      }
      await runAction(action, id);
    });
  });
}
