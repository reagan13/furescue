import { createIcons, icons } from "lucide";
import { apiFetchFull, redirectToLogin } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { initResidentShell } from "/assets/js/components/resident-shell.js";
import { toast } from "/shared/components/toast/toast.js";
import { esc, timeAgo } from "/assets/js/lib/format.js";
import { openListingForm } from "./form.js";

const el = (id) => document.getElementById(id);

const STATUS_CHIPS = {
  pending_review: { text: "In review", cls: "rchip--brand" },
  approved: { text: "Live", cls: "rchip--success" },
  rejected: { text: "Rejected", cls: "rchip--alert" },
};

const animalMap = new Map();

function parseUrls(value) {
  if (!value) return [];
  let parsed = value;
  if (typeof parsed === "string") {
    try {
      parsed = JSON.parse(parsed);
    } catch {
      return [];
    }
  }
  return Array.isArray(parsed) ? parsed.filter((u) => typeof u === "string" && u.trim() !== "") : [];
}

function animalName(animalId) {
  const a = animalMap.get(animalId);
  return a && a.name && String(a.name).trim() !== "" ? String(a.name).trim() : "an animal";
}

function rowHtml(row) {
  const chip = STATUS_CHIPS[row.status] || STATUS_CHIPS.pending_review;
  const photo = (animalMap.get(row.animal_id) && parseUrls(animalMap.get(row.animal_id).photo_urls)[0]) || null;

  return `
  <li class="rrow" data-id="${esc(row.id)}">
    <span class="rrow-icon">
      ${
        photo
          ? `<img src="${esc(photo)}" alt="" class="h-full w-full rounded-lg object-cover">`
          : `<i data-lucide="paw-print"></i>`
      }
    </span>
    <div class="rrow-main">
      <p class="rrow-title">${esc(animalName(row.animal_id))}</p>
      <p class="rrow-sub">Posted ${esc(timeAgo(row.created_at))} · <a class="underline text-primary" href="/animals/detail.php?id=${esc(
    row.animal_id
  )}">view profile</a></p>
      ${
        row.status === "rejected" && row.review_notes
          ? `<p class="rform-error mt-1"><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i><span>Reviewer notes: ${esc(String(row.review_notes))}</span></p>`
          : ""
      }
      ${row.status === "approved" ? `<p class="rrow-sub mt-1 font-semibold text-primary">This listing is public — adopters can now apply.</p>` : ""}
    </div>
    <div class="rrow-side"><span class="rchip ${chip.cls}">${esc(chip.text)}</span></div>
  </li>`;
}

async function loadAnimalMap() {
  const payload = await apiFetchFull("/animals?per_page=100");
  (Array.isArray(payload.data) ? payload.data : []).forEach((a) => animalMap.set(a.id, a));
}

async function load() {
  const list = el("listing-list");
  list.innerHTML = `<li class="rempty"><i data-lucide="loader-circle"></i><p class="rempty-text">Loading…</p></li>`;
  createIcons({ icons });

  try {
    await loadAnimalMap();
    const payload = await apiFetchFull("/adoption-listings?per_page=100");
    const rows = Array.isArray(payload.data) ? payload.data : [];
    if (!rows.length) {
      list.innerHTML = "";
      el("listings-empty").hidden = false;
    } else {
      el("listings-empty").hidden = true;
      list.innerHTML = rows.map(rowHtml).join("");
    }
    createIcons({ icons });
  } catch (err) {
    if (err && err.status === 401) {
      redirectToLogin();
      return;
    }
    list.innerHTML = `<li class="rempty"><i data-lucide="triangle-alert"></i><p class="rempty-text">${esc(
      err.message || "Could not load your listings."
    )}</p></li>`;
    createIcons({ icons });
  }
}

function boot() {
  const user = bootstrapPageAuth();
  if (!user) {
    redirectToLogin();
    return;
  }
  initResidentShell();

  const ready = load();
  el("btn-new-listing").addEventListener("click", () => {
    ready
      .then(() => openListingForm({ onCreated: load }))
      .catch((err) => {
        if (err && err.status === 401) redirectToLogin();
        else toast(err.message || "Could not open the form.", { type: "error" });
      });
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot);
} else {
  boot();
}
