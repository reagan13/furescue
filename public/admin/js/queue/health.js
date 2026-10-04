import { shortId, titleCase } from "../helpers.js";
import { state } from "../state.js";

export function details(id) {
  const h = state.healthUpdates.items.find((i) => i.id === id);
  return h
    ? [
        { label: "Update", value: shortId(h.id) },
        { label: "Animal", value: [h.animal_name, h.breed_type].filter(Boolean).join(", ") || "—" },
        { label: "Species", value: titleCase(h.species) || "—" },
        { label: "Rescue status", value: titleCase(h.rescue_status) || "—" },
        { label: "Health status", value: h.health_status === "healthy" ? "Stable" : "Needs Attention" },
        { label: "Logged by", value: h.logged_by_name || "—" },
        { label: "Logged at", value: new Date(h.logged_at).toLocaleString() },
      ]
    : [];
}
