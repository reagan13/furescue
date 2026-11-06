import { esc } from "../../health-records/components/util.js";
import {
  TONE,
  ICON,
  chip,
  emptyState,
  toneFor,
  titleCase,
  interpretOverview,
  selectField,
  DEWORMING_OPTIONS,
  NEUTERED_OPTIONS,
} from "../util.js";

export function OverviewPanel(o, vaccinations = [], { editing = false } = {}) {
  if (!o) return "";
  const sub = (field, label, value, extra = "") => {
    const tone = toneFor(field, value);
    return `
    <div class="hr-subcard">
      <span class="tint-circle ${TONE[tone].split(" ")[0]}"><i data-lucide="${ICON[tone] || "circle"}"></i></span>
      <div>
        <div class="hr-subcard-label">${esc(label)}</div>
        <div class="hr-subcard-value">${chip(tone, titleCase(value))}</div>
        ${extra}
      </div>
    </div>`;
  };

  const list = (vaccinations || [])
    .map((v) => ({ name: (v.vaccine || "").trim(), date: v.dateGiven || v.date || null, status: v.status || null }))
    .filter((v) => v.name)
    .sort((a, b) => String(b.date || "").localeCompare(String(a.date || "")));
  const latest = list[0] || null;

  const interpretation = interpretOverview(o);
  const savedNotes = o.notes && String(o.notes).trim()
    ? `<div class="hr-notes"><p class="hr-notes-text">${esc(o.notes)}</p>${o.notesMeta ? `<p class="hr-notes-meta">${esc(o.notesMeta)}</p>` : ""}</div>`
    : "";
  const interpretationHtml = interpretation
    ? `<div class="hr-notes"><p class="hr-notes-text">${esc(interpretation)}</p><p class="hr-notes-meta">${esc(o.notesMeta || "Interpretation of the health overview data above")}</p></div>`
    : "";
  const notes = savedNotes + interpretationHtml || emptyState("No health data recorded");

  const editFields = editing
    ? `<div class="hr-edit-fields">
        <label class="dialog-label">Deworming${selectField({
          id: "hr-deworming",
          name: "deworming_status",
          options: DEWORMING_OPTIONS,
          value: o.deworming || "unknown",
          placeholder: "Deworming",
        })}</label>
        <label class="dialog-label">Neutered${selectField({
          id: "hr-neutered",
          name: "neutered",
          options: NEUTERED_OPTIONS,
          value: o.neutered || "unknown",
          placeholder: "Neutered",
        })}</label>
      </div>`
    : "";

  return `
  <section class="panel hr-overview-panel">
    <div class="panel-head">
      <div class="panel-title-wrap"><i data-lucide="activity"></i><h3 class="panel-title">Health Overview</h3></div>
    </div>
    <div class="panel-body hr-overview-body">
      <div class="hr-subcards">
        ${sub("healthStatus", "Health Status", o.healthStatus)}
        ${sub("vaccinationStatus", "Vaccination", latest ? latest.name : o.vaccinationStatus)}
        ${sub("deworming", "Deworming", o.deworming)}
        ${sub("neutered", "Neutered", o.neutered)}
      </div>
      ${editFields}
      ${notes}
    </div>
  </section>`;
}
