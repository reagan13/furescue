import { createIcons, icons } from "lucide";
import { apiFetchFull, redirectToLogin } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { initResidentShell } from "/assets/js/components/resident-shell.js";
import { toast } from "/shared/components/toast/toast.js";
import { esc } from "/assets/js/lib/format.js";
import { initSelect } from "/shared/components/select/select.js";
import { openModelViewer, open360Viewer } from "./3d-viewer.js";
import { parseUrls } from "./photos.js";

const PER_PAGE = 12;

const state = {
  page: 1,
  total: 0,
  shown: 0,
  loading: false,
  failed: false,
  queued: false,
  species: "",
  sex: "",
  breed_type: "",
};

const registry = new Map();
const el = (id) => document.getElementById(id);

function label(value) {
  const raw = String(value || "").trim();
  if (!raw) return "";
  return raw.charAt(0).toUpperCase() + raw.slice(1);
}

function cardHtml(animal) {
  const photo = parseUrls(animal.photo_urls)[0] || null;
  const name = animal.name && String(animal.name).trim() !== "" ? String(animal.name).trim() : "Unnamed";
  const sex = animal.sex === "female" ? "♀ Female" : animal.sex === "male" ? "♂ Male" : "";
  const metaParts = [label(animal.breed_type), animal.age_estimate ? String(animal.age_estimate) : ""]
    .filter(Boolean)
    .join(" · ");
  const spin = parseUrls(animal.photo_360_set);

  return `
  <article class="racard" data-id="${esc(animal.id)}" tabindex="0" aria-label="View ${esc(name)}">
    <div class="racard-thumb">
      ${
        photo
          ? `<img src="${esc(photo)}" alt="Photo of ${esc(name)}" loading="lazy">`
          : `<div class="racard-placeholder"><i data-lucide="${
              animal.species === "cat" ? "cat" : "dog"
            }"></i></div>`
      }
      <span class="racard-flag"><span class="rchip rchip--success">Available</span></span>
    </div>
    <div class="racard-body">
      <div class="racard-top">
        <span class="racard-name">${esc(name)}</span>
        ${sex ? `<span class="racard-sex">${esc(sex)}</span>` : ""}
      </div>
      ${metaParts ? `<p class="racard-meta">${esc(metaParts)}</p>` : ""}
      <div class="racard-tags">
        <span class="rchip">${esc(label(animal.species) || "Pet")}</span>
        ${
          animal.model_3d_url
            ? `<button type="button" class="rchip rchip--sky" data-view-3d title="View 3D model"><i data-lucide="rotate-3d"></i>3D</button>`
            : ""
        }
        ${
          spin.length
            ? `<button type="button" class="rchip rchip--sky" data-view-360 title="View 360° photos"><i data-lucide="refresh-cw"></i>360°</button>`
            : ""
        }
      </div>
    </div>
  </article>`;
}

function renderCards(items, append) {
  remember(items);
  const grid = el("gallery-grid");
  if (append) {
    grid.insertAdjacentHTML("beforeend", items.map(cardHtml).join(""));
  } else {
    grid.innerHTML = items.map(cardHtml).join("");
  }
  createIcons({ icons });
}

function remember(items) {
  items.forEach((a) => {
    if (a && a.id) registry.set(a.id, a);
  });
}

function updateMeta() {
  const empty = el("gallery-empty");
  const title = empty.querySelector(".rempty-title");
  const text = empty.querySelector(".rempty-text");
  if (state.failed) {
    if (title) title.textContent = "Could not load animals";
    if (text) text.textContent = "Check your connection and try again.";
    empty.hidden = false;
  } else {
    if (title) title.textContent = "No animals match your filters";
    if (text) {
      text.textContent = "Try clearing a filter — or check back soon, new rescues arrive regularly.";
    }
    empty.hidden = state.shown > 0;
  }
  el("gallery-count").hidden = state.failed;
  el("gallery-count").textContent = `Showing ${state.shown} of ${state.total} available ${
    state.total === 1 ? "animal" : "animals"
  }`;
  el("load-more").hidden = state.failed || state.shown >= state.total || state.shown === 0;
}

async function load({ append = false } = {}) {
  if (state.loading) {
    if (!append) state.queued = true;
    return;
  }
  state.loading = true;
  state.queued = false;

  const params = new URLSearchParams({
    adoption_status: "available",
    per_page: String(PER_PAGE),
    page: String(append ? state.page + 1 : 1),
  });
  const q = (el("filter-q").value || "").trim();
  if (q) params.set("q", q);
  if (state.species) params.set("species", state.species);
  if (state.sex) params.set("sex", state.sex);
  if (state.breed_type) params.set("breed_type", state.breed_type);

  try {
    const payload = await apiFetchFull(`/animals?${params}`);
    const items = Array.isArray(payload.data) ? payload.data : [];
    const total = Number((payload.meta || {}).total) || items.length;
    state.page = Number((payload.meta || {}).page) || (append ? state.page + 1 : 1);
    state.total = total;
    state.failed = false;
    renderCards(items, append);
    state.shown = append ? state.shown + items.length : items.length;
  } catch (err) {
    if (err && err.status === 401) {
      redirectToLogin();
      return;
    }
    state.failed = true;
    toast(err.message || "Could not load the gallery.", { type: "error" });
  } finally {
    state.loading = false;
    updateMeta();
    if (state.queued) {
      state.queued = false;
      load({ append: false });
    }
  }
}

const refresh = () => load({ append: false });

function initEvents() {
  const grid = el("gallery-grid");
  if (!grid) return;

  grid.addEventListener("click", (event) => {
    const card = event.target.closest(".racard");
    if (!card) return;
    const animal = registry.get(card.dataset.id);
    if (event.target.closest("[data-view-3d]") && animal && animal.model_3d_url) {
      openModelViewer(animal);
      return;
    }
    if (event.target.closest("[data-view-360]") && animal) {
      open360Viewer(animal);
      return;
    }
    window.location.href = `/animals/detail.php?id=${encodeURIComponent(card.dataset.id)}`;
  });

  grid.addEventListener("keydown", (event) => {
    if (event.key !== "Enter") return;
    const card = event.target.closest(".racard");
    if (!card || event.target !== card) return;
    window.location.href = `/animals/detail.php?id=${encodeURIComponent(card.dataset.id)}`;
  });

  el("load-more").addEventListener("click", () => load({ append: true }));

  let debounce = null;
  const debouncedRefresh = () => {
    clearTimeout(debounce);
    debounce = setTimeout(refresh, 300);
  };
  el("filter-q")?.addEventListener("input", debouncedRefresh);
  initSelect(el("filter-species"), {
    "filter-species": (value) => {
      state.species = value || "";
      refresh();
    },
  });
  initSelect(el("filter-sex"), {
    "filter-sex": (value) => {
      state.sex = value || "";
      refresh();
    },
  });
  initSelect(el("filter-breed"), {
    "filter-breed": (value) => {
      state.breed_type = value || "";
      refresh();
    },
  });
}

function boot() {
  try {
    const user = bootstrapPageAuth();
    if (!user) {
      redirectToLogin();
      return;
    }
    initResidentShell();
    initEvents();
    refresh();
  } catch (err) {
    console.error("animals boot failed", err);
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot);
} else {
  boot();
}
