import { createIcons, icons } from "lucide";
import {
  apiFetch,
  apiUpload,
  getAccessToken,
  getRefreshToken,
  getSessionUser,
  hasPageSession,
  redirectToLogin,
  setSession,
} from "/assets/js/lib/api.js";
import { toast } from "/shared/components/toast/toast.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";

const MAX_BYTES = 5 * 1024 * 1024;
const ACCEPT = new Set(["image/jpeg", "image/png", "image/webp"]);

function initialsFrom(name) {
  const parts = String(name || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2);
  const letters = parts.map((part) => part.slice(0, 1).toUpperCase()).join("");
  return letters || "?";
}

function paintPhoto(url) {
  const img = document.getElementById("account-photo-img");
  const fallback = document.getElementById("account-photo-fallback");
  const remove = document.getElementById("account-photo-remove");
  const hasPhoto = Boolean(url);
  if (img) {
    img.src = hasPhoto ? url : "";
    img.hidden = !hasPhoto;
  }
  if (fallback) fallback.hidden = hasPhoto;
  if (remove) remove.hidden = !hasPhoto;
  document.querySelectorAll("[data-shell-avatar]").forEach((el) => {
    el.setAttribute("src", hasPhoto ? url : "");
    el.hidden = !hasPhoto;
  });
  document.querySelectorAll("[data-shell-avatar-fallback]").forEach((el) => {
    el.hidden = hasPhoto;
  });
}

function persistSessionPhoto(url) {
  const session = getSessionUser();
  if (!session) return;
  const next = { ...session, profile_photo_url: url || "" };
  setSession({
    access_token: getAccessToken(),
    refresh_token: getRefreshToken(),
    user: next,
  });
  if (window.__PAGE_STATE__ && window.__PAGE_STATE__.user) {
    window.__PAGE_STATE__.user.profile_photo_url = url || "";
  }
}

function handleAuthError(err) {
  if (err && err.status === 401 && !hasPageSession()) {
    redirectToLogin();
    return true;
  }
  return false;
}

async function uploadPhoto(user, file) {
  const form = new FormData();
  form.append("file", file);
  const payload = await apiUpload(`/users/${encodeURIComponent(user.id)}/profile-photo`, form);
  return payload && payload.data && payload.data.user ? payload.data.user.profile_photo_url : "";
}

export function initAccountPhoto(user) {
  const input = document.getElementById("account-photo-input");
  const change = document.getElementById("account-photo-change");
  const remove = document.getElementById("account-photo-remove");
  const fallback = document.getElementById("account-photo-fallback");
  if (fallback && !fallback.textContent.trim()) {
    fallback.textContent = initialsFrom(user.full_name);
  }

  change?.addEventListener("click", () => input?.click());
  input?.addEventListener("change", async () => {
    const file = input.files && input.files[0];
    input.value = "";
    if (!file) return;
    if (!ACCEPT.has(file.type)) {
      toast("Use a JPG, PNG, or WEBP photo.", { type: "error" });
      return;
    }
    if (file.size > MAX_BYTES) {
      toast("Photo must be 5 MB or smaller.", { type: "error" });
      return;
    }
    const img = document.getElementById("account-photo-img");
    const hasPhoto = Boolean(img && !img.hidden && img.src);
    const ok = await confirmDialog({
      title: hasPhoto ? "Update this profile photo?" : "Upload this profile photo?",
      message: hasPhoto
        ? "This will replace your current profile photo."
        : "This will set a new profile photo on your account.",
      confirmText: hasPhoto ? "Update photo" : "Upload photo",
    });
    if (!ok) return;
    if (change) change.disabled = true;
    try {
      const url = await uploadPhoto(user, file);
      paintPhoto(url);
      persistSessionPhoto(url);
      createIcons({ icons });
      toast("Profile photo updated.", { type: "success" });
    } catch (err) {
      if (handleAuthError(err)) return;
      toast(err.message || "Could not upload your photo.", { type: "error" });
    } finally {
      if (change) change.disabled = false;
    }
  });

  remove?.addEventListener("click", async () => {
    const ok = await confirmDialog({
      title: "Delete this profile photo?",
      message: "This will remove your profile photo from your account.",
      confirmText: "Delete photo",
      danger: true,
    });
    if (!ok) return;
    remove.disabled = true;
    try {
      await apiFetch(`/users/${encodeURIComponent(user.id)}/profile-photo`, { method: "DELETE" });
      paintPhoto("");
      persistSessionPhoto("");
      toast("Profile photo removed.", { type: "success" });
    } catch (err) {
      if (handleAuthError(err)) return;
      toast(err.message || "Could not remove your photo.", { type: "error" });
    } finally {
      remove.disabled = false;
    }
  });
}

export function refreshPhotoInitials(name) {
  const letters = initialsFrom(name);
  const fallback = document.getElementById("account-photo-fallback");
  if (fallback) fallback.textContent = letters;
  document.querySelectorAll("[data-shell-avatar-fallback]").forEach((el) => {
    el.textContent = letters;
  });
}
