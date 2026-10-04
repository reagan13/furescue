export function esc(value) {
  return String(value ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  }[c]));
}

export function stampCls(status) {
  if (status === "pending") return "stamp--coral";
  if (status === "approved" || status === "completed") return "stamp--accent";
  return "stamp--muted";
}

export function applicantName(a) {
  return (a && a.applicant_name) || "";
}

export function animalName(a) {
  return (a && a.animal_name) || "";
}

export function speciesLabel(species) {
  const s = String(species || "").toLowerCase();
  if (s === "cat") return "Cat";
  if (s === "dog") return "Dog";
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : "";
}

export function breedLabel(breed) {
  const b = String(breed || "").toLowerCase();
  if (b === "aspin") return "Aspin";
  if (b === "puspin") return "Puspin";
  return b ? b.charAt(0).toUpperCase() + b.slice(1) : "";
}

export function firstPhoto(urls) {
  if (!urls) return "";
  let value = urls;
  if (typeof value === "string") {
    try {
      value = JSON.parse(value);
    } catch {
      return value.startsWith("/") || value.startsWith("http") ? value : "";
    }
  }
  if (!Array.isArray(value)) return "";
  const first = value.find((item) => typeof item === "string" && item !== "");
  return first || "";
}

export function animalHeadline(a) {
  const name = animalName(a) || "Unnamed";
  const species = speciesLabel(a && a.species);
  const breed = breedLabel(a && a.breed_type);
  const kind = [species, breed ? `(${breed})` : ""].filter(Boolean).join(" ");
  return kind ? `${name} · ${kind}` : name;
}
