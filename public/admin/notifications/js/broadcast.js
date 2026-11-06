import { createIcons, icons } from "lucide";
import { requireAuth, apiFetchFull } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { fetchRecentBroadcasts } from "/assets/js/admin/admin-data.js";
import { initShell } from "/assets/js/admin/app-shell.js";
import { initDropdownMenu } from "/shared/components/dropdown-menu/dropdown-menu.js";
import { initSelect } from "/shared/components/select/select.js";
import { toast } from "/shared/components/toast/toast.js";
import { EmptyState } from "/shared/components/empty-state/empty-state.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";

const MAX_LENGTH = 1000;

const TARGET_MAP = {
  all: ["all"],
  "role:resident": ["role:resident"],
  "role:rescuer": ["role:rescuer"],
  staff: ["role:admin", "role:rescuer"],
};

const TARGET_LABELS = {
  all: "everyone",
  "role:resident": "residents",
  "role:rescuer": "rescuers",
  staff: "staff (admins and rescuers)",
};

let selectedTarget = "all";

function esc(value) {
  return String(value ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  }[c]));
}

function timeAgo(value) {
  if (!value) return "—";
  const date = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return "—";
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const day = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const diff = Math.round((today - day) / 86400000);
  if (diff === 0) return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  if (diff === 1) return "Yesterday";
  if (diff < 7) return `${diff} days ago`;
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
}

function emptyState(icon, text) {
  return EmptyState({ icon, text });
}

function rowHtml(broadcast) {
  const recipients = parseInt(broadcast.recipients, 10) || 0;
  return `
    <tr>
      <td class="table-cell table-cell--strong">${esc(broadcast.message)}</td>
      <td class="table-cell"><span class="stamp stamp--sm stamp--accent">${recipients} sent</span></td>
      <td class="table-cell table-cell--mono table-cell--muted">${esc(timeAgo(broadcast.created_at))}</td>
      <td class="table-cell table-cell--right">
        <button type="button" class="btn btn--ghost btn--sm" data-broadcast-delete="${esc(broadcast.id || "")}">Remove</button>
      </td>
    </tr>`;
}

function renderBroadcasts(items) {
  const list = document.getElementById("broadcast-list");
  const totalEl = document.getElementById("broadcast-total");
  if (!list) return;
  if (!items.length) {
    list.className = "queue-empty";
    list.innerHTML = emptyState("megaphone", "No broadcasts yet. Compose your first announcement.");
  } else {
    list.className = "table-wrap";
    list.innerHTML = `
      <table class="table">
        <thead><tr class="table-head"><th>Message</th><th>Recipients</th><th>Sent</th><th></th></tr></thead>
        <tbody id="broadcast-rows">${items.map(rowHtml).join("")}</tbody>
      </table>`;
  }
  if (totalEl) totalEl.textContent = String(items.length);
  createIcons({ icons });
  list.querySelectorAll("[data-broadcast-delete]").forEach((button) => {
    button.addEventListener("click", () => removeBroadcast(button.dataset.broadcastDelete));
  });
}

async function removeBroadcast(id) {
  if (!id) return;
  const ok = await confirmDialog({
    title: "Remove this announcement?",
    message: "This hides the announcement from every inbox that received it.",
    confirmText: "Remove",
    danger: true,
  });
  if (!ok) return;
  try {
    await apiFetchFull(`/admin/notifications/${encodeURIComponent(id)}`, { method: "DELETE" });
    toast("Announcement removed.", { type: "success" });
    await refreshBroadcasts();
  } catch (err) {
    toast(err.message || "Could not remove the announcement.", { type: "error" });
  }
}

async function refreshBroadcasts() {
  try {
    renderBroadcasts(await fetchRecentBroadcasts());
  } catch {
    /* keep server-rendered rows on failure */
  }
}

function initCounter() {
  const message = document.getElementById("broadcast-message");
  const counter = document.getElementById("broadcast-count");
  if (!message || !counter) return;
  const update = () => {
    counter.textContent = String(Math.min(message.value.length, MAX_LENGTH));
  };
  message.addEventListener("input", update);
  update();
}

function initForm() {
  const form = document.getElementById("broadcast-form");
  const sendBtn = document.getElementById("broadcast-send");
  if (!form || !sendBtn) return;
  let inFlight = false;

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    send();
  });
  sendBtn.addEventListener("click", (e) => {
    e.preventDefault();
    send();
  });

  async function send() {
    if (inFlight || sendBtn.disabled) return;
    const messageEl = document.getElementById("broadcast-message");
    const message = messageEl ? messageEl.value.trim() : "";
    if (!message) {
      toast("Please enter a message", { type: "error" });
      messageEl?.focus();
      return;
    }
    const audience = TARGET_LABELS[selectedTarget] || "the selected audience";
    inFlight = true;
    sendBtn.disabled = true;
    const ok = await confirmDialog({
      title: "Send this announcement?",
      message: `This will notify ${audience}.`,
      confirmText: "Send",
    });
    if (!ok) {
      inFlight = false;
      sendBtn.disabled = false;
      return;
    }
    const targets = TARGET_MAP[selectedTarget] || TARGET_MAP.all;
    try {
      const payload = await apiFetchFull("/admin/notifications/broadcast", {
        method: "POST",
        body: { type: "admin_announcement", targets, message },
      });
      const sent = payload && payload.data && payload.data.sent;
      toast(`Announcement sent to ${sent} ${sent === 1 ? "user" : "users"}`, { type: "success" });
      if (messageEl) messageEl.value = "";
      messageEl?.dispatchEvent(new Event("input"));
      await refreshBroadcasts();
    } catch (err) {
      toast(err.message || "Failed to send broadcast", { type: "error" });
    } finally {
      inFlight = false;
      sendBtn.disabled = false;
    }
  }
}

function init() {
  bootstrapPageAuth();
  const user = requireAuth(["admin"]);
  if (!user) return;
  createIcons({ icons });
  initShell();
  initDropdownMenu(document);
  initSelect(document, {
    "broadcast-target": (value) => {
      selectedTarget = value;
    },
  });
  initCounter();
  initForm();
  refreshBroadcasts();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
