import { createIcons, icons } from "lucide";
import { apiFetch, PORTAL_ROLES, requireAuth } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { esc, timeAgo } from "/assets/js/lib/format.js";
import { subscribeToNotifications } from "/assets/js/lib/notification-stream.js";
import { initResidentShell, setResidentNavBadge } from "/assets/js/components/resident-shell.js";
import { toast } from "/shared/components/toast/toast.js";

const TYPE_STYLE = [
  [/(report_)?dismiss/, { icon: "x-circle", tone: "alert", label: "Dismissed" }],
  [/approved|verified|completed|accepted/, { icon: "check-circle-2", tone: "success" }],
  [/rejected|rejected_listing|error/, { icon: "alert-circle", tone: "alert" }],
  [/message/, { icon: "message-square", tone: "" }],
  [/adoption|listing|adopt/, { icon: "heart", tone: "" }],
  [/case|rescue/, { icon: "ambulance", tone: "" }],
  [/report/, { icon: "map-pin", tone: "" }],
  [/announce|broadcast/, { icon: "megaphone", tone: "" }],
];

function pageRole() {
  const user = window.__PAGE_STATE__ && window.__PAGE_STATE__.user;
  return user && user.role ? user.role : "resident";
}

function linkFor(type) {
  const role = pageRole();
  const kind = type === "adoption_listing" ? "listing" : type;
  if (kind === "case" && role !== "rescuer") return null;
  if (kind === "report" && role === "rescuer") {
    return { href: "/cases/", label: "View cases" };
  }
  const table = {
    report: { href: "/reports/?id=", label: "View report" },
    adoption: { href: "/adoptions/?id=", label: "View adoption" },
    listing: { href: "/listings/?id=", label: "View listing" },
    case: { href: "/cases/detail.php?id=", label: "View case" },
  };
  return table[kind] || null;
}

let inboxStream = null;

const state = {
  items: [],
  filter: "all",
  loadError: "",
};

function styleFor(type) {
  const t = String(type || "").toLowerCase();
  for (const [pattern, style] of TYPE_STYLE) {
    if (pattern.test(t)) return style;
  }
  return { icon: "bell", tone: "" };
}

function relatedFor(n) {
  const spec = linkFor(n.related_type);
  if (!spec) return null;
  const id = n.related_id;
  if (id == null || id === "") return { href: spec.href.split("?")[0], label: spec.label };
  if (spec.href.includes("?id=")) {
    return { href: `${spec.href}${encodeURIComponent(id)}`, label: spec.label };
  }
  const joiner = spec.href.includes("?") ? "&" : "?";
  return { href: `${spec.href}${joiner}id=${encodeURIComponent(id)}`, label: spec.label };
}

function unreadCount() {
  return state.items.filter((n) => !n.is_read).length;
}

function syncBadge() {
  const count = unreadCount();
  setResidentNavBadge("notifications", count);
}

function rowHtml(n) {
  const style = styleFor(n.type);
  const related = relatedFor(n);
  const unread = !n.is_read;
  const clickable = Boolean(related);
  return `
  <li
    class="notif-item ${unread ? "is-unread" : ""} ${clickable ? "is-clickable" : ""}"
    data-id="${esc(n.id)}"
    ${clickable ? `data-href="${esc(related.href)}" tabindex="0" role="link"` : ""}
  >
    <span class="notif-icon ${style.tone ? `notif-icon--${style.tone}` : ""}">
      <i data-lucide="${esc(style.icon)}"></i>
      ${unread ? '<span class="notif-dot" aria-hidden="true"></span>' : ""}
    </span>
    <span class="notif-body">
      <p class="notif-msg">${esc(n.message)}</p>
      <span class="notif-meta">
        <span class="notif-time">${esc(timeAgo(n.created_at))}</span>
        ${related ? `<span class="notif-link">${esc(related.label)} <i data-lucide="chevron-right"></i></span>` : ""}
      </span>
    </span>
    ${
      unread
        ? `<button type="button" class="notif-mark-btn" data-mark="${esc(n.id)}" aria-label="Mark as read">
             <i data-lucide="check"></i>
           </button>`
        : '<span aria-hidden="true"></span>'
    }
  </li>`;
}

function renderList() {
  const list = document.getElementById("notif-list");
  if (!list) return;

  const visible =
    state.filter === "unread" ? state.items.filter((n) => !n.is_read) : state.items;

  if (state.loadError) {
    list.innerHTML = `
      <li class="rempty">
        <i data-lucide="wifi-off"></i>
        <p class="rempty-title">Could not load notifications</p>
        <p class="rempty-text">${esc(state.loadError)}</p>
      </li>`;
    createIcons({ icons });
    return;
  }

  if (!visible.length) {
    const empty =
      state.filter === "unread"
        ? {
            icon: "bell-off",
            title: "You're all caught up",
            text: "No unread notifications right now.",
          }
        : {
            icon: "bell",
            title: "Nothing here yet",
            text: "Updates about your reports, adoptions, and messages will appear here.",
          };
    list.innerHTML = `
      <li class="rempty">
        <i data-lucide="${empty.icon}"></i>
        <p class="rempty-title">${empty.title}</p>
        <p class="rempty-text">${empty.text}</p>
      </li>`;
    createIcons({ icons });
    return;
  }

  list.innerHTML = visible.map(rowHtml).join("");
  createIcons({ icons });
}

function renderUnreadChip() {
  const chip = document.getElementById("notif-unread-count");
  if (!chip) return;
  const count = unreadCount();
  chip.hidden = count === 0;
  chip.textContent = String(count);
}

async function loadNotifications({ silent = false } = {}) {
  try {
    const items = await apiFetch("/notifications?per_page=100");
    state.items = Array.isArray(items) ? items : [];
    state.loadError = "";
    renderList();
    renderUnreadChip();
    syncBadge();
  } catch (err) {
    state.loadError = err.message || "Could not load notifications.";
    renderList();
    if (!silent) toast(state.loadError, { type: "error" });
  }
}

async function markOne(id) {
  try {
    await apiFetch(`/notifications/${encodeURIComponent(id)}/read`, { method: "PATCH" });
    const item = state.items.find((n) => n.id === id);
    if (item) item.is_read = true;
    renderList();
    renderUnreadChip();
    syncBadge();
  } catch (err) {
    toast(err.message || "Could not mark as read.", { type: "error" });
  }
}

function boot() {
  bootstrapPageAuth();
  const user = requireAuth(PORTAL_ROLES);
  if (!user) return;
  initResidentShell();

  document.querySelectorAll(".notif-tab").forEach((tab) => {
    tab.addEventListener("click", () => {
      state.filter = String(tab.dataset.filter || "all");
      document.querySelectorAll(".notif-tab").forEach((t) => {
        const active = t === tab;
        t.classList.toggle("is-active", active);
        t.setAttribute("aria-selected", String(active));
      });
      renderList();
    });
  });

  document.getElementById("notif-mark-all")?.addEventListener("click", async () => {
    if (!unreadCount()) {
      toast("No unread notifications.", { type: "info" });
      return;
    }
    try {
      await apiFetch("/notifications/read-all", { method: "POST" });
      state.items.forEach((n) => {
        n.is_read = true;
      });
      renderList();
      renderUnreadChip();
      syncBadge();
      toast("All notifications marked as read.", { type: "success" });
    } catch (err) {
      toast(err.message || "Could not mark all as read.", { type: "error" });
    }
  });

  const list = document.getElementById("notif-list");
  const openRow = (row) => {
    void markOne(String(row.dataset.id));
    const href = row.dataset.href;
    if (href) window.location.href = href;
  };

  list?.addEventListener("click", (e) => {
    const markBtn = e.target.closest("[data-mark]");
    if (markBtn) {
      e.stopPropagation();
      void markOne(String(markBtn.dataset.mark));
      return;
    }
    const row = e.target.closest(".notif-item[data-id]");
    if (row && row.dataset.href) openRow(row);
  });

  list?.addEventListener("keydown", (e) => {
    if (e.key !== "Enter" && e.key !== " ") return;
    const row = e.target.closest(".notif-item[data-id]");
    if (!row || !row.dataset.href || e.target !== row) return;
    e.preventDefault();
    openRow(row);
  });

  void loadNotifications().finally(() => {
    inboxStream = subscribeToNotifications(applyInboxStream);
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot);
} else {
  boot();
}

function rowFromPayload(payload) {
  if (!payload || typeof payload !== "object") return null;
  const candidate =
    payload.notification && typeof payload.notification === "object"
      ? payload.notification
      : payload;
  return candidate.id != null ? candidate : null;
}

function applyInboxStream(payload) {
  const row = rowFromPayload(payload);
  if (row) {
    const idx = state.items.findIndex((n) => n.id === row.id);
    if (idx >= 0) state.items.splice(idx, 1);
    state.items.unshift(row);
    renderList();
    renderUnreadChip();
    syncBadge();
    return;
  }
  if (payload && typeof payload.unread_count === "number") {
    setResidentNavBadge("notifications", payload.unread_count);
  }
}

function closeInboxStream() {
  if (!inboxStream) return;
  inboxStream.close();
  inboxStream = null;
}

window.addEventListener("pagehide", closeInboxStream);
window.addEventListener("unload", closeInboxStream);
