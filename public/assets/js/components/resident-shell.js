import { createIcons, icons } from "lucide";
import { clearSession, apiFetch } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { setNavBadge } from "/assets/js/lib/swr.js";
import { subscribeToNotifications } from "/assets/js/lib/notification-stream.js";
import { initDropdownMenu } from "/shared/components/dropdown-menu/dropdown-menu.js";
import { initFeedback } from "/assets/js/components/feedback.js";

function initMenuToggle() {
  const sidebar = document.getElementById("rside");
  const overlay = document.getElementById("roverlay");
  const toggle = document.getElementById("rmenu-toggle");
  if (!sidebar || !overlay || !toggle) return;

  const setOpen = (open) => {
    sidebar.classList.toggle("is-open", open);
    overlay.classList.toggle("is-visible", open);
    toggle.setAttribute("aria-expanded", String(open));
  };

  toggle.addEventListener("click", () => setOpen(!sidebar.classList.contains("is-open")));
  overlay.addEventListener("click", () => setOpen(false));
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") setOpen(false);
  });
}

function setBadgeEls(key, value) {
  const els = document.querySelectorAll(`[data-nav-badge="${key}"]`);
  els.forEach((el) => {
    const show = Number(value) > 0;
    el.hidden = !show;
    el.textContent = show ? String(value) : "";
    el.classList.toggle("is-empty", !show);
  });
}

function applyBadgeCount(count) {
  setNavBadge("notifications", count);
  setBadgeEls("notifications", count);
}

async function refreshNotificationBadge() {
  try {
    const data = await apiFetch("/notifications/unread-count");
    applyBadgeCount(Number(data && data.count) || 0);
  } catch {
    /* badge is best-effort */
  }
}

function applyStreamBadge(payload) {
  if (!payload || typeof payload !== "object") return;
  if (typeof payload.unread_count === "number") {
    applyBadgeCount(payload.unread_count);
    return;
  }
  const hasRow =
    (payload.notification && payload.notification.id != null) || payload.id != null;
  if (hasRow) {
    const shown = document.querySelector('[data-nav-badge="notifications"]');
    applyBadgeCount((Number(shown && shown.textContent) || 0) + 1);
    return;
  }
  void refreshNotificationBadge();
}

let notificationStream = null;

function stopNotificationStream() {
  if (!notificationStream) return;
  notificationStream.close();
  notificationStream = null;
}

function initDate() {
  document.querySelectorAll("[data-resident-date]").forEach((el) => {
    el.textContent = new Date().toLocaleDateString("en-US", {
      weekday: "short",
      month: "short",
      day: "numeric",
    });
  });
}

function initLogout() {
  document.querySelectorAll('[data-action="logout"]').forEach((el) => {
    el.addEventListener("click", (e) => {
      e.preventDefault();
      clearSession();
      window.location.replace("/auth/logout.php");
    });
  });
}

let booted = false;

export function initResidentShell() {
  if (booted) return;
  booted = true;
  bootstrapPageAuth();
  createIcons({ icons });
  initMenuToggle();
  initDate();
  initLogout();
  initDropdownMenu(document);
  initFeedback();
  refreshNotificationBadge();
  notificationStream = subscribeToNotifications(applyStreamBadge);
}

window.addEventListener("pagehide", stopNotificationStream);

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initResidentShell);
} else {
  initResidentShell();
}

export { setBadgeEls as setResidentNavBadge };
