import { createIcons, icons } from "lucide";
import { Button } from "/shared/components/button/button.js";
import { DatePicker, initDatePicker } from "/shared/components/date-picker/date-picker.js";
import { Spinner } from "/shared/components/spinner/spinner.js";
import { createAnimal, fetchCase, fetchReport, updateAnimal } from "/assets/js/admin/admin-data.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import { addAnimal, parsePhoto360, setSelectedId } from "../state.js";
import { bindProfileAssets, clientAssetError, profileAssetsFields, readProfileAssets, syncReferencePreset } from "./profile-assets.js";
import { esc, resizeImage, ageFromBirthDate, shortId } from "./util.js";

const HEALTH_READY_HINT =
  "Animal must have a vaccination record and vitals before it can be listed for adoption.";

function tabsHtml(attr, options, active) {
  return options
    .map((o) => {
      const disabled = o.disabled ? " disabled aria-disabled=\"true\"" : "";
      const title = o.title ? ` title="${esc(o.title)}"` : "";
      const cls = `q-btn${o.value === active ? " is-active" : ""}${o.disabled ? " is-disabled" : ""}`;
      return `<button type="button" class="${cls}" data-${attr}="${esc(o.value)}"${disabled}${title}>${esc(o.label)}</button>`;
    })
    .join("");
}

function handoffCaseId(prefill = {}) {
  return prefill.caseId || new URLSearchParams(window.location.search).get("from_case") || "";
}

async function loadHandoff(caseId) {
  if (!caseId) return { caseId: "", description: "", source: "rescued_case" };
  try {
    const caseData = await fetchCase(caseId);
    let description = "";
    if (caseData && caseData.report_id) {
      const report = await fetchReport(caseData.report_id);
      description = (report && report.animal_description) || "";
    }
    return { caseId, description, source: "rescued_case" };
  } catch {
    return { caseId, description: "", source: "rescued_case" };
  }
}

export function openAddAnimalDialog(prefill = {}) {
  const caseId = handoffCaseId(prefill);
  return new Promise((resolve) => {
    const overlay = document.createElement("div");
    overlay.className = "dialog-overlay";

    const speciesTabs = [
      { value: "dog", label: "Dog" },
      { value: "cat", label: "Cat" },
    ];
    const sexTabs = [
      { value: "male", label: "Male" },
      { value: "female", label: "Female" },
    ];
    const breedTabs = [
      { value: "aspin", label: "Aspin" },
      { value: "puspin", label: "Puspin" },
    ];
    const statusTabs = [
      { value: "not_listed", label: "Not listed" },
      { value: "available", label: "Ready for adoption", disabled: true, title: HEALTH_READY_HINT },
      { value: "pending", label: "Pending" },
      { value: "adopted", label: "Adopted" },
    ];
    const ageUnitTabs = [
      { value: "yr", label: "yr" },
      { value: "mon", label: "mon" },
      { value: "day", label: "day" },
    ];

    const form = {
      species: "dog",
      breed: "aspin",
      sex: "male",
      status: "not_listed",
      ageUnit: "yr",
      photo: null,
      pendingId: null,
      source: "rescued_case",
      description: prefill.description || "",
    };

    overlay.innerHTML = `
      <div class="dialog dialog--wide" role="dialog" aria-modal="true" aria-labelledby="add-animal-title">
        <div class="dialog-head">
          <div class="dialog-title-wrap">
            <i data-lucide="plus-circle" class="dialog-icon"></i>
            <h3 class="dialog-title" id="add-animal-title">${caseId ? "Register animal" : "Add animal"}</h3>
          </div>
          <button type="button" class="dialog-x" aria-label="Close"><i data-lucide="x"></i></button>
        </div>
        <div class="dialog-body rescuer-modal-body">
          <div class="add-animal-form">
            ${caseId ? `<p class="stamp stamp--sm stamp--muted">Source · Rescued case · ${esc(shortId(caseId))}</p>
            <label class="dialog-label" for="aa-description">Report description</label>
            <textarea class="dialog-input aa-textarea" id="aa-description" rows="3">${esc(form.description)}</textarea>` : ""}
            <label class="dialog-label" for="aa-name">Name <span class="dialog-req">*</span></label>
            <input class="dialog-input" id="aa-name" placeholder="e.g. Bantay" autocomplete="off" />

            <label class="dialog-label">Species</label>
            <div class="q-tabs" id="aa-species-tabs">${tabsHtml("species", speciesTabs, form.species)}</div>

            <label class="dialog-label">Breed <span class="dialog-hint">auto-set by species</span></label>
            <div class="q-tabs" id="aa-breed-tabs">${tabsHtml("breed", breedTabs, form.breed)}</div>

            <div class="add-animal-row">
              <div class="add-animal-col">
                <label class="dialog-label" for="aa-age">Age</label>
                <input class="dialog-input" id="aa-age" inputmode="numeric" placeholder="e.g. 2" autocomplete="off" />
                <p class="dialog-error" id="aa-age-error" hidden>Enter numbers only.</p>
              </div>
              <div class="add-animal-col">
                <label class="dialog-label">Unit</label>
                <div class="q-tabs" id="aa-age-tabs">${tabsHtml("unit", ageUnitTabs, form.ageUnit)}</div>
              </div>
            </div>

            <label class="dialog-label" for="aa-birth">Date of birth <span class="dialog-hint">auto-computes age</span></label>
            ${DatePicker({ id: "aa-birth", name: "birthDate", placeholder: "Pick a date", max: new Date().toISOString().slice(0, 10) })}

            <label class="dialog-label">Sex</label>
            <div class="q-tabs" id="aa-sex-tabs">${tabsHtml("sex", sexTabs, form.sex)}</div>

            <label class="dialog-label">Status</label>
            <div class="q-tabs" id="aa-status-tabs">${tabsHtml("status", statusTabs, form.status)}</div>
            <p class="health-ready-hint">Ready for adoption stays disabled until the animal has a vaccination and a vital.</p>

            <label class="dialog-label" for="aa-color">Color / markings</label>
            <input class="dialog-input" id="aa-color" placeholder="e.g. Brown with white patch" autocomplete="off" />

            <label class="dialog-label">Photo</label>
            <div class="aa-photo">
              <div class="aa-photo-preview" id="aa-photo-preview"><i data-lucide="image-plus"></i></div>
              <input type="file" id="aa-photo" accept="image/*" class="aa-photo-input" />
            </div>

            ${profileAssetsFields({ prefix: "aa", species: form.species })}

            <p class="dialog-error" id="aa-error" hidden>Please provide a name.</p>
          </div>
        </div>
        <div class="dialog-foot">
          ${Button({ text: "Cancel", variant: "outline", attrs: 'data-act="cancel"' })}
          ${Button({ text: "Add animal", variant: "default", icon: "plus", attrs: 'data-act="ok"' })}
        </div>
      </div>`;

    document.body.appendChild(overlay);
    bindProfileAssets(overlay, "aa");
    createIcons({ icons });

    const nameEl = overlay.querySelector("#aa-name");
    const ageEl = overlay.querySelector("#aa-age");
    const ageErrorEl = overlay.querySelector("#aa-age-error");
    const birthEl = overlay.querySelector("#aa-birth-value");
    const colorEl = overlay.querySelector("#aa-color");
    const speciesTabsEl = overlay.querySelector("#aa-species-tabs");
    const breedTabsEl = overlay.querySelector("#aa-breed-tabs");
    const sexTabsEl = overlay.querySelector("#aa-sex-tabs");
    const statusTabsEl = overlay.querySelector("#aa-status-tabs");
    const ageTabsEl = overlay.querySelector("#aa-age-tabs");
    const photoInput = overlay.querySelector("#aa-photo");
    const photoPreview = overlay.querySelector("#aa-photo-preview");
    const errorEl = overlay.querySelector("#aa-error");
    nameEl && nameEl.focus();

    const activate = (container, attr, value) =>
      container.querySelectorAll(".q-btn").forEach((b) => b.classList.toggle("is-active", b.dataset[attr] === value));

    speciesTabsEl.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-species]");
      if (!btn) return;
      form.species = btn.dataset.species;
      form.breed = form.species === "cat" ? "puspin" : "aspin";
      activate(speciesTabsEl, "species", form.species);
      activate(breedTabsEl, "breed", form.breed);
      syncReferencePreset(overlay.querySelector("#aa-model-preset"), form.species);
    });
    breedTabsEl.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-breed]");
      if (!btn) return;
      form.breed = btn.dataset.breed;
      activate(breedTabsEl, "breed", form.breed);
    });
    sexTabsEl.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-sex]");
      if (!btn) return;
      form.sex = btn.dataset.sex;
      activate(sexTabsEl, "sex", form.sex);
    });
    statusTabsEl.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-status]");
      if (!btn || btn.disabled) return;
      form.status = btn.dataset.status;
      activate(statusTabsEl, "status", form.status);
    });
    const descEl = overlay.querySelector("#aa-description");
    if (caseId) {
      loadHandoff(caseId).then((handoff) => {
        form.source = handoff.source;
        form.description = handoff.description;
        if (descEl && !descEl.value.trim() && handoff.description) descEl.value = handoff.description;
      });
    }
    ageTabsEl.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-unit]");
      if (!btn) return;
      form.ageUnit = btn.dataset.unit;
      activate(ageTabsEl, "unit", form.ageUnit);
    });
    if (ageEl) {
      ageEl.addEventListener("input", () => {
        const digits = ageEl.value.replace(/[^0-9]/g, "");
        if (digits !== ageEl.value) {
          ageErrorEl.textContent = "Enter numbers only.";
          ageErrorEl.hidden = false;
        } else {
          ageErrorEl.hidden = true;
        }
        ageEl.value = digits;
      });
    }
    if (photoInput) {
      photoInput.addEventListener("change", () => {
        const file = photoInput.files && photoInput.files[0];
        if (!file) return;
        resizeImage(file, 640, 0.8)
          .then((dataUrl) => {
            form.photo = dataUrl;
            photoPreview.style.backgroundImage = `url("${dataUrl}")`;
            photoPreview.innerHTML = "";
          })
          .catch(() => {});
      });
    }
    initDatePicker(overlay, {
      "aa-birth": (val) => {
        if (!ageEl) return;
        const computed = ageFromBirthDate(val);
        if (!computed) return;
        form.ageUnit = computed.unit;
        ageEl.value = String(computed.n);
        ageErrorEl.hidden = true;
        activate(ageTabsEl, "unit", form.ageUnit);
      },
    });

    const close = () => {
      overlay.remove();
      resolve(null);
    };

    let busy = false;
    const submit = async () => {
      if (busy) return;
      const name = nameEl.value.trim();
      if (!name) {
        errorEl.hidden = false;
        nameEl.focus();
        return;
      }
      errorEl.hidden = true;
      const age = ageEl && ageEl.value.trim() ? `${ageEl.value.trim()} ${form.ageUnit}` : null;
      const birthDate = birthEl && birthEl.value.trim() ? birthEl.value.trim() : null;
      const assets = readProfileAssets(overlay, "aa");
      const assetErr = clientAssetError(assets);
      if (assetErr) {
        errorEl.textContent = assetErr;
        errorEl.hidden = false;
        return;
      }
      let photo360Urls = null;
      if (!assets.photo360Files.length && assets.photo360Text.trim()) {
        try {
          photo360Urls = parsePhoto360(assets.photo360Text);
        } catch (err) {
          errorEl.textContent = err.message;
          errorEl.hidden = false;
          return;
        }
      }
      const isRegister = Boolean(caseId);
      busy = true;
      const confirmed = await confirmDialog({
        title: isRegister ? "Register this animal?" : "Create this animal?",
        message: isRegister
          ? `Register "${name}" and link it to this rescued case?`
          : `Create "${name}" and add it to the animal roster?`,
        confirmText: isRegister ? "Register" : "Create",
        cancelText: "Cancel",
      });
      if (!confirmed) {
        busy = false;
        return;
      }
      const okBtn = overlay.querySelector('[data-act="ok"]');
      okBtn.disabled = true;
      okBtn.innerHTML = `${Spinner({ size: 16 })}<span>Adding…</span>`;
      try {
        if (form.status === "available") form.status = "not_listed";
        const description = descEl ? descEl.value.trim() : form.description || null;
        if (caseId && !form.pendingId) {
          const created = await createAnimal({
            name,
            species: form.species,
            breed_type: form.breed,
            sex: form.sex,
            age_estimate: age,
            birth_date: birthDate,
            color_markings: colorEl ? colorEl.value.trim() : null,
            adoption_status: "not_listed",
            source: "rescued_case",
            description,
            photo_urls: form.photo ? [form.photo] : null,
            case_id: caseId,
            model_preset: assets.modelPreset || null,
          });
          if (created && created.id) form.pendingId = created.id;
        }
        const animal = await addAnimal({
          id: form.pendingId || null,
          name,
          species: form.species,
          breed: form.breed,
          age,
          birthDate,
          sex: form.sex,
          status: form.status === "available" ? "not_listed" : form.status,
          color: colorEl ? colorEl.value.trim() : null,
          photo: form.photo,
          model3d: assets.modelFile ? "" : assets.modelUrl,
          photo360Urls,
          modelFile: assets.modelFile,
          photo360Files: assets.photo360Files,
          modelPreset: assets.modelPreset,
          description,
        });
        if (caseId && animal && animal.id) {
          try {
            await updateAnimal(animal.id, { case_id: caseId });
          } catch {
            /* create() may already have linked case_id */
          }
          setSelectedId(animal.id);
        }
        overlay.remove();
        resolve(animal);
        if (animal && animal.id) {
          window.location.href = `/admin/health-records/health-record.php?id=${encodeURIComponent(animal.id)}`;
        }
      } catch (err) {
        if (err && err.animalId) form.pendingId = err.animalId;
        busy = false;
        okBtn.disabled = false;
        okBtn.innerHTML = `<i data-lucide="plus"></i><span>Add animal</span>`;
        createIcons({ icons });
        errorEl.textContent = err && err.message ? err.message : "Failed to add animal.";
        errorEl.hidden = false;
      }
    };

    overlay.querySelector('[data-act="cancel"]').addEventListener("click", close);
    overlay.querySelector('[data-act="ok"]').addEventListener("click", submit);
    overlay.querySelector(".dialog-x").addEventListener("click", close);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) close();
    });
    overlay.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && e.target.tagName !== "TEXTAREA" && e.target.type !== "file" && e.target.tagName !== "SUMMARY") {
        e.preventDefault();
        submit();
      }
    });
  });
}

let handoffOpened = false;

function bootCaseHandoff() {
  if (handoffOpened) return;
  if (!window.location.pathname.includes("/admin/animals")) return;
  const caseId = new URLSearchParams(window.location.search).get("from_case");
  if (!caseId) return;
  handoffOpened = true;
  openAddAnimalDialog({ caseId });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => setTimeout(bootCaseHandoff, 0));
} else {
  setTimeout(bootCaseHandoff, 0);
}
