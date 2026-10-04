import { createIcons, icons } from "lucide";
import { apiFetch } from "/assets/js/lib/api.js";
import { toast } from "/shared/components/toast/toast.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import { esc } from "/assets/js/lib/format.js";

export async function openCompose({ onSent, preselect } = {}) {
  let targets;
  try {
    targets = await apiFetch("/messages/compose-targets");
  } catch (err) {
    toast(err.message || "Could not start a conversation.", { type: "error" });
    return;
  }

  const topics = Array.isArray(targets.topics) ? targets.topics : [];
  const receiver = targets.receiver;
  if (!receiver) {
    toast("No City Veterinary Office account is available yet.", { type: "error" });
    return;
  }

  const overlay = document.createElement("div");
  overlay.className = "rmodal-overlay";
  const options = topics.length
    ? topics
        .map((t) => {
          const value = `${t.related_type}|${t.related_id}`;
          const selected =
            preselect &&
            preselect.type === t.related_type &&
            preselect.id === t.related_id
              ? " selected"
              : "";
          return `<option value="${esc(value)}"${selected}>${esc(t.label)}</option>`;
        })
        .join("")
    : `<option value="">No reports or applications yet</option>`;

  overlay.innerHTML = `
    <div class="rmodal" role="dialog" aria-modal="true" aria-labelledby="compose-title">
      <div class="rmodal-head">
        <i data-lucide="message-square-plus" class="text-primary"></i>
        <h2 class="rmodal-title" id="compose-title">Message City Vet</h2>
        <button type="button" class="rmodal-x" aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <div class="rmodal-body">
        <p class="m-0 text-sm text-muted-foreground">Start a conversation about one of your reports or adoption applications. ${esc(
          receiver.full_name
        )} will see it in the office inbox.</p>
        <div class="rform-field">
          <label for="compose-topic" class="rform-label">About</label>
          <select id="compose-topic" class="input" ${topics.length ? "" : "disabled"}>${options}</select>
        </div>
        <div class="rform-field">
          <label for="compose-text" class="rform-label">Message</label>
          <textarea id="compose-text" class="input input--area" rows="4" maxlength="4000" placeholder="Write the first message…"></textarea>
        </div>
        <p class="rform-error" id="compose-error" hidden><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i><span></span></p>
      </div>
      <div class="rmodal-foot">
        <button type="button" class="rbtn rbtn--ghost" data-act="cancel">Cancel</button>
        <button type="button" class="rbtn rbtn--solid" data-act="send" ${topics.length ? "" : "disabled"}><i data-lucide="send-horizontal"></i><span>Send</span></button>
      </div>
    </div>`;

  const host = document.querySelector(".resident-shell") || document.body;
  host.appendChild(overlay);
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

  if (!topics.length) {
    overlay.querySelector("#compose-error").hidden = false;
    overlay.querySelector("#compose-error span").textContent =
      "File a report or apply to adopt first — then you can message the team about it.";
    return;
  }

  overlay.querySelector('[data-act="send"]').addEventListener("click", async () => {
    const raw = overlay.querySelector("#compose-topic").value;
    const text = overlay.querySelector("#compose-text").value.trim();
    const errorEl = overlay.querySelector("#compose-error");
    if (!raw || !text) {
      errorEl.querySelector("span").textContent = "Pick a topic and write a message.";
      errorEl.hidden = false;
      return;
    }
    const [related_type, related_id] = raw.split("|");
    const ok = await confirmDialog({
      title: "Send this message?",
      message: `This will start a conversation with ${receiver.full_name}.`,
      confirmText: "Send message",
    });
    if (!ok) return;
    try {
      await apiFetch("/messages", {
        method: "POST",
        body: {
          receiver_id: receiver.id,
          related_type,
          related_id,
          message_text: text,
        },
      });
      close();
      toast("Message sent.", { type: "success" });
      if (typeof onSent === "function") await onSent({ related_type, related_id, other_user_id: receiver.id, other_user_name: receiver.full_name });
    } catch (err) {
      errorEl.querySelector("span").textContent = err.message || "Could not send.";
      errorEl.hidden = false;
    }
  });
}
