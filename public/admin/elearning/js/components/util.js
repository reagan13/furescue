import { EmptyState } from "/shared/components/empty-state/empty-state.js";

export const PAGE_SIZE = 20;

export const CATEGORIES = [
  { key: "dog_behavior", label: "Dog Behavior", icon: "dog" },
  { key: "cat_behavior", label: "Cat Behavior", icon: "cat" },
  { key: "basic_training", label: "Basic Training", icon: "award" },
  { key: "general_care", label: "General Care", icon: "heart-pulse" },
];

export const STATUS_FILTERS = [
  { key: "all", label: "All" },
  { key: "draft", label: "Draft" },
  { key: "published", label: "Published" },
];

export function categoryMeta(category) {
  return (
    CATEGORIES.find((c) => c.key === category) || {
      key: category,
      label: String(category || "Module").replaceAll("_", " "),
      icon: "book-open",
    }
  );
}

export function emptyEditor() {
  return {
    id: null,
    title: "",
    category: "general_care",
    content_body: "",
    quiz_json: "",
    published_status: "draft",
  };
}

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

export function statusStampCls(status) {
  return status === "published" ? "stamp--accent" : "stamp--muted";
}

export function statusLabel(status) {
  return status === "published" ? "Published" : "Draft";
}

export function emptyState(icon = "book-open", text = "No modules yet.") {
  return EmptyState({ icon, text });
}
