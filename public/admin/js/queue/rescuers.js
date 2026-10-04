import { Button } from "/shared/components/button/button.js";
import { openDrawer } from "/shared/components/drawer/drawer.js";
import * as api from "/assets/js/admin/admin-data.js";
import { shortId, titleCase } from "../helpers.js";
import { state } from "../state.js";
import { esc } from "./text.js";

export function details(id) {
  const u = state.rescuersPending.items.find((i) => i.id === id);
  return u
    ? [
        { label: "Name", value: u.full_name || "—" },
        { label: "Email", value: u.email || "—" },
        { label: "Contact", value: u.phone_number || "—" },
        { label: "Role", value: titleCase(u.role) || "—" },
        { label: "Status", value: titleCase(u.account_status) || "—" },
        { label: "Submitted", value: new Date(u.created_at).toLocaleString() },
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
    title: "Rescuer application details",
    body: `<div class="dialog-info">${rows}</div>`,
    footer: `${Button({ text: "Reject", variant: "outline", attrs: 'data-drawer-act="reject"' })}
      ${Button({ text: "Approve", variant: "default", attrs: 'data-drawer-act="approve"' })}`,
    onMount: (bodyEl) => {
      const drawer = bodyEl.closest(".drawer");
      const reject = drawer.querySelector('[data-drawer-act="reject"]');
      const approve = drawer.querySelector('[data-drawer-act="approve"]');
      if (reject) reject.addEventListener("click", () => runAction("reject-rescuer", id));
      if (approve) approve.addEventListener("click", () => runAction("approve-rescuer", id));
    },
  });
}

export const actions = {
  "approve-rescuer": {
    queue: "rescuers",
    title: "Approve this rescuer?",
    confirmText: "Approve",
    run: (id) => api.approveRescuer(id),
  },
  "reject-rescuer": {
    queue: "rescuers",
    title: "Reject this rescuer?",
    confirmText: "Reject",
    danger: true,
    run: (id) => api.rejectRescuer(id),
  },
};

export function confirmMessage(action, id) {
  if (action !== "approve-rescuer" && action !== "reject-rescuer") return null;
  const u = state.rescuersPending.items.find((i) => i.id === id);
  const name = (u && u.full_name) || shortId(id);
  return action === "approve-rescuer"
    ? `Approve ${name} as a rescuer?`
    : `Reject ${name}'s rescuer application?`;
}

export function info(action, id) {
  if (action !== "approve-rescuer" && action !== "reject-rescuer") return null;
  const u = state.rescuersPending.items.find((i) => i.id === id);
  return u
    ? [
        { label: "Name", value: u.full_name || "—" },
        { label: "Contact", value: u.phone_number || "—" },
        { label: "Submitted", value: new Date(u.created_at).toLocaleString() },
      ]
    : [];
}

export function toastMessage(action, id) {
  if (action !== "approve-rescuer" && action !== "reject-rescuer") return null;
  const u = state.rescuersPending.items.find((i) => i.id === id);
  const name = (u && u.full_name) || shortId(id);
  return action === "approve-rescuer"
    ? `${name} approved as rescuer.`
    : `Rescuer application rejected for ${name}.`;
}
