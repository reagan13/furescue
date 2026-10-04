import { createIcons, icons } from "lucide";
import { openDrawer } from "/shared/components/drawer/drawer.js";
import { Button } from "/shared/components/button/button.js";
import { shortId, titleCase } from "/admin/js/helpers.js";
import { esc, applicantName, animalName, animalHeadline, firstPhoto } from "../components/util.js";
import { findAdoption, runApprove, runDecline, runComplete } from "./actions.js";

function row(label, value) {
  return `
    <div class="dialog-info-row">
      <span class="dialog-info-label">${esc(label)}</span>
      <span class="dialog-info-value">${esc(value || "—")}</span>
    </div>`;
}

function when(value) {
  if (!value) return "—";
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? "—" : d.toLocaleString();
}

export function openDetailsDrawer(id) {
  const a = findAdoption(id);
  if (!a) return;
  const name = applicantName(a) || shortId(a.applicant_id);
  const animal = animalHeadline(a) || animalName(a) || shortId(a.animal_id);
  const photo = firstPhoto(a.photo_urls);
  const species = (a.species || "dog").toLowerCase() === "cat" ? "cat" : "dog";
  const thumb = `
    <div class="app-drawer-animal">
      <div class="animal-thumb animal-thumb--sm animal-thumb--${esc(species)}">
        ${photo
          ? `<img src="${esc(photo)}" alt="${esc(animalName(a) || "Animal")}" class="animal-thumb-img">`
          : `<span class="animal-thumb-initial">${esc((animalName(a) || "?").slice(0, 1).toUpperCase())}</span><i data-lucide="${species === "cat" ? "cat" : "paw-print"}" class="animal-thumb-icon"></i>`}
      </div>
      <div class="app-drawer-animal-meta">
        <span class="dialog-info-label">Animal</span>
        <span class="dialog-info-value">${esc(animal)}</span>
      </div>
    </div>`;
  const message = a.message && String(a.message).trim() ? String(a.message).trim() : "—";
  const rejection = a.rejection_reason && String(a.rejection_reason).trim() ? String(a.rejection_reason).trim() : "—";

  const opts = {
    title: "Adoption application details",
    body: `<div class="dialog-info">
      ${thumb}
      ${row("Application", shortId(a.id))}
      ${row("Applicant", name)}
      ${row("Status", titleCase(a.status))}
      ${row("Message", message)}
      ${a.status === "rejected" || rejection !== "—" ? row("Rejection reason", rejection) : ""}
      ${row("Submitted", when(a.created_at))}
      ${row("Reviewed", when(a.reviewed_at))}
      ${row("Completed", when(a.completed_at))}
    </div>`,
  };

  if (a.status === "pending") {
    opts.footer = `${Button({ text: "Reject", variant: "outline", attrs: 'data-drawer-act="decline"' })}
      ${Button({ text: "Approve", variant: "default", attrs: 'data-drawer-act="approve"' })}`;
    opts.onMount = (bodyEl) => {
      const drawer = bodyEl.closest(".drawer");
      const decline = drawer.querySelector('[data-drawer-act="decline"]');
      const approve = drawer.querySelector('[data-drawer-act="approve"]');
      if (decline) decline.addEventListener("click", () => runDecline(id));
      if (approve) approve.addEventListener("click", () => runApprove(id));
    };
  } else if (a.status === "approved") {
    opts.footer = `${Button({ text: "Complete", variant: "default", attrs: 'data-drawer-act="complete"' })}`;
    opts.onMount = (bodyEl) => {
      const drawer = bodyEl.closest(".drawer");
      const complete = drawer.querySelector('[data-drawer-act="complete"]');
      if (complete) complete.addEventListener("click", () => runComplete(id));
    };
  }

  openDrawer(opts);
  createIcons({ icons });
}
