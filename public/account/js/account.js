import { createIcons, icons } from "lucide";
import { hasPageSession, PORTAL_ROLES, requireAuth, apiFetch, redirectToLogin } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { initResidentShell } from "/assets/js/components/resident-shell.js";
import { initShell } from "/assets/js/admin/app-shell.js";
import { initDropdownMenu } from "/shared/components/dropdown-menu/dropdown-menu.js";
import { toast } from "/shared/components/toast/toast.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import { initAccountPhoto, refreshPhotoInitials } from "/account/js/photo.js";

const el = (id) => document.getElementById(id);

function showFormError(message) {
  const slot = el("account-error");
  if (!slot) return;
  const span = slot.querySelector("span");
  if (span) span.textContent = message;
  slot.hidden = false;
  createIcons({ icons });
}

function clearFormError() {
  const slot = el("account-error");
  if (slot) slot.hidden = true;
}

function paintShellName(name) {
  const label = document.querySelector("#profile-menu p.text-sm.font-semibold");
  if (label) label.textContent = name || "My Account";
}

async function onSubmit(event, user) {
  event.preventDefault();
  const form = event.currentTarget;
  const btn = el("account-save");
  const fullName = String(form.full_name.value || "").trim();
  clearFormError();
  if (!fullName) {
    showFormError("Full name is required.");
    return;
  }
  const ok = await confirmDialog({
    title: "Update this account?",
    message: "This will save changes to your profile.",
    confirmText: "Update account",
  });
  if (!ok) return;
  if (btn) btn.disabled = true;
  try {
    await apiFetch(`/users/${encodeURIComponent(user.id)}`, {
      method: "PATCH",
      body: {
        full_name: fullName,
        phone_number: String(form.phone_number.value || "").trim(),
        address: String(form.address.value || "").trim(),
      },
    });
    refreshPhotoInitials(fullName);
    paintShellName(fullName);
    toast("Account updated.", { type: "success" });
  } catch (err) {
    if (err && err.status === 401 && !hasPageSession()) {
      redirectToLogin();
      return;
    }
    const message = err.message || "Could not update your account.";
    showFormError(message);
    toast(message, { type: "error" });
  } finally {
    if (btn) btn.disabled = false;
  }
}

function initAdminDate() {
  const node = document.getElementById("admin-date");
  if (!node) return;
  node.textContent = new Date().toLocaleDateString("en-US", {
    weekday: "short",
    month: "short",
    day: "numeric",
  });
}

function initAccountShell(user) {
  if (user.role === "admin") {
    initShell();
    initDropdownMenu(document);
    initAdminDate();
    return;
  }
  initResidentShell();
}

function boot() {
  bootstrapPageAuth();
  const user = requireAuth(PORTAL_ROLES);
  if (!user) return;
  initAccountShell(user);
  createIcons({ icons });
  initAccountPhoto(user);
  el("account-form")?.addEventListener("submit", (event) => onSubmit(event, user));
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot);
} else {
  boot();
}
