import { apiFetch } from "/assets/js/lib/api.js";

function showError(form, id, message) {
  const el = form.querySelector(id);
  if (!el) return;
  const slot = el.querySelector("span");
  if (slot) slot.textContent = message;
  el.hidden = false;
}

export function bindCaseHealth(root, { caseId, onSaved } = {}) {
  if (!root || !caseId) return;
  const vax = root.querySelector("#case-vax-form");
  const heart = root.querySelector("#case-hr-form");

  vax?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const name = vax.querySelector("#case-vax-name")?.value.trim() || "";
    const date = vax.querySelector("#case-vax-date")?.value || "";
    if (!name || !date) {
      showError(vax, "#case-vax-error", "Enter the vaccine name and the date it was given.");
      return;
    }
    try {
      await apiFetch(`/cases/${encodeURIComponent(caseId)}/vaccinations`, {
        method: "POST",
        body: { name, date_given: date },
      });
      if (typeof onSaved === "function") await onSaved();
    } catch (err) {
      showError(vax, "#case-vax-error", err.message || "Could not save the vaccination.");
    }
  });

  heart?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const bpm = Number(heart.querySelector("#case-hr")?.value);
    if (!Number.isFinite(bpm) || bpm < 1) {
      showError(heart, "#case-hr-error", "Enter a heart rate of at least 1 bpm.");
      return;
    }
    try {
      await apiFetch(`/cases/${encodeURIComponent(caseId)}/heart-rate`, {
        method: "POST",
        body: { heart_rate_bpm: bpm },
      });
      if (typeof onSaved === "function") await onSaved();
    } catch (err) {
      showError(heart, "#case-hr-error", err.message || "Could not save the heart rate.");
    }
  });
}
