import { createIcons, icons } from "lucide";
import { requireAuth, apiFetchFull, redirectToLogin } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { toast } from "/shared/components/toast/toast.js";

const esc = (value) =>
  String(value ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  }[c]));

const STATUS_PILL_CLS = {
  pending_verification: "bg-teal/10 text-teal",
  verified: "bg-jungle/10 text-jungle",
  dismissed: "bg-muted text-muted-foreground",
};

const STATUS_LABEL = {
  pending_verification: "Pending verification",
  verified: "Verified",
  dismissed: "Dismissed",
};

const PILL_BASE =
  "inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-extrabold uppercase tracking-wide";

function timeAgo(value) {
  if (!value) return "";
  const ts = new Date(String(value).replace(" ", "T")).getTime();
  if (Number.isNaN(ts)) return "";
  const diff = Math.floor((Date.now() - ts) / 1000);
  if (diff < 60) return "Just now";
  if (diff < 3600) {
    const m = Math.floor(diff / 60);
    return m === 1 ? "1 min ago" : `${m} mins ago`;
  }
  if (diff < 86400) {
    const h = Math.floor(diff / 3600);
    return h === 1 ? "1 hr ago" : `${h} hrs ago`;
  }
  const d = Math.floor(diff / 86400);
  if (d === 1) return "Yesterday";
  if (d < 7) return `${d} days ago`;
  const date = new Date(ts);
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

function photoUrlsOf(report) {
  const raw = report.photo_urls;
  if (Array.isArray(raw)) {
    return raw.filter((u) => typeof u === "string" && u.trim() !== "");
  }
  if (!raw || typeof raw !== "string") return [];
  try {
    const decoded = JSON.parse(raw);
    return Array.isArray(decoded)
      ? decoded.filter((u) => typeof u === "string" && u.trim() !== "")
      : [];
  } catch {
    return [];
  }
}

function photosHtml(urls) {
  if (!urls.length) return "";
  const thumbs = urls
    .slice(0, 4)
    .map((url) => {
      const ext = (url.split("?")[0].split(".").pop() || "").toLowerCase();
      if (ext === "mp4" || ext === "webm") {
        return '<span class="flex h-16 w-16 items-center justify-center rounded-lg border border-border bg-secondary text-muted-foreground"><i data-lucide="film" class="h-5 w-5"></i></span>';
      }
      return `<img src="${esc(url)}" alt="Report photo" loading="lazy" class="h-16 w-16 rounded-lg border border-border object-cover">`;
    })
    .join("");
  return `<div class="mt-3 flex flex-wrap gap-2">${thumbs}</div>`;
}

function reportCard(report) {
  const status = String(report.status || "");
  const pillCls = STATUS_PILL_CLS[status] || "bg-muted text-muted-foreground";
  const label =
    STATUS_LABEL[status] || status.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
  let pills = `<span class="${PILL_BASE} ${pillCls}">${esc(label)}</span>`;
  if (report.validation_status === "flagged_duplicate" && status !== "dismissed") {
    pills += ` <span class="${PILL_BASE} bg-destructive/10 text-destructive">Flagged duplicate</span>`;
  }
  const when = timeAgo(report.created_at);
  const address = String(report.address_text || "").trim();
  const addressRow = address
    ? `<div class="mt-3 flex items-start gap-1.5 text-xs text-muted-foreground"><i data-lucide="map-pin" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i><span class="min-w-0">${esc(address)}</span></div>`
    : "";
  return `
  <article class="flex flex-col rounded-xl border bg-card p-4 text-card-foreground shadow-sm sm:p-5" data-report-id="${esc(report.id)}">
    <div class="flex flex-wrap items-center gap-2">
      ${pills}
      ${when ? `<time class="ml-auto text-xs text-muted-foreground" datetime="${esc(report.created_at)}">${esc(when)}</time>` : ""}
    </div>
    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed">${esc(report.animal_description || "")}</p>
    ${addressRow}
    ${photosHtml(photoUrlsOf(report))}
    <div class="mt-3">
      <a class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary" href="/messages/?compose=1&amp;type=report&amp;id=${esc(report.id)}">
        <i data-lucide="message-square" class="h-3.5 w-3.5"></i><span>Message City Vet</span>
      </a>
    </div>
  </article>`;
}

function emptyStateHtml() {
  return `
  <div id="reports-empty" class="md:col-span-2 rounded-xl border bg-card px-6 py-12 text-center">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-secondary">
      <i data-lucide="inbox" class="h-6 w-6 text-muted-foreground"></i>
    </div>
    <h2 class="mt-3 text-base font-extrabold">You haven't submitted any reports yet.</h2>
    <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">Spotted a stray animal in need? Pin its location and our rescue team will take it from there.</p>
    <a href="/report/" class="mt-4 inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-md bg-primary px-4 text-sm font-bold text-primary-foreground shadow transition-colors hover:bg-primary/90">Report an animal</a>
  </div>`;
}

function errorStateHtml(message) {
  return `
  <div id="reports-error" class="md:col-span-2 rounded-xl border bg-card px-6 py-12 text-center">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-secondary">
      <i data-lucide="triangle-alert" class="h-6 w-6 text-destructive"></i>
    </div>
    <h2 class="mt-3 text-base font-extrabold">Could not load your reports.</h2>
    <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">${esc(message || "Please try again.")}</p>
    <button type="button" id="reports-retry" class="mt-4 inline-flex h-8 items-center justify-center gap-2 whitespace-nowrap rounded-md border border-input bg-background px-3 text-[13px] font-medium transition-colors hover:bg-accent hover:text-accent-foreground">
      <i data-lucide="refresh-cw" class="h-4 w-4"></i><span>Try again</span>
    </button>
  </div>`;
}

function renderReports(reports) {
  const list = document.getElementById("reports-list");
  if (!list) return;
  list.innerHTML = reports.length ? reports.map(reportCard).join("") : emptyStateHtml();
  createIcons({ icons });
}

function renderError(message) {
  const list = document.getElementById("reports-list");
  if (!list) return;
  list.innerHTML = errorStateHtml(message);
  createIcons({ icons });
  document.getElementById("reports-retry")?.addEventListener("click", () => {
    const btn = document.getElementById("refresh-reports");
    if (btn) refreshReports(btn);
  });
}

async function refreshReports(btn) {
  btn.disabled = true;
  btn.querySelector(".lucide")?.classList.add("animate-spin");
  try {
    const payload = await apiFetchFull("/reports/me?page=1&per_page=50");
    const reports = Array.isArray(payload?.data) ? payload.data : [];
    renderReports(reports);
    const count = document.getElementById("reports-count");
    if (count) {
      count.textContent =
        reports.length === 1 ? "1 report submitted" : `${reports.length} reports submitted`;
    }
    toast("Reports refreshed.", { type: "success" });
  } catch (err) {
    if (err.status === 401) {
      toast("Your session expired. Please sign in again.", { type: "error" });
      setTimeout(redirectToLogin, 1200);
      return;
    }
    renderError(err.message || "Could not refresh reports.");
    toast(err.message || "Could not refresh reports.", { type: "error" });
  } finally {
    btn.disabled = false;
    btn.querySelector(".lucide")?.classList.remove("animate-spin");
  }
}

function showFlash() {
  const raw = sessionStorage.getItem("furescue_flash");
  if (!raw) return;
  sessionStorage.removeItem("furescue_flash");
  try {
    const flash = JSON.parse(raw);
    if (flash && flash.message) toast(flash.message, { type: flash.type || "default", duration: 5000 });
  } catch {
    /* ignore malformed flash */
  }
}

function bindRefresh() {
  document.getElementById("refresh-reports")?.addEventListener("click", (e) => {
    refreshReports(e.currentTarget);
  });
  document.getElementById("reports-retry")?.addEventListener("click", () => {
    const btn = document.getElementById("refresh-reports");
    if (btn) refreshReports(btn);
  });
}

function boot() {
  bootstrapPageAuth();
  if (!requireAuth()) return;
  createIcons({ icons });
  showFlash();
  bindRefresh();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot);
} else {
  boot();
}
