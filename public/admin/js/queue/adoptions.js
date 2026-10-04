import { Button } from "/shared/components/button/button.js";
import { openDrawer } from "/shared/components/drawer/drawer.js";
import * as api from "/assets/js/admin/admin-data.js";
import { shortId, titleCase } from "../helpers.js";
import { state } from "../state.js";
import { esc } from "./text.js";

export function details(id) {
  const a = state.adoptionsPending.items.find((i) => i.id === id);
  return a
    ? [
        { label: "Application", value: shortId(a.id) },
        { label: "Applicant", value: a.applicant_name || shortId(a.applicant_id) },
        { label: "Animal", value: a.animal_name || shortId(a.animal_id) },
        { label: "Status", value: titleCase(a.status) || "—" },
        { label: "Submitted", value: new Date(a.created_at).toLocaleString() },
      ]
    : [];
}

export function open(id, runAction) {
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
    title: "Adoption application details",
    body: `<div class="dialog-info">${rows}</div>`,
    footer: `${Button({ text: "Decline", variant: "outline", attrs: 'data-drawer-act="decline"' })}
      ${Button({ text: "Approve", variant: "default", attrs: 'data-drawer-act="approve"' })}`,
    onMount: (bodyEl) => {
      const drawer = bodyEl.closest(".drawer");
      const decline = drawer.querySelector('[data-drawer-act="decline"]');
      const approve = drawer.querySelector('[data-drawer-act="approve"]');
      if (decline) decline.addEventListener("click", () => runAction("decline-adoption", id));
      if (approve) approve.addEventListener("click", () => runAction("approve-adoption", id));
    },
  });
}

export const actions = {
  "approve-adoption": {
    queue: "adopt",
    title: "Approve this adoption?",
    confirmText: "Approve",
    run: (id) => api.approveAdoption(id),
  },
  "decline-adoption": {
    queue: "adopt",
    title: "Decline this adoption?",
    confirmText: "Decline",
    danger: true,
    withReason: true,
    reasonLabel: "Decline reason",
    reasonRequired: true,
    run: (id, reason) => api.rejectAdoption(id, reason),
  },
};

export function confirmMessage(action, id) {
  if (action !== "approve-adoption" && action !== "decline-adoption") return null;
  const a = state.adoptionsPending.items.find((i) => i.id === id);
  const applicant = (a && a.applicant_name) || shortId(id);
  const animal = (a && a.animal_name) || "this animal";
  return action === "approve-adoption"
    ? `Approve the adoption application for ${applicant} · ${animal}?`
    : `Decline the adoption application for ${applicant} · ${animal}?`;
}

export function info(action, id) {
  if (action !== "approve-adoption" && action !== "decline-adoption") return null;
  const a = state.adoptionsPending.items.find((i) => i.id === id);
  return a
    ? [
        { label: "Applicant", value: a.applicant_name || shortId(a.applicant_id) },
        { label: "Animal", value: a.animal_name || shortId(a.animal_id) },
        { label: "Submitted", value: new Date(a.created_at).toLocaleString() },
      ]
    : [];
}

export function toastMessage(action, id) {
  if (action !== "approve-adoption" && action !== "decline-adoption") return null;
  const a = state.adoptionsPending.items.find((i) => i.id === id);
  const applicant = (a && a.applicant_name) || shortId(id);
  const animal = (a && a.animal_name) || "animal";
  return action === "approve-adoption"
    ? `Adoption approved for ${applicant} · ${animal}.`
    : `Adoption declined for ${applicant} · ${animal}.`;
}
