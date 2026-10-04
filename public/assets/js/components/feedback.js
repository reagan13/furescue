import { createIcons, icons } from "lucide";
import { apiFetch } from "/assets/js/lib/api.js";
import { toast } from "/shared/components/toast/toast.js";

export function initFeedback() {
  const openBtn = document.getElementById("rfeedback-open");
  if (!openBtn || openBtn.dataset.bound === "1") return;
  openBtn.dataset.bound = "1";
  openBtn.addEventListener("click", openFeedback);
}

function openFeedback() {
  const overlay = document.createElement("div");
  overlay.className = "rmodal-overlay";
  overlay.innerHTML = `
    <div class="rmodal" role="dialog" aria-modal="true" aria-labelledby="feedback-title">
      <div class="rmodal-head">
        <i data-lucide="flag" class="text-primary"></i>
        <h2 class="rmodal-title" id="feedback-title">Report a problem</h2>
        <button type="button" class="rmodal-x" aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <div class="rmodal-body">
        <p class="m-0 text-sm text-muted-foreground">Tell City Vet what went wrong. They will see this in their notification inbox.</p>
        <div class="rform-field">
          <label for="feedback-text" class="rform-label">What happened?</label>
          <textarea id="feedback-text" class="input input--area" rows="5" maxlength="2000" required placeholder="A short note about the bug or inconvenience."></textarea>
        </div>
        <p class="rform-error" id="feedback-error" hidden><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i><span></span></p>
      </div>
      <div class="rmodal-foot">
        <button type="button" class="rbtn rbtn--ghost" data-act="cancel">Cancel</button>
        <button type="button" class="rbtn rbtn--solid" data-act="send"><i data-lucide="send"></i><span>Send report</span></button>
      </div>
    </div>`;
  document.body.appendChild(overlay);
  createIcons({ icons });

  const close = () => overlay.remove();
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) close();
  });
  overlay.querySelector('[data-act="cancel"]').addEventListener("click", close);
  overlay.querySelector(".rmodal-x").addEventListener("click", close);
  overlay.querySelector('[data-act="send"]').addEventListener("click", async () => {
    const text = overlay.querySelector("#feedback-text").value.trim();
    const errorEl = overlay.querySelector("#feedback-error");
    if (!text) {
      errorEl.querySelector("span").textContent = "Please describe the problem.";
      errorEl.hidden = false;
      return;
    }
    try {
      await apiFetch("/feedback", {
        method: "POST",
        body: { message: text, page_url: window.location.pathname },
      });
      close();
      toast("Thanks — the team was notified.", { type: "success" });
    } catch (err) {
      errorEl.querySelector("span").textContent = err.message || "Could not send.";
      errorEl.hidden = false;
    }
  });
}
