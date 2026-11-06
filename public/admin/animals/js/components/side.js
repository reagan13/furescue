import { createIcons, icons } from "lucide";
import { state, getAnimal, speciesIcon, statusTone } from "../state.js";
import { Button } from "/shared/components/button/button.js";
import { syncDetailLayout } from "./grid.js";
import { esc } from "./util.js";

export function DetailPanel() {
  const a = state.selectedId ? getAnimal(state.selectedId) : null;
  if (!a) {
    return `
    <div class="panel panel--padded animal-detail animal-detail--empty">
      <div class="rescuer-detail-empty">
        <i data-lucide="mouse-pointer-click"></i>
        <p>Select an animal card to view its full profile.</p>
      </div>
    </div>`;
  }
  const rows = [
    { label: "ID", value: a.id },
    { label: "Species", value: a.species },
    { label: "Breed", value: a.breed },
    { label: "Age", value: a.age },
    { label: "Sex", value: a.sex },
    { label: "Status", value: a.status },
    { label: "Description", value: a.barangay },
    { label: "Intake", value: a.intake },
  ]
    .map(
      (r) => `
    <div class="dialog-info-row">
      <span class="dialog-info-label">${esc(r.label)}</span>
      <span class="dialog-info-value">${esc(r.value)}</span>
    </div>`
    )
    .join("");
  const photo = a.photo
    ? `<div class="animal-detail-photo"><img src="${esc(a.photo)}" alt="${esc(a.name)}"></div>`
    : `<div class="animal-detail-photo animal-detail-photo--empty" aria-hidden="true"><i data-lucide="${esc(speciesIcon(a.species))}"></i></div>`;
  return `
  <div class="panel panel--padded animal-detail">
    ${photo}
    <div class="panel-title-wrap">
      <i data-lucide="${esc(({ Dog: "paw-print", Cat: "cat", Bird: "bird", Rabbit: "rabbit", Other: "paw-print" })[a.species] || "paw-print")}"></i>
      <h2 class="panel-title panel-title--sm">${esc(a.name)}</h2>
      <span class="stamp stamp--sm ${statusTone(a.status)}" style="margin-left:auto">${esc(a.status)}</span>
    </div>
    <div class="dialog-info">${rows}</div>
    <div class="animal-detail-actions">
      <div class="animal-detail-actions-row">
        ${Button({
          text: a.hasMedical ? "View medical records" : "Add medical records",
          variant: "default",
          attrs: 'data-act="add-health"',
        })}
      </div>
      <div class="animal-detail-actions-row">
        ${Button({ text: "Edit", variant: "outline", attrs: 'data-act="edit-animal"' })}
        ${Button({ text: "Delete", variant: "destructive", attrs: 'data-act="delete-animal"' })}
      </div>
    </div>
  </div>`;
}

export function SidePanel() {
  return `
  <div class="animal-side">
    <div id="animal-detail">${DetailPanel()}</div>
  </div>`;
}

function revealDetailIfStacked() {
  const wrap = document.getElementById("animal-detail");
  if (!wrap || !state.selectedId) return;
  if (!window.matchMedia("(max-width: 1023px)").matches) return;
  requestAnimationFrame(() => {
    const el = document.getElementById("animal-detail");
    if (!el) return;
    const topbarH = document.querySelector(".topbar")?.getBoundingClientRect().height || 0;
    const y = el.getBoundingClientRect().top + window.scrollY - topbarH - 8;
    window.scrollTo({ top: Math.max(0, y), left: 0, behavior: "auto" });
  });
}

export function renderSideStats() {
  const wrap = document.getElementById("animal-side");
  if (wrap) {
    wrap.innerHTML = SidePanel();
    createIcons({ icons });
    syncDetailLayout();
    revealDetailIfStacked();
  }
}

export function renderDetail() {
  const wrap = document.getElementById("animal-detail");
  if (wrap) {
    wrap.innerHTML = DetailPanel();
    createIcons({ icons });
    syncDetailLayout();
    revealDetailIfStacked();
  }
}
