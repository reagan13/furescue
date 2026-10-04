export function parseUrls(value) {
  if (!value) return [];
  if (Array.isArray(value)) {
    return value.filter((u) => typeof u === "string" && u.trim() !== "");
  }
  if (typeof value !== "string") return [];
  const trimmed = value.trim();
  if (!trimmed || trimmed === "null" || trimmed === "[]") return [];
  if (trimmed.startsWith("/") || /^https?:\/\//i.test(trimmed)) return [trimmed];
  try {
    const parsed = JSON.parse(trimmed);
    if (Array.isArray(parsed)) {
      return parsed.filter((u) => typeof u === "string" && u.trim() !== "");
    }
    if (typeof parsed === "string" && parsed.trim() !== "") return [parsed];
  } catch {
    /* not JSON */
  }
  return [];
}
