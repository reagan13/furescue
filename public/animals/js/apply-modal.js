import { createIcons, icons } from "lucide";
import { apiFetch } from "/assets/js/lib/api.js";
import { esc } from "/assets/js/lib/format.js";

// Shared "Apply to adopt" dialog. `animal` needs at least { id, name }.
export function openApplyModal(animal, { onApplied } = {}) {
  const overlay = document.createElement("div");
  overlay.className = "rmodal-overlay";
  overlay.innerHTML = `
    <div class="rmodal" role="dialog" aria-modal="true" aria-labelledby="apply-title">
      <div class="rmodal-head">
        <i data-lucide="heart" class="text-primary"></i>
        <h2 class="rmodal-title" id="apply-title">Apply to adopt ${esc(animal.name || "this animal")}</h2>
        <button type="button" class="rmodal-x" aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <div class="rmodal-body">
        <p class="m-0 text-sm text-muted-foreground">Tell the team a bit about yourself and your home. Applications are reviewed by the City Veterinarian's Office.</p>
        <div class="rform-field">
          <label for="apply-message" class="rform-label">Your note <span class="font-normal">(optional)</span></label>
          <textarea id="apply-message" class="input h-auto py-2" rows="4" maxlength="1000" placeholder="e.g. We have a fenced yard and are home most of the day…"></textarea>
        </div>
        <p class="rform-error" id="apply-error" hidden><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i><span>Something went wrong.</span></p>
      </div>
      <div class="rmodal-foot">
        <button type="button" class="rbtn rbtn--ghost" data-act="cancel">Cancel</button>
        <button type="button" class="rbtn rbtn--solid" data-act="submit"><i data-lucide="send"></i><span>Submit application</span></button>
      </div>
    </div>`;
  const host = document.querySelector(".resident-shell") || document.body;
  host.appendChild(overlay);
  const body = overlay.querySelector(".rmodal-body");
  const foot = overlay.querySelector(".rmodal-foot");
  if (body) {
    body.style.flex = "1 1 auto";
    body.style.minHeight = "0";
  }
  if (foot) foot.style.flexShrink = "0";
  createIcons({ icons });

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

  overlay.querySelector('[data-act="submit"]').addEventListener("click", async (e) => {
    const btn = e.currentTarget;
    if (btn.disabled) return;
    const errorEl = overlay.querySelector("#apply-error");
    errorEl.hidden = true;
    btn.disabled = true;
    try {
      await apiFetch("/adoptions", {
        method: "POST",
        body: {
          animal_id: animal.id,
          message: overlay.querySelector("#apply-message").value.trim() || undefined,
        },
      });
      showSubmitted(overlay, close);
      onApplied && onApplied();
    } catch (err) {
      btn.disabled = false;
      errorEl.querySelector("span").textContent =
        err.code === "NOT_ADOPTABLE"
          ? "Sorry — this animal is no longer available for adoption."
          : err.message || "Could not submit the application.";
      errorEl.hidden = false;
    }
  });
}

function showSubmitted(overlay, close) {
  const title = overlay.querySelector("#apply-title");
  if (title) title.textContent = "Application submitted";
  const icon = overlay.querySelector(".rmodal-head i");
  if (icon) icon.setAttribute("data-lucide", "check-circle-2");
  const body = overlay.querySelector(".rmodal-body");
  const foot = overlay.querySelector(".rmodal-foot");
  if (body) {
    body.innerHTML = `<p class="m-0 text-sm text-muted-foreground">Your application was sent. It is pending until City Vet approves it.</p>`;
  }
  if (foot) {
    foot.innerHTML = `
      <button type="button" class="rbtn rbtn--ghost" data-act="close">Close</button>
      <a class="rbtn rbtn--solid" href="/adoptions/">My Adoptions</a>`;
    const closeBtn = foot.querySelector('[data-act="close"]');
    if (closeBtn) closeBtn.addEventListener("click", close);
  }
  createIcons({ icons });
}
