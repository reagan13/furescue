import { createIcons, icons } from "lucide";
import { Button } from "/shared/components/button/button.js";
import { toast } from "/shared/components/toast/toast.js";
import { fetchPendingAdoptions, fetchReports, fetchCases } from "../api.js";
import { state } from "../state.js";
import { buildComposeTargets, contextLabel, esc } from "../util.js";
import { startConversation } from "../workflow/actions.js";

const TYPE_OPTIONS = [
  { value: "adoption", label: "Pending adoption" },
  { value: "report", label: "Report" },
  { value: "case", label: "Case" },
];

function optionsHtml(list) {
  if (!list.length) {
    return `<option value="">No targets available</option>`;
  }
  return list
    .map(
      (t) =>
        `<option value="${esc(t.related_type)}|${esc(t.related_id)}|${esc(t.receiver_id)}" data-name="${esc(t.name)}">${esc(t.label)}</option>`
    )
    .join("");
}

function firstTypeWithTargets(targets) {
  return TYPE_OPTIONS.find((t) => (targets[t.value] || []).length)?.value || "adoption";
}

export async function openComposeDialog() {
  const overlay = document.createElement("div");
  overlay.className = "dialog-overlay";
  overlay.innerHTML = `
    <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="amsg-compose-title">
      <div class="dialog-head">
        <div class="dialog-title-wrap">
          <i data-lucide="plus" class="dialog-icon"></i>
          <h3 class="dialog-title" id="amsg-compose-title">Start conversation</h3>
        </div>
        <button type="button" class="dialog-x" aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <div class="dialog-body">
        <p class="dialog-message">Message an applicant or reporter about a pending adoption, report, or case. The thread appears in this inbox after you send.</p>
        <p class="dialog-message" id="amsg-compose-status">Loading targets&hellip;</p>
        <label class="dialog-label" for="amsg-compose-type">Context<span class="dialog-req"> *</span>
          <select id="amsg-compose-type" class="dialog-input amsg-compose-select" disabled>
            ${TYPE_OPTIONS.map((t) => `<option value="${t.value}">${t.label}</option>`).join("")}
          </select>
        </label>
        <label class="dialog-label" for="amsg-compose-target">To<span class="dialog-req"> *</span>
          <select id="amsg-compose-target" class="dialog-input amsg-compose-select" disabled>
            <option value="">Loading&hellip;</option>
          </select>
        </label>
        <label class="dialog-label" for="amsg-compose-text">Message<span class="dialog-req"> *</span>
          <textarea id="amsg-compose-text" class="dialog-input input--area" rows="4" maxlength="4000" placeholder="Write the first message&hellip;"></textarea>
        </label>
        <p class="dialog-error is-hidden" id="amsg-compose-error"></p>
      </div>
      <div class="dialog-foot">
        ${Button({ text: "Cancel", variant: "outline", attrs: 'data-act="close"' })}
        ${Button({ text: "Send", variant: "default", icon: "send-horizontal", attrs: 'data-act="send"' })}
      </div>
    </div>`;

  document.body.appendChild(overlay);
  createIcons({ icons });

  const typeEl = overlay.querySelector("#amsg-compose-type");
  const targetEl = overlay.querySelector("#amsg-compose-target");
  const textEl = overlay.querySelector("#amsg-compose-text");
  const statusEl = overlay.querySelector("#amsg-compose-status");
  const errorEl = overlay.querySelector("#amsg-compose-error");
  const sendBtn = overlay.querySelector('[data-act="send"]');

  let targets = { adoption: [], report: [], case: [] };
  let sending = false;

  const close = () => overlay.remove();

  overlay.querySelector('[data-act="close"]').addEventListener("click", close);
  overlay.querySelector(".dialog-x").addEventListener("click", close);
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) close();
  });

  const fillTargets = (type) => {
    const list = targets[type] || [];
    targetEl.innerHTML = optionsHtml(list);
    targetEl.disabled = list.length === 0;
  };

  typeEl.addEventListener("change", () => fillTargets(typeEl.value));

  sendBtn.addEventListener("click", async () => {
    if (sending) return;
    errorEl.classList.add("is-hidden");
    const raw = String(targetEl.value || "");
    const [related_type, related_id, receiver_id] = raw.split("|");
    const message_text = String(textEl.value || "").trim();
    const name = targetEl.selectedOptions[0]?.dataset.name || contextLabel(related_type);
    if (!related_type || !related_id || !receiver_id) {
      errorEl.textContent = "Pick someone to message.";
      errorEl.classList.remove("is-hidden");
      return;
    }
    if (!message_text) {
      errorEl.textContent = "Write a message before sending.";
      errorEl.classList.remove("is-hidden");
      return;
    }
    sending = true;
    sendBtn.disabled = true;
    try {
      await startConversation({
        related_type,
        related_id,
        receiver_id,
        other_user_name: name,
        message_text,
      });
      close();
    } catch (err) {
      errorEl.textContent = err.message || "Could not send the message.";
      errorEl.classList.remove("is-hidden");
      sending = false;
      sendBtn.disabled = false;
    }
  });

  try {
    const meId = state.me && state.me.id;
    const [adoptions, reports, cases] = await Promise.all([
      fetchPendingAdoptions().catch(() => []),
      fetchReports().catch(() => []),
      fetchCases().catch(() => []),
    ]);
    targets = buildComposeTargets({ adoptions, reports, cases, meId });
    const total = targets.adoption.length + targets.report.length + targets.case.length;
    const initial = firstTypeWithTargets(targets);
    typeEl.value = initial;
    typeEl.disabled = false;
    fillTargets(initial);
    if (total === 0) {
      statusEl.textContent = "No pending adoptions, reports, or cases with a resident to message.";
      sendBtn.disabled = true;
    } else {
      statusEl.textContent = "Choose a context, then send the first message.";
    }
  } catch (err) {
    statusEl.textContent = err.message || "Could not load compose targets.";
    toast(statusEl.textContent, { type: "error" });
  }
}
