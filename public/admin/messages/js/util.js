import { esc } from "/assets/js/lib/format.js";

export { esc };

export const CONTEXT_LABEL = { report: "Report", case: "Case", adoption: "Adoption" };

export const CONTEXT_STAMP = {
  report: "stamp--coral",
  case: "stamp--accent",
  adoption: "stamp--jungle",
};

export function contextLabel(type) {
  return CONTEXT_LABEL[type] || String(type || "Context");
}

export function contextStamp(type) {
  return CONTEXT_STAMP[type] || "stamp--muted";
}

export function initialOf(name) {
  return String(name || "?").trim().charAt(0).toUpperCase() || "?";
}

export function shortId(id) {
  if (!id) return "—";
  return "#" + String(id).replace(/-/g, "").slice(0, 4).toUpperCase();
}

export function dayKey(input) {
  return String(input || "").slice(0, 10);
}

export function dayLabel(input) {
  const d = new Date(String(input || "").replace(" ", "T"));
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

export function buildComposeTargets({ adoptions = [], reports = [], cases = [], meId = "" } = {}) {
  const me = String(meId || "");
  const reportById = new Map(reports.map((r) => [String(r.id), r]));

  const adoption = [];
  for (const row of adoptions) {
    const receiverId = String(row.applicant_id || "");
    if (!receiverId || receiverId === me || !row.id) continue;
    adoption.push({
      related_type: "adoption",
      related_id: String(row.id),
      receiver_id: receiverId,
      name: row.applicant_name || "Applicant",
      label: `${row.applicant_name || "Applicant"} · ${row.animal_name || "Animal"}`,
    });
  }

  const report = [];
  for (const row of reports) {
    const receiverId = String(row.resident_id || "");
    if (!receiverId || receiverId === me || !row.id) continue;
    const place = row.address_text || row.animal_description || "Report";
    report.push({
      related_type: "report",
      related_id: String(row.id),
      receiver_id: receiverId,
      name: "Reporter",
      label: `Report ${shortId(row.id)} · ${place}`,
    });
  }

  const caseTargets = [];
  for (const row of cases) {
    const linked = row.report_id ? reportById.get(String(row.report_id)) : null;
    const receiverId = String((linked && linked.resident_id) || "");
    if (!receiverId || receiverId === me || !row.id) continue;
    const place = row.address_text || row.animal_description || "Case";
    caseTargets.push({
      related_type: "case",
      related_id: String(row.id),
      receiver_id: receiverId,
      name: "Reporter",
      label: `Reporter · Case ${shortId(row.id)} · ${place}`,
    });
    const rescuerId = String(row.assigned_rescuer_id || "");
    if (rescuerId && rescuerId !== me) {
      caseTargets.push({
        related_type: "case",
        related_id: String(row.id),
        receiver_id: rescuerId,
        name: row.assigned_rescuer_name || "Rescuer",
        label: `Rescuer ${row.assigned_rescuer_name || ""} · Case ${shortId(row.id)}`.trim(),
      });
    }
  }

  return { adoption, report, case: caseTargets };
}
