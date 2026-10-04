import { createIcons, icons } from "lucide";
import { apiFetch, apiUpload } from "/assets/js/lib/api.js";
import { toast } from "/shared/components/toast/toast.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import { esc } from "/assets/js/lib/format.js";

const MAX_PHOTOS = 8;

export function openListingForm({ onCreated } = {}) {
  const overlay = document.createElement("div");
  overlay.className = "rmodal-overlay";
  overlay.innerHTML = `
    <div class="rmodal" role="dialog" aria-modal="true" aria-labelledby="listing-title">
      <div class="rmodal-head">
        <i data-lucide="megaphone" class="text-primary"></i>
        <h2 class="rmodal-title" id="listing-title">Post for adoption</h2>
        <button type="button" class="rmodal-x" aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <form class="rmodal-body rform" id="listing-form">
        <p class="m-0 text-sm text-muted-foreground">Add the animal’s profile and at least one photo. City Vet reviews the listing before it goes live.</p>
        <div class="rform-field">
          <label for="listing-name" class="rform-label">Name <span class="font-normal text-muted-foreground">(required)</span></label>
          <input id="listing-name" name="name" type="text" maxlength="100" required class="input" placeholder="Ligaya">
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="rform-field">
            <label for="listing-species" class="rform-label">Species</label>
            <select id="listing-species" name="species" class="input" required>
              <option value="dog">Dog (Aspin)</option>
              <option value="cat">Cat (Puspin)</option>
            </select>
          </div>
          <div class="rform-field">
            <label for="listing-sex" class="rform-label">Sex</label>
            <select id="listing-sex" name="sex" class="input" required>
              <option value="female">Female</option>
              <option value="male">Male</option>
            </select>
          </div>
        </div>
        <div class="rform-field">
          <label for="listing-age" class="rform-label">Age estimate</label>
          <input id="listing-age" name="age_estimate" type="text" maxlength="50" class="input" placeholder="About 2 years">
        </div>
        <div class="rform-field">
          <label for="listing-color" class="rform-label">Color / markings</label>
          <input id="listing-color" name="color_markings" type="text" maxlength="500" class="input" placeholder="Brown with white chest">
        </div>
        <div class="rform-field">
          <label for="listing-desc" class="rform-label">Profile <span class="font-normal text-muted-foreground">(required)</span></label>
          <textarea id="listing-desc" name="description" rows="6" maxlength="2000" required class="input input--area input--area-lg" placeholder="Temperament, how it was rescued, and what home it needs."></textarea>
        </div>
        <div class="rform-field">
          <p class="rform-label">Photo <span class="font-normal text-muted-foreground">(required)</span></p>
          <label for="listing-photos" class="mt-2 flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-input bg-background px-4 py-6 text-center transition-colors hover:bg-accent/50">
            <i data-lucide="image-plus" class="h-6 w-6 text-muted-foreground"></i>
            <span class="text-sm font-semibold">Choose photos</span>
            <span class="text-xs text-muted-foreground">JPG, PNG, GIF or WEBP — max 10 MB each</span>
            <input id="listing-photos" name="photos[]" type="file" accept="image/*" multiple required class="sr-only">
          </label>
          <ul id="listing-photo-list" class="mt-2 flex flex-wrap gap-2"></ul>
        </div>
        <p class="rform-error" id="listing-error" hidden><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i><span>Something went wrong.</span></p>
      </form>
      <div class="rmodal-foot">
        <button type="button" class="rbtn rbtn--ghost" data-act="cancel">Cancel</button>
        <button type="button" class="rbtn rbtn--solid" data-act="submit"><i data-lucide="send"></i><span>Submit for review</span></button>
      </div>
    </div>`;

  const host = document.querySelector(".resident-shell") || document.body;
  host.appendChild(overlay);
  const body = overlay.querySelector(".rmodal-body");
  if (body) {
    body.style.flex = "1 1 auto";
    body.style.minHeight = "0";
    body.style.overflow = "auto";
  }
  createIcons({ icons });

  const files = [];
  const input = overlay.querySelector("#listing-photos");
  const list = overlay.querySelector("#listing-photo-list");
  const errorEl = overlay.querySelector("#listing-error");

  const close = () => {
    document.removeEventListener("keydown", onEsc);
    overlay.remove();
  };
  const onEsc = (e) => {
    if (e.key === "Escape") close();
  };
  document.addEventListener("keydown", onEsc);
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) close();
  });
  overlay.querySelector('[data-act="cancel"]').addEventListener("click", close);
  overlay.querySelector(".rmodal-x").addEventListener("click", close);

  const renderFiles = () => {
    list.innerHTML = files
      .map(
        (file, i) =>
          `<li class="rchip">${esc(file.name)} <button type="button" class="rbtn rbtn--ghost rbtn--sm" data-remove="${i}" aria-label="Remove photo">×</button></li>`
      )
      .join("");
    input.required = files.length === 0;
  };

  input.addEventListener("change", () => {
    for (const file of Array.from(input.files || [])) {
      if (files.length >= MAX_PHOTOS) break;
      files.push(file);
    }
    input.value = "";
    renderFiles();
  });
  list.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-remove]");
    if (!btn) return;
    files.splice(Number(btn.dataset.remove), 1);
    renderFiles();
  });

  overlay.querySelector('[data-act="submit"]').addEventListener("click", async (e) => {
    const form = overlay.querySelector("#listing-form");
    const name = form.name.value.trim();
    const description = form.description.value.trim();
    if (!name || !description || files.length === 0) {
      errorEl.querySelector("span").textContent = "Name, profile, and at least one photo are required.";
      errorEl.hidden = false;
      return;
    }
    const ok = await confirmDialog({
      title: "Submit this listing?",
      message: `This will send ${name} to City Vet for review.`,
      confirmText: "Submit for review",
    });
    if (!ok) return;
    const btn = e.currentTarget;
    btn.disabled = true;
    errorEl.hidden = true;
    const body = new FormData();
    body.append("name", name);
    body.append("species", form.species.value);
    body.append("sex", form.sex.value);
    body.append("breed_type", form.species.value === "cat" ? "puspin" : "aspin");
    body.append("description", description);
    const age = form.age_estimate.value.trim();
    const color = form.color_markings.value.trim();
    if (age) body.append("age_estimate", age);
    if (color) body.append("color_markings", color);
    for (const file of files) body.append("photos[]", file);
    try {
      await apiUpload("/adoption-listings", body);
      close();
      toast("Listing submitted. You'll be notified once it's reviewed.", { type: "success", duration: 5000 });
      if (typeof onCreated === "function") await onCreated();
    } catch (err) {
      btn.disabled = false;
      errorEl.querySelector("span").textContent = err.message || "Could not submit the listing.";
      errorEl.hidden = false;
    }
  });
}
