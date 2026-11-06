import { createIcons, icons } from "lucide";
import { Button } from "/shared/components/button/button.js";
import { DatePicker, initDatePicker } from "/shared/components/date-picker/date-picker.js";
import { Select, initSelect, getSelectValue } from "/shared/components/select/select.js";
import { Spinner } from "/shared/components/spinner/spinner.js";
import { toast } from "/shared/components/toast/toast.js";
import { upsertAnimalMedical } from "/assets/js/admin/admin-data.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";

function esc(value) {
  return String(value ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  }[c]));
}

export function openHealthRecordDialog(animal) {
  return new Promise((resolve) => {
    const overlay = document.createElement("div");
    overlay.className = "dialog-overlay";

    const vaxOptions = [
      { value: "none", label: "Not vaccinated" },
      { value: "partial", label: "Partial" },
      { value: "complete", label: "Up to date" },
    ];

    overlay.innerHTML = `
      <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="health-title">
        <div class="dialog-head">
          <div class="dialog-title-wrap">
            <i data-lucide="heart-pulse" class="dialog-icon"></i>
            <h3 class="dialog-title" id="health-title">Health record &middot; ${esc(animal.name)}</h3>
          </div>
          <button type="button" class="dialog-x" aria-label="Close"><i data-lucide="x"></i></button>
        </div>
        <div class="dialog-body rescuer-modal-body">
          <div class="add-animal-form">
            <label class="dialog-label" for="hr-notes">Medical history notes</label>
            <textarea class="dialog-input" id="hr-notes" rows="3" placeholder="e.g. Recovering well, mild limp on front left leg"></textarea>

            <label class="dialog-label">Vaccination status${Select({
              id: "hr-vax",
              options: vaxOptions,
              value: "none",
              className: "w-full",
            })}</label>

            <label class="dialog-label" for="hr-date">Last checkup date</label>
            ${DatePicker({ id: "hr-date", name: "lastCheckup", placeholder: "Pick a date", max: new Date().toISOString().slice(0, 10) })}

            <label class="dialog-label" for="hr-details">Vaccination details</label>
            <input class="dialog-input" id="hr-details" placeholder="e.g. Anti-rabies on 2026-05-12" autocomplete="off" />

            <p class="dialog-error" id="hr-error" hidden></p>
          </div>
        </div>
        <div class="dialog-foot">
          ${Button({ text: "Cancel", variant: "outline", attrs: 'data-act="cancel"' })}
          ${Button({ text: "Save record", variant: "default", icon: "heart-pulse", attrs: 'data-act="ok"' })}
        </div>
      </div>`;

    document.body.appendChild(overlay);
    createIcons({ icons });
    initSelect(overlay);
    initDatePicker(overlay);

    const notesEl = overlay.querySelector("#hr-notes");
    const vaxWrap = overlay.querySelector("#hr-vax");
    const dateEl = overlay.querySelector("#hr-date-value");
    const detailsEl = overlay.querySelector("#hr-details");
    const errorEl = overlay.querySelector("#hr-error");
    notesEl && notesEl.focus();

    const close = () => {
      overlay.remove();
      resolve(null);
    };

    let busy = false;
    const submit = async () => {
      if (busy) return;
      const details = detailsEl.value.trim();
      const body = {
        medical_history_notes: notesEl.value.trim(),
        vaccination_status: getSelectValue(vaxWrap) || "none",
        last_checkup_date: dateEl?.value || null,
      };
      if (details) body.vaccination_details = [details];
      busy = true;
      const confirmed = await confirmDialog({
        title: "Save this health record?",
        message: `Save medical notes for "${animal.name}"?`,
        confirmText: "Save",
        cancelText: "Cancel",
      });
      if (!confirmed) {
        busy = false;
        return;
      }
      const okBtn = overlay.querySelector('[data-act="ok"]');
      okBtn.disabled = true;
      okBtn.innerHTML = `${Spinner({ size: 16 })}<span>Saving…</span>`;
      try {
        await upsertAnimalMedical(animal.id, body);
        toast("Health record saved.", { type: "success" });
        overlay.remove();
        resolve(animal);
        window.location.assign(`/admin/health-records/health-record.php?id=${encodeURIComponent(animal.id)}`);
      } catch (err) {
        busy = false;
        okBtn.disabled = false;
        okBtn.innerHTML = `<i data-lucide="heart-pulse"></i><span>Save record</span>`;
        createIcons({ icons });
        errorEl.textContent = err && err.message ? err.message : "Failed to save health record.";
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
