import { createIcons, icons } from "lucide";
import { apiFetchFull, redirectToLogin } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { initResidentShell } from "/assets/js/components/resident-shell.js";
import { esc } from "/assets/js/lib/format.js";
import { openModelViewer, open360Viewer } from "./3d-viewer.js";
import { openApplyModal } from "./apply-modal.js";
import { parseUrls } from "./photos.js";

const el = (id) => document.getElementById(id);
const state = window.__PAGE_STATE__ || {};
const backHref = state.backHref || "/animals/";
const backLabel = state.backLabel || "Back to gallery";
let animal = null;
let applied = false;

function label(value) {
  const raw = String(value || "").trim();
  return raw ? raw.charAt(0).toUpperCase() + raw.slice(1) : "";
}

const VACCINE_LABELS = {
  none: { text: "Not vaccinated", cls: "rchip--alert" },
  partial: { text: "Partially vaccinated", cls: "rchip--sky" },
  complete: { text: "Fully vaccinated", cls: "rchip--success" },
};

const STATUS_CHIPS = {
  available: { text: "Available for adoption", cls: "rchip--success" },
  pending: { text: "Adoption pending", cls: "rchip--brand" },
  adopted: { text: "Already adopted", cls: "" },
  not_listed: { text: "Not listed for adoption", cls: "" },
};

function fmtDate(value) {
  if (!value) return "—";
  return String(value).slice(0, 10);
}

async function loadAnimal() {
  const payload = await apiFetchFull(`/animals/${encodeURIComponent(state.animalId)}`);
  animal = payload.data && payload.data.animal ? payload.data.animal : null;
  if (!animal) throw new Error("Animal not found");
}

async function loadApplied() {
  try {
    const payload = await apiFetchFull("/adoptions?per_page=100");
    const rows = Array.isArray(payload.data) ? payload.data : [];
    applied = rows.some(
      (row) =>
        row.animal_id === state.animalId && (row.status === "pending" || row.status === "approved")
    );
  } catch {
    applied = false;
  }
}

function galleryHtml(photos) {
  const main = photos[0] || null;
  return `
    <div class="rcard overflow-hidden">
      <div class="rgallery-main">
        ${
          main
            ? `<img id="gallery-main-img" src="${esc(main)}" alt="Photo of ${esc(animal.name)}">`
            : `<div class="racard-placeholder"><i data-lucide="${
                animal.species === "cat" ? "cat" : "dog"
              }"></i></div>`
        }
      </div>
      ${
        photos.length > 1
          ? `<div class="rgallery-thumbs" id="gallery-thumbs">
              ${photos
                .map(
                  (src, i) => `
                <button type="button" class="rgallery-thumb${i === 0 ? " is-active" : ""}" data-index="${i}" aria-label="Photo ${i + 1}">
                  <img src="${esc(src)}" alt="">
                </button>`
                )
                .join("")}
            </div>`
          : ""
      }
    </div>`;
}

function infoHtml({ spinCount }) {
  const status = STATUS_CHIPS[animal.adoption_status] || STATUS_CHIPS.not_listed;
  const vaccine = VACCINE_LABELS[(animal.medical && animal.medical.vaccination_status) || "none"] || VACCINE_LABELS.none;
  const sexLabel = animal.sex === "female" ? "♀ Female" : animal.sex === "male" ? "♂ Male" : "—";

  return `
  <div class="rcard rcard-pad">
    <div class="flex flex-wrap items-center gap-2">
      <h1 class="rpage-title">${esc(animal.name || "Unnamed")}</h1>
      <span class="rchip ${status.cls}">${esc(status.text)}</span>
    </div>
    <p class="rpage-sub">${esc(label(animal.breed_type) || "Mixed")} · ${esc(sexLabel)}${
    animal.age_estimate ? ` · ${esc(String(animal.age_estimate))}` : ""
  }</p>

    <dl class="rspec-list mt-4">
      <dt>Species</dt><dd>${esc(label(animal.species) || "—")}</dd>
      <dt>Breed</dt><dd>${esc(label(animal.breed_type) || "—")}</dd>
      <dt>Sex</dt><dd>${esc(sexLabel)}</dd>
      <dt>Age</dt><dd>${animal.age_estimate ? esc(String(animal.age_estimate)) : "—"}</dd>
      <dt>Color</dt><dd>${animal.color_markings ? esc(String(animal.color_markings)) : "—"}</dd>
      <dt>Listed</dt><dd>${esc(fmtDate(animal.created_at))}</dd>
      <dt>Vaccines</dt><dd><span class="rchip ${vaccine.cls}">${esc(vaccine.text)}</span></dd>
      ${
        animal.medical && animal.medical.last_checkup_date
          ? `<dt>Last checkup</dt><dd>${esc(fmtDate(animal.medical.last_checkup_date))}</dd>`
          : ""
      }
    </dl>

    ${
      animal.model_3d_url || spinCount
        ? `<div class="mt-4 flex flex-wrap gap-2">
            ${animal.model_3d_url ? `<button type="button" id="btn-view-3d" class="rbtn rbtn--ghost"><i data-lucide="rotate-3d"></i><span>View in 3D</span></button>` : ""}
            ${spinCount ? `<button type="button" id="btn-view-360" class="rbtn rbtn--ghost"><i data-lucide="refresh-cw"></i><span>360° view</span></button>` : ""}
          </div>`
        : ""
    }

    ${
      animal.adoption_status === "available"
        ? `<button type="button" id="btn-apply" class="rbtn rbtn--solid w-full mt-5">
            <i data-lucide="${applied ? "check-circle-2" : "heart"}"></i>
            <span>${applied ? "Application submitted" : "Apply to adopt"}</span>
          </button>`
        : ""
    }
  </div>`;
}

function aboutHtml() {
  if (!animal.description || String(animal.description).trim() === "") return "";
  return `
  <div class="rcard">
    <div class="rcard-head"><i data-lucide="info" class="text-primary"></i><h2 class="rcard-title">About</h2></div>
    <div class="rcard-pad text-sm leading-relaxed whitespace-pre-line">${esc(String(animal.description))}</div>
  </div>`;
}

function medicalHtml() {
  const m = animal.medical;
  if (!m) return "";
  const notes = m.medical_history_notes ? String(m.medical_history_notes).trim() : "";
  if (!notes && !m.vaccination_status) return "";
  return `
  <div class="rcard">
    <div class="rcard-head"><i data-lucide="stethoscope" class="text-primary"></i><h2 class="rcard-title">Medical summary</h2></div>
    <div class="rcard-pad text-sm leading-relaxed">
      ${notes ? `<p class="whitespace-pre-line">${esc(notes)}</p>` : `<p class="text-muted-foreground">No medical notes recorded yet.</p>`}
    </div>
  </div>`;
}

function historyHtml() {
  const rows = Array.isArray(animal.field_status) ? animal.field_status : [];
  if (!rows.length) return "";
  const items = rows
    .map(
      (row) => `
    <li class="rtimeline-item">
      <strong>${row.rescue_status === "rescued" ? "Rescued" : "Not yet rescued"}</strong>
      · ${row.health_status === "healthy" ? "healthy" : "needs care"}
      <br><span class="text-xs font-mono text-muted-foreground">${esc(fmtDate(row.logged_at))}</span>
    </li>`
    )
    .join("");
  return `
  <div class="rcard">
    <div class="rcard-head"><i data-lucide="history" class="text-primary"></i><h2 class="rcard-title">Status history</h2></div>
    <div class="rcard-pad">
      <ul class="rtimeline">${items}</ul>
    </div>
  </div>`;
}

function renderDetail() {
  const photos = parseUrls(animal.photo_urls);
  const spin = parseUrls(animal.photo_360_set);

  el("detail-root").innerHTML = `
    <div class="rdetail-grid">
      <div class="flex flex-col gap-4">${galleryHtml(photos)}${historyHtml()}</div>
      <div class="flex flex-col gap-4">${infoHtml({ spinCount: spin.length })}${aboutHtml()}${medicalHtml()}</div>
    </div>`;
  createIcons({ icons });

  el("detail-root").addEventListener("click", onDetailClick);
}

function onDetailClick(event) {
  const thumb = event.target.closest(".rgallery-thumb");
  if (thumb) {
    const index = Number(thumb.dataset.index);
    const photos = parseUrls(animal.photo_urls);
    const mainImg = el("gallery-main-img");
    if (mainImg && photos[index]) mainImg.src = photos[index];
    el("detail-root")
      .querySelectorAll(".rgallery-thumb")
      .forEach((t) => t.classList.toggle("is-active", t === thumb));
    return;
  }

  if (event.target.closest("#btn-view-3d")) {
    openModelViewer(animal);
    return;
  }
  if (event.target.closest("#btn-view-360")) {
    open360Viewer(animal);
    return;
  }
  if (event.target.closest("#btn-apply")) {
    if (applied) window.location.href = "/adoptions/";
    else openApplyModal(animal, { onApplied });
  }
}

function onApplied() {
  applied = true;
  const applyBtn = el("btn-apply");
  if (applyBtn) {
    applyBtn.innerHTML = `<i data-lucide="check-circle-2"></i><span>Application submitted</span>`;
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

  if (!state.animalId) {
    el("detail-root").innerHTML = `
      <div class="rempty">
        <i data-lucide="search-x"></i>
        <p class="rempty-title">No animal selected</p>
        <p class="rempty-text">Pick an animal from the <a class="underline text-primary" href="/animals/">adoption gallery</a>.</p>
      </div>`;
    createIcons({ icons });
    return;
  }

  Promise.all([loadAnimal(), loadApplied()])
    .then(() => renderDetail())
    .catch((err) => {
      if (err && err.status === 401) {
        redirectToLogin();
        return;
      }
      el("detail-root").innerHTML = `
      <div class="rempty">
        <i data-lucide="cat"></i>
        <p class="rempty-title">Profile unavailable</p>
        <p class="rempty-text">${esc(err.message || "This animal could not be loaded.")}</p>
        <a href="${esc(backHref)}" class="rbtn rbtn--ghost rbtn--sm"><i data-lucide="arrow-left"></i><span>${esc(backLabel)}</span></a>
      </div>`;
      createIcons({ icons });
    });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot);
} else {
  boot();
}
