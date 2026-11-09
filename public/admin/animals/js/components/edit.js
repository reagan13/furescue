import { createIcons, icons } from "lucide";
import { Button } from "/shared/components/button/button.js";
import { Spinner } from "/shared/components/spinner/spinner.js";
import { fetchAnimalHealthRecord, updateAnimal } from "/assets/js/admin/admin-data.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import { parsePhoto360, saveAnimalAssets } from "../state.js";
import { bindProfileAssets, clientAssetError, profileAssetsFields, readProfileAssets } from "./profile-assets.js";
import { resizeImage } from "./util.js";

const HEALTH_READY_HINT =
  "Animal must have a vaccination record and vitals before it can be listed for adoption.";

function isHealthReady(record) {
  if (!record) return false;
  const hasVaccination = Array.isArray(record.vaccinations) && record.vaccinations.length > 0;
  const hasVital = Array.isArray(record.vitals) && record.vitals.length > 0;
  return hasVaccination && hasVital;
}

function esc(value) {
  return String(value ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  }[c]));
}

const STATUS_TO_API = {
  Available: "available",
  Pending: "pending",
  Adopted: "adopted",
  "Not listed": "not_listed",
};
const API_TO_STATUS = {
  available: "Available",
  pending: "Pending",
  adopted: "Adopted",
  not_listed: "Not listed",
};

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

export async function openEditAnimalDialog(animal) {
  let healthReady = false;
  try {
    healthReady = isHealthReady(await fetchAnimalHealthRecord(animal.id));
  } catch {
    healthReady = false;
  }
  return new Promise((resolve) => {
    const overlay = document.createElement("div");
    overlay.className = "dialog-overlay";

    const statusTabs = [
      { value: "not_listed", label: "Not listed" },
      { value: "available", label: "Ready for adoption", disabled: !healthReady, title: healthReady ? "" : HEALTH_READY_HINT },
      { value: "pending", label: "Pending" },
      { value: "adopted", label: "Adopted" },
    ];

    const m = (animal.age || "").match(/^(\d+)\s*(\w+)$/);
    const ageNum = m ? m[1] : "";
    const ageUnitRaw = m ? m[2] : "yr";
    const ageUnit = ageUnitRaw.startsWith("day") ? "day" : ageUnitRaw;

    const form = {
      status: STATUS_TO_API[animal.status] || "not_listed",
      ageUnit,
      photo: animal.photo || null,
    };

    overlay.innerHTML = `
      <div class="dialog dialog--wide" role="dialog" aria-modal="true" aria-labelledby="edit-animal-title">
        <div class="dialog-head">
          <div class="dialog-title-wrap">
            <i data-lucide="pencil" class="dialog-icon"></i>
            <h3 class="dialog-title" id="edit-animal-title">Edit &middot; ${esc(animal.name)}</h3>
          </div>
          <button type="button" class="dialog-x" aria-label="Close"><i data-lucide="x"></i></button>
        </div>
        <div class="dialog-body rescuer-modal-body">
          <div class="edit-animal-readonly">
            <span><i data-lucide="paw-print"></i>${esc(animal.species)}</span>
            <span><i data-lucide="tag"></i>${esc(animal.breed)}</span>
            <span><i data-lucide="venus-mars"></i>${esc(animal.sex)}</span>
          </div>
          <div class="add-animal-form">
            <label class="dialog-label" for="ea-name">Name</label>
            <input class="dialog-input" id="ea-name" value="${esc(animal.name)}" autocomplete="off" />

            <div class="add-animal-row">
              <div class="add-animal-col">
                <label class="dialog-label" for="ea-age">Age</label>
                <input class="dialog-input" id="ea-age" inputmode="numeric" value="${esc(ageNum)}" autocomplete="off" />
                <p class="dialog-error" id="ea-age-error" hidden>Enter numbers only.</p>
              </div>
              <div class="add-animal-col">
                <label class="dialog-label">Unit</label>
                <div class="q-tabs" id="ea-age-tabs">${tabsHtml("unit", [
                  { value: "yr", label: "yr" },
                  { value: "mon", label: "mon" },
                  { value: "day", label: "day" },
                ], form.ageUnit)}</div>
              </div>
            </div>

            <label class="dialog-label">Status</label>
            <div class="q-tabs" id="ea-status-tabs">${tabsHtml("status", statusTabs, form.status)}</div>
            ${healthReady ? "" : `<p class="health-ready-hint">${esc(HEALTH_READY_HINT)}</p>`}

            <label class="dialog-label" for="ea-color">Color / markings</label>
            <input class="dialog-input" id="ea-color" value="${esc(animal.barangay)}" autocomplete="off" />

            <label class="dialog-label">Photo</label>
            <div class="aa-photo">
              <div class="aa-photo-preview" id="ea-photo-preview">${
                animal.photo
                  ? `<img src="${esc(animal.photo)}" alt="${esc(animal.name)}">`
                  : `<i data-lucide="image-plus"></i>`
              }</div>
              <input type="file" id="ea-photo" accept="image/*" class="aa-photo-input" />
            </div>

            ${profileAssetsFields({
              prefix: "ea",
              modelUrl: animal.model3d || "",
              photo360: animal.photo360 || "",
              showRemove: true,
              species: animal.species,
              preset: animal.modelPreset || "",
            })}

            <p class="dialog-error" id="ea-error" hidden></p>
          </div>
        </div>
        <div class="dialog-foot">
          ${Button({ text: "Cancel", variant: "outline", attrs: 'data-act="cancel"' })}
          ${Button({ text: "Save changes", variant: "default", icon: "check", attrs: 'data-act="ok"' })}
        </div>
      </div>`;

    document.body.appendChild(overlay);
    bindProfileAssets(overlay, "ea");
    createIcons({ icons });

    const nameEl = overlay.querySelector("#ea-name");
    const ageEl = overlay.querySelector("#ea-age");
    const ageErrorEl = overlay.querySelector("#ea-age-error");
    const colorEl = overlay.querySelector("#ea-color");
    const statusTabsEl = overlay.querySelector("#ea-status-tabs");
    const ageTabsEl = overlay.querySelector("#ea-age-tabs");
    const photoInput = overlay.querySelector("#ea-photo");
    const photoPreview = overlay.querySelector("#ea-photo-preview");
    const errorEl = overlay.querySelector("#ea-error");
    nameEl && nameEl.focus();

    const activate = (container, attr, value) =>
      container.querySelectorAll(".q-btn").forEach((b) => b.classList.toggle("is-active", b.dataset[attr] === value));

    statusTabsEl.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-status]");
      if (!btn || btn.disabled) return;
      form.status = btn.dataset.status;
      activate(statusTabsEl, "status", form.status);
    });
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

    const close = () => {
      overlay.remove();
      resolve(null);
    };

    let busy = false;
    const submit = async () => {
      if (busy) return;
      const name = nameEl.value.trim();
      const age = ageEl && ageEl.value.trim() ? `${ageEl.value.trim()} ${form.ageUnit}` : null;
      const assets = readProfileAssets(overlay, "ea");
      const assetErr = clientAssetError(assets);
      if (assetErr) {
        errorEl.textContent = assetErr;
        errorEl.hidden = false;
        return;
      }
      if (form.status === "available" && !healthReady) {
        errorEl.textContent = HEALTH_READY_HINT;
        errorEl.hidden = false;
        return;
      }
      const body = {
        name,
        age_estimate: age,
        color_markings: colorEl ? colorEl.value.trim() : null,
        adoption_status: form.status,
        model_preset: assets.modelPreset || null,
      };
      if (!assets.modelFile && !assets.removeModel) {
        const pasted = assets.modelUrl;
        if (pasted !== (animal.model3d || "")) {
          if (!pasted) assets.removeModel = true;
          else body.model_3d_url = pasted;
        }
      }
      if (!assets.photo360Files.length && !assets.remove360) {
        const text = (assets.photo360Text || "").trim();
        const prev = (animal.photo360 || "").trim();
        if (text !== prev) {
          if (!text) {
            assets.remove360 = true;
          } else {
            try {
              body.photo_360_set = parsePhoto360(text);
            } catch (err) {
              errorEl.textContent = err.message;
              errorEl.hidden = false;
              return;
            }
          }
        }
      }
      if (form.photo && form.photo !== animal.photo) body.photo_urls = [form.photo];
      const removals = [];
      if (assets.removeModel && !assets.modelFile) removals.push("the attached 3D model");
      if (assets.remove360 && !(assets.photo360Files && assets.photo360Files.length)) {
        removals.push("the 360° photo set");
      }
      busy = true;
      const confirmed = await confirmDialog({
        title: "Save changes to this animal?",
        message: removals.length
          ? `Save changes to "${name || animal.name}"? This will also delete ${removals.join(" and ")}.`
          : `Save changes to "${name || animal.name}"?`,
        confirmText: "Save",
        cancelText: "Cancel",
        danger: removals.length > 0,
      });
      if (!confirmed) {
        busy = false;
        return;
      }
      const okBtn = overlay.querySelector('[data-act="ok"]');
      okBtn.disabled = true;
      okBtn.innerHTML = `${Spinner({ size: 16 })}<span>Saving…</span>`;
      try {
        let updated = animal;
        const uploaded = await saveAnimalAssets(animal.id, assets);
        if (uploaded) updated = uploaded;
        updated = (await updateAnimal(animal.id, body)) || updated;
        overlay.remove();
        resolve(updated || animal);
      } catch (err) {
        busy = false;
        okBtn.disabled = false;
        okBtn.innerHTML = `<i data-lucide="check"></i><span>Save changes</span>`;
        createIcons({ icons });
        errorEl.textContent = err && err.message ? err.message : "Failed to save changes.";
        errorEl.hidden = false;
      }
    };

    overlay.querySelector('[data-act="cancel"]').addEventListener("click", close);
    overlay.querySelector('[data-act="ok"]').addEventListener("click", submit);
    overlay.querySelector(".dialog-x").addEventListener("click", close);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) close();
    });
  });
}
