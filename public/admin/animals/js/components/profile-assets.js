import { esc } from "./util.js";

export const MODEL_EXT = ["glb", "gltf", "obj"];
export const PHOTO_EXT = ["jpg", "jpeg", "png", "webp"];
export const MODEL_MAX_BYTES = 20 * 1024 * 1024;
export const PHOTO_MAX_BYTES = 5 * 1024 * 1024;
export const PHOTO_MIN = 4;
export const PHOTO_MAX = 36;

function extOf(name) {
  const i = String(name || "").lastIndexOf(".");
  return i >= 0 ? String(name).slice(i + 1).toLowerCase() : "";
}

export function validateModelFile(file) {
  if (!file) return "A 3D model file is required.";
  if (file.size > MODEL_MAX_BYTES) return `'${file.name}' exceeds the 20 MB size limit.`;
  if (!MODEL_EXT.includes(extOf(file.name))) return "Unsupported file type. Allowed: GLB, GLTF, OBJ.";
  return null;
}

export function validatePhotoFiles(files) {
  const list = Array.from(files || []);
  if (list.length < PHOTO_MIN || list.length > PHOTO_MAX) {
    return "Upload between 4 and 36 photos (JPG, PNG, or WEBP, 5 MB each).";
  }
  for (const file of list) {
    if (file.size > PHOTO_MAX_BYTES) return `'${file.name}' exceeds the 5 MB size limit.`;
    if (!PHOTO_EXT.includes(extOf(file.name))) return "Unsupported file type. Allowed: JPG, PNG, WEBP.";
  }
  return null;
}

export function fileBase(url) {
  const raw = String(url || "").trim();
  if (!raw) return "";
  try {
    return decodeURIComponent(raw.split("?")[0].split("/").pop() || raw);
  } catch {
    return raw;
  }
}

export function frameCount(photo360) {
  if (!photo360) return 0;
  try {
    const parsed = typeof photo360 === "string" ? JSON.parse(photo360) : photo360;
    return Array.isArray(parsed) ? parsed.filter((u) => typeof u === "string" && u.trim() !== "").length : 0;
  } catch {
    return 0;
  }
}

function namesLine(files) {
  const list = Array.from(files || []);
  if (!list.length) return "";
  const shown = list.slice(0, 6).map((f) => f.name);
  const extra = list.length > 6 ? ` +${list.length - 6} more` : "";
  if (list.length === 1) return `Selected: ${shown[0]}`;
  return `${list.length} files selected: ${shown.join(", ")}${extra}`;
}

export const REFERENCE_PRESETS = [
  { value: "aspin", label: "Aspin" },
  { value: "puspin", label: "Puspin" },
  { value: "hungarian", label: "Hungarian" },
  { value: "german_shepherd", label: "German Shepherd" },
];

export function defaultReferencePreset(species) {
  return String(species || "").toLowerCase() === "cat" ? "puspin" : "aspin";
}

export function syncReferencePreset(select, species) {
  if (!select) return;
  if (select.value === "aspin" || select.value === "puspin" || select.value === "") {
    select.value = defaultReferencePreset(species);
  }
}

export function profileAssetsFields({ prefix, modelUrl = "", photo360 = "", showRemove = false, species = "dog", preset = "" }) {
  const modelCurrent = fileBase(modelUrl);
  const frames = frameCount(photo360);
  const photoText = typeof photo360 === "string" ? photo360 : photo360 ? JSON.stringify(photo360) : "";
  const chosen = REFERENCE_PRESETS.some((opt) => opt.value === preset) ? preset : defaultReferencePreset(species);
  const presetOptions = REFERENCE_PRESETS.map(
    (opt) => `<option value="${esc(opt.value)}"${opt.value === chosen ? " selected" : ""}>${esc(opt.label)}</option>`
  ).join("");
  return `
    <div class="dialog-row">
    <div class="file-attach">
      <label class="dialog-label" for="${prefix}-model3d-file">
        <span class="file-attach__title"><i data-lucide="rotate-3d"></i>3D model</span>
        <span class="dialog-hint">optional — .glb / .gltf / .obj · max 20 MB</span>
      </label>
      <div class="file-attach__pick">
        <i data-lucide="upload" aria-hidden="true"></i>
        <input type="file" id="${prefix}-model3d-file" accept=".glb,.gltf,.obj" class="aa-photo-input" />
      </div>
      <p class="dialog-hint file-attach__names" id="${prefix}-model3d-name" hidden></p>
      ${
        showRemove && modelUrl
          ? `<p class="dialog-hint file-attach__current" id="${prefix}-model3d-current">Attached: ${esc(modelCurrent || modelUrl)}</p>
             <label class="file-attach__remove"><input type="checkbox" id="${prefix}-model3d-remove" /> Remove attached model</label>`
          : ""
      }
      <details>
        <summary class="dialog-hint">or paste URL</summary>
        <input class="dialog-input" id="${prefix}-model3d" value="${esc(modelUrl)}" placeholder="https://…" autocomplete="off" />
      </details>
    </div>
    <div class="file-attach">
      <label class="dialog-label" for="${prefix}-model-preset">
        Static model
        <select class="dialog-input" id="${prefix}-model-preset">${presetOptions}</select>
      </label>
    </div>
    </div>
    <div class="file-attach">
      <label class="dialog-label" for="${prefix}-photo360-file">
        <span class="file-attach__title"><i data-lucide="refresh-cw"></i>360° photos</span>
        <span class="dialog-hint">optional — 4 to 36 frames · JPG / PNG / WEBP · 5 MB each</span>
      </label>
      <div class="file-attach__pick">
        <i data-lucide="upload" aria-hidden="true"></i>
        <input type="file" id="${prefix}-photo360-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple class="aa-photo-input" />
      </div>
      <p class="dialog-hint file-attach__names" id="${prefix}-photo360-name" hidden></p>
      ${
        showRemove && frames
          ? `<p class="dialog-hint file-attach__current" id="${prefix}-photo360-current">${frames} frames attached</p>
             <label class="file-attach__remove"><input type="checkbox" id="${prefix}-photo360-remove" /> Remove 360° set</label>`
          : ""
      }
      <details>
        <summary class="dialog-hint">or paste JSON URLs</summary>
        <textarea class="dialog-input aa-textarea" id="${prefix}-photo360" rows="3" placeholder='["https://…/view-01.jpg"]'>${esc(photoText)}</textarea>
      </details>
    </div>`;
}

export function bindProfileAssets(overlay, prefix) {
  const modelInput = overlay.querySelector(`#${prefix}-model3d-file`);
  const modelNames = overlay.querySelector(`#${prefix}-model3d-name`);
  const photoInput = overlay.querySelector(`#${prefix}-photo360-file`);
  const photoNames = overlay.querySelector(`#${prefix}-photo360-name`);
  const modelRemove = overlay.querySelector(`#${prefix}-model3d-remove`);
  const photoRemove = overlay.querySelector(`#${prefix}-photo360-remove`);

  if (modelInput && modelNames) {
    modelInput.addEventListener("change", () => {
      const file = modelInput.files && modelInput.files[0];
      if (!file) {
        modelNames.hidden = true;
        modelNames.textContent = "";
        return;
      }
      const err = validateModelFile(file);
      modelNames.hidden = false;
      modelNames.textContent = err || `Selected: ${file.name}`;
      if (modelRemove) modelRemove.checked = false;
    });
  }
  if (photoInput && photoNames) {
    photoInput.addEventListener("change", () => {
      const files = photoInput.files;
      if (!files || !files.length) {
        photoNames.hidden = true;
        photoNames.textContent = "";
        return;
      }
      const err = validatePhotoFiles(files);
      photoNames.hidden = false;
      photoNames.textContent = err || namesLine(files);
      if (photoRemove) photoRemove.checked = false;
    });
  }
}

export function readProfileAssets(overlay, prefix) {
  const modelInput = overlay.querySelector(`#${prefix}-model3d-file`);
  const photoInput = overlay.querySelector(`#${prefix}-photo360-file`);
  const modelPaste = overlay.querySelector(`#${prefix}-model3d`);
  const photoPaste = overlay.querySelector(`#${prefix}-photo360`);
  const modelRemove = overlay.querySelector(`#${prefix}-model3d-remove`);
  const photoRemove = overlay.querySelector(`#${prefix}-photo360-remove`);
  const presetEl = overlay.querySelector(`#${prefix}-model-preset`);
  return {
    modelFile: modelInput && modelInput.files && modelInput.files[0] ? modelInput.files[0] : null,
    photo360Files: photoInput && photoInput.files ? Array.from(photoInput.files) : [],
    modelUrl: modelPaste ? modelPaste.value.trim() : "",
    photo360Text: photoPaste ? photoPaste.value : "",
    modelPreset: presetEl ? presetEl.value : "",
    removeModel: !!(modelRemove && modelRemove.checked),
    remove360: !!(photoRemove && photoRemove.checked),
  };
}

export function clientAssetError(assets) {
  if (assets.modelFile) {
    const err = validateModelFile(assets.modelFile);
    if (err) return err;
  }
  if (assets.photo360Files.length) {
    return validatePhotoFiles(assets.photo360Files);
  }
  return null;
}
