import { Button } from "/shared/components/button/button.js";
import { esc, timeAgo } from "/assets/js/lib/format.js";
import { shortId, statusChip } from "./status.js";

function spec(label, value) {
  return `<dt>${esc(label)}</dt><dd>${value}</dd>`;
}

function photoGrid(urls, emptyText) {
  if (!urls.length) {
    return `<div class="rempty"><i data-lucide="image-off"></i><p class="rempty-text">${esc(emptyText)}</p></div>`;
  }
  const main = urls[0];
  const thumbs = urls
    .map(
      (url, i) =>
        `<a class="rgallery-thumb${i === 0 ? " is-active" : ""}" href="${esc(url)}" target="_blank" rel="noopener">
          <img src="${esc(url)}" alt="Case photo" loading="lazy">
        </a>`
    )
    .join("");
  return `
    <div class="min-w-0">
      <a class="rgallery-main" href="${esc(main)}" target="_blank" rel="noopener">
        <img src="${esc(main)}" alt="Case photo">
      </a>
      ${urls.length > 1 ? `<div class="rgallery-thumbs">${thumbs}</div>` : ""}
    </div>`;
}

function actionBar(item) {
  if (item.status === "assigned") {
    return `
      <div class="rpage-actions" style="flex-wrap:wrap">
        ${Button({ text: "Accept", icon: "check", attrs: `data-case-act="accept" data-id="${esc(item.id)}"` })}
        ${Button({ text: "Decline", variant: "destructive", icon: "x", attrs: `data-case-act="decline" data-id="${esc(item.id)}"` })}
      </div>`;
  }
  return "";
}

function pinMap(item) {
  const lat = Number(item.latitude);
  const lng = Number(item.longitude);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return `<p class="field-hint">No map pin was saved with this report.</p>`;
  }
  return `<div id="case-pin-map" class="case-pin-map map-canvas map-canvas--leaflet mt-4" data-lat="${esc(String(lat))}" data-lng="${esc(String(lng))}" role="img" aria-label="Map pin for the assigned report"></div>`;
}

function vaccineLine(row) {
  if (!row || typeof row !== "object") return "";
  const name = row.name || row.vaccine || row.type || "Vaccination";
  const date = row.date_given || row.date || "";
  return `${name}${date ? ` · ${date}` : ""}`;
}

function healthPanel(item) {
  const health = item.health;
  if (!health || !health.animal) {
    return `
      <section class="rcard min-w-0">
        <div class="rcard-head"><h2 class="rcard-title"><i data-lucide="heart-pulse"></i>Animal health</h2></div>
        <div class="rcard-pad"><p class="field-hint">No animal record is linked to this case yet. City Vet adds the animal on the web console.</p></div>
      </section>`;
  }
  const animal = health.animal;
  const shots = Array.isArray(health.vaccinations) ? health.vaccinations.map(vaccineLine).filter(Boolean) : [];
  const rate = health.heart_rate_bpm == null ? "—" : `${health.heart_rate_bpm} bpm`;
  return `
    <section class="rcard min-w-0" id="case-health">
      <div class="rcard-head"><h2 class="rcard-title"><i data-lucide="heart-pulse"></i>Animal health</h2></div>
      <div class="rcard-pad">
        <dl class="rspec-list">
          ${spec("Name", esc(animal.name || "Unnamed"))}
          ${spec("Species", esc(animal.species || "—"))}
          ${spec("Notes", esc(health.medical_history_notes || "—"))}
          ${spec("Vaccination", esc(health.vaccination_status || "none"))}
          ${spec("Latest heart rate", esc(rate))}
        </dl>
        ${shots.length ? `<ul class="mt-3 text-sm">${shots.map((line) => `<li>${esc(line)}</li>`).join("")}</ul>` : `<p class="field-hint mt-3">No vaccination recorded yet.</p>`}
        <form id="case-vax-form" class="mt-4">
          <p class="field-label">Add a vaccination</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <input id="case-vax-name" class="input" name="name" maxlength="80" placeholder="Vaccine name" required>
            <input id="case-vax-date" class="input" name="date_given" type="date" required>
          </div>
          <p class="rform-error mt-2" id="case-vax-error" hidden><i data-lucide="alert-circle"></i><span></span></p>
          <button type="submit" class="rbtn rbtn--solid rbtn--sm mt-2">Save vaccination</button>
        </form>
        <form id="case-hr-form" class="mt-4">
          <p class="field-label">Add a heart-rate reading</p>
          <input id="case-hr" class="input" name="heart_rate_bpm" type="number" min="1" max="400" placeholder="Beats per minute" required>
          <p class="rform-error mt-2" id="case-hr-error" hidden><i data-lucide="alert-circle"></i><span></span></p>
          <button type="submit" class="rbtn rbtn--solid rbtn--sm mt-2">Save heart rate</button>
        </form>
      </div>
    </section>`;
}

function proofPanel(item) {
  const canUpload = item.status === "in_progress";
  const photos = item.resolution_photos || [];
  if (!canUpload && !photos.length && item.status !== "resolved") return "";

  const form = canUpload
    ? `<form id="proof-form" class="file-attach mt-4" enctype="multipart/form-data">
        <span class="file-attach__title field-label"><i data-lucide="image-plus"></i>Upload proof photos</span>
        <p class="field-hint">Choose image files from this device. URL paste is not accepted.</p>
        <div class="file-attach__pick">
          <input id="proof-files" class="input" type="file" name="files[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
        </div>
        <p id="proof-names" class="file-attach__names text-xs text-muted-foreground" hidden></p>
        <p class="rform-error" id="proof-error" hidden><i data-lucide="alert-circle"></i><span></span></p>
        <button type="submit" class="rbtn rbtn--solid rbtn--sm mt-2" id="proof-submit">
          <i data-lucide="upload"></i><span>Upload proof</span>
        </button>
      </form>`
    : "";

  return `
    <section class="rcard min-w-0">
      <div class="rcard-head">
        <h2 class="rcard-title"><i data-lucide="camera"></i>Rescue proof</h2>
      </div>
      <div class="rcard-pad">
        ${photoGrid(photos, canUpload ? "No proof uploaded yet." : "No rescue proof uploaded.")}
        ${form}
      </div>
    </section>`;
}

export function renderDetail(item) {
  const notes = String(item.resolution_notes || "").trim();
  return `
    <div class="rpage-head">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          ${statusChip(item.status)}
          <span class="text-xs text-muted-foreground">${esc(shortId(item.id))}</span>
        </div>
        <h1 class="rpage-title mt-2">Rescue case</h1>
        <p class="rpage-sub">Updated ${esc(timeAgo(item.updated_at || item.created_at) || "recently")}</p>
      </div>
      ${actionBar(item)}
    </div>

    <div class="rdetail-grid">
      <section class="rcard min-w-0">
        <div class="rcard-head">
          <h2 class="rcard-title"><i data-lucide="clipboard-list"></i>Case details</h2>
        </div>
        <div class="rcard-pad">
          <dl class="rspec-list">
            ${spec("Status", statusChip(item.status))}
            ${spec("Created", esc(timeAgo(item.created_at) || "—"))}
            ${spec("Updated", esc(timeAgo(item.updated_at) || "—"))}
            ${notes ? spec("Notes", esc(notes)) : ""}
          </dl>
        </div>
      </section>

      <section class="rcard min-w-0">
        <div class="rcard-head">
          <h2 class="rcard-title"><i data-lucide="file-text"></i>Source report</h2>
        </div>
        <div class="rcard-pad">
          <dl class="rspec-list">
            ${spec("Animal", esc(item.animal_description || "—"))}
            ${spec("Location", esc(item.address_text || "—"))}
            ${item.species ? spec("Species", esc(item.species)) : ""}
          </dl>
          <div class="mt-4">${pinMap(item)}</div>
          <div class="mt-4">${photoGrid(item.photo_urls || [], "No report photos.")}</div>
        </div>
      </section>

      ${healthPanel(item)}
      ${proofPanel(item)}
    </div>`;
}

export function renderDetailError(message, { missing = false } = {}) {
  return `
    <div class="rempty">
      <i data-lucide="${missing ? "search-x" : "triangle-alert"}"></i>
      <p class="rempty-title">${missing ? "Case not found" : "Could not load this case"}</p>
      <p class="rempty-text">${esc(message)}</p>
      <a href="/cases/" class="rbtn rbtn--ghost rbtn--sm"><i data-lucide="arrow-left"></i><span>Back to My Cases</span></a>
    </div>`;
}

export function renderDetailLoading() {
  return `<div class="rempty"><i data-lucide="loader-circle"></i><p class="rempty-text">Loading case…</p></div>`;
}
