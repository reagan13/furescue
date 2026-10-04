export function esc(value) {
  return String(value ?? "").replace(/[&<>"']/g, (c) =>
    ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    }[c])
  );
}

export const STATUS_LABELS = {
  pending_review: "In review",
  approved: "Live",
  rejected: "Rejected",
};

export function statusLabel(status) {
  return STATUS_LABELS[status] || String(status || "—").replace(/_/g, " ");
}

export function stampCls(status) {
  if (status === "pending_review") return "stamp--coral";
  if (status === "rejected") return "stamp--muted";
  return "stamp--accent";
}

export function panelTitle(filter) {
  if (filter === "pending_review") return "In review";
  if (filter === "approved") return "Live listings";
  if (filter === "rejected") return "Rejected";
  return "All listings";
}

export function speciesLabel(species) {
  const s = String(species || "").toLowerCase();
  if (s === "cat") return "Cat";
  if (s === "dog") return "Dog";
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : "—";
}

export function emptyMessage(filter, query) {
  if (query && query.trim()) return "No listings match.";
  if (filter === "pending_review") return "No listings awaiting review.";
  if (filter === "approved") return "No live listings.";
  if (filter === "rejected") return "No rejected listings.";
  return "No adoption listings yet.";
}
