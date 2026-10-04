import { createIcons, icons } from "lucide";
import { apiFetch, apiFetchFull, PORTAL_ROLES, requireAuth } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { esc } from "/assets/js/lib/format.js";
import { initResidentShell } from "/assets/js/components/resident-shell.js";
import { toast } from "/shared/components/toast/toast.js";
import { readingPercent, renderQuiz, saveReadPct, showBadge } from "./lesson.js";

const CATEGORY_META = {
  dog_behavior: { label: "Dog Behavior", icon: "dog" },
  cat_behavior: { label: "Cat Behavior", icon: "cat" },
  basic_training: { label: "Basic Training", icon: "award" },
  general_care: { label: "General Care", icon: "heart-pulse" },
};

const state = {
  modules: [],
  progress: new Map(),
  currentId: null,
  currentModule: null,
  busy: false,
  loadError: "",
  lastSavedPct: 0,
};

function categoryMeta(category) {
  return CATEGORY_META[category] || { label: String(category || "Module").replaceAll("_", " "), icon: "book-open" };
}

function statusFor(moduleId) {
  return state.progress.get(moduleId) || null;
}

function statusChip(moduleId) {
  const row = statusFor(moduleId);
  if (!row) return "";
  if (row.status === "completed" || Number(row.badge_earned) === 1) {
    return '<span class="rchip rchip--success"><i data-lucide="award" class="h-3.5 w-3.5"></i> Badge</span>';
  }
  if (row.status === "in_progress") return '<span class="rchip rchip--sky">In progress</span>';
  return "";
}

function renderGrid() {
  const grid = document.getElementById("learn-grid");
  if (!grid) return;

  if (state.loadError) {
    grid.innerHTML = `
      <div class="rcard">
        <div class="rempty">
          <i data-lucide="wifi-off"></i>
          <p class="rempty-title">Could not load modules</p>
          <p class="rempty-text">${esc(state.loadError)}</p>
        </div>
      </div>`;
    createIcons({ icons });
    return;
  }

  if (!state.modules.length) {
    grid.innerHTML = `
      <div class="rcard">
        <div class="rempty">
          <i data-lucide="book-open"></i>
          <p class="rempty-title">No modules yet</p>
          <p class="rempty-text">Learning modules will appear here once the City Veterinary Office publishes them.</p>
        </div>
      </div>`;
    createIcons({ icons });
    return;
  }

  grid.innerHTML = state.modules
    .map((m) => {
      const meta = categoryMeta(m.category);
      const done = statusFor(m.id)?.status === "completed";
      const excerpt = String(m.content_body || "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
      return `
      <button type="button" class="rcard learn-card" data-module="${esc(m.id)}" aria-label="Open module: ${esc(m.title)}">
        <div class="learn-card-top">
          <span class="learn-card-icon"><i data-lucide="${esc(meta.icon)}"></i></span>
          ${statusChip(m.id)}
        </div>
        <span class="rchip rchip--brand">${esc(meta.label)}</span>
        <span class="learn-card-title">${esc(m.title)}</span>
        <p class="learn-card-desc">${esc(excerpt || "Read this short module to learn the basics.")}</p>
        <div class="learn-card-foot">
          <span class="learn-card-cta"><i data-lucide="${done ? "award" : "play"}"></i>${done ? "Read again" : "Start"}</span>
        </div>
      </button>`;
    })
    .join("");
  createIcons({ icons });
}

function renderProgress() {
  const chip = document.getElementById("learn-progress-chip");
  const fill = document.querySelector("#learn-progress-bar .learn-progress-fill");
  const bar = document.getElementById("learn-progress-bar");
  const note = document.getElementById("learn-progress-note");
  const total = state.modules.length;
  const badges = state.modules.filter((m) => {
    const row = statusFor(m.id);
    return row && (row.status === "completed" || Number(row.badge_earned) === 1);
  }).length;
  const reading = state.modules.reduce((sum, m) => {
    const row = statusFor(m.id);
    if (!row) return sum;
    if (row.status === "completed") return sum + 100;
    return sum + Math.max(0, Math.min(100, Number(row.read_pct) || 0));
  }, 0);
  const pct = total ? Math.round(reading / total) : 0;

  if (chip) chip.textContent = `${badges} of ${total} badges`;
  if (fill) fill.style.width = `${pct}%`;
  if (bar) bar.setAttribute("aria-valuenow", String(pct));
  if (note) {
    note.textContent = !total
      ? "No published modules yet."
      : badges === total
        ? "Every published lesson has a badge — nice work!"
        : "The bar fills as you read. A badge is awarded after the end-of-lesson quiz.";
  }
}

function setLessonReadBar(pct) {
  const fill = document.querySelector("#learn-lesson-progress .learn-progress-fill");
  const bar = document.getElementById("learn-lesson-progress");
  if (fill) fill.style.width = `${pct}%`;
  if (bar) bar.setAttribute("aria-valuenow", String(pct));
}

function showList() {
  state.currentId = null;
  state.currentModule = null;
  if (window.location.hash) history.replaceState(null, "", window.location.pathname);
  document.getElementById("learn-lesson-section")?.classList.add("is-hidden");
  document.getElementById("learn-list-section")?.classList.remove("is-hidden");
}

function applyLessonChrome(mod, done) {
  const meta = categoryMeta(mod.category);
  const titleEl = document.getElementById("learn-lesson-title");
  const catEl = document.getElementById("learn-lesson-category");
  const bodyEl = document.getElementById("learn-lesson-content");
  const statusEl = document.getElementById("learn-lesson-status");
  const hint = document.getElementById("learn-lesson-hint");
  if (titleEl) titleEl.textContent = mod.title;
  if (catEl) catEl.textContent = meta.label;
  if (bodyEl) bodyEl.innerHTML = String(mod.content_body || "");
  if (statusEl) {
    statusEl.textContent = done ? "Badge earned" : "In progress";
    statusEl.className = `rchip ${done ? "rchip--success" : "rchip--sky"}`;
    statusEl.hidden = false;
  }
  if (hint) {
    hint.textContent = done
      ? "You already earned this badge. You can still reread the lesson."
      : "Read to the end. The quiz unlocks the badge — no extra button to tap.";
  }
}

async function openLesson(moduleId) {
  bootstrapPageAuth();
  const section = document.getElementById("learn-lesson-section");
  const list = document.getElementById("learn-list-section");
  if (!section || !list) return;

  try {
    const data = await apiFetch(`/elearning/modules/${encodeURIComponent(moduleId)}`);
    const mod = data && data.module;
    if (!mod) throw new Error("Module not found");

    state.currentId = mod.id;
    state.currentModule = mod;
    const existing = statusFor(mod.id);
    if (existing == null) {
      state.progress.set(mod.id, { module_id: mod.id, status: "in_progress", read_pct: 0, completed_at: null });
      void apiFetch("/elearning/progress", {
        method: "POST",
        body: { module_id: mod.id, status: "in_progress", read_pct: 0 },
      }).catch(() => {});
    }

    const done = statusFor(mod.id)?.status === "completed";
    applyLessonChrome(mod, done);
    list.classList.add("is-hidden");
    section.classList.remove("is-hidden");

    const quiz = Array.isArray(mod.quiz) ? mod.quiz : [];
    renderQuiz(document.getElementById("learn-quiz"), quiz, {
      disabled: done,
      busy: state.busy,
      onSubmit: submitQuiz,
    });
    showBadge(document.getElementById("learn-badge"), mod.title);
    document.getElementById("learn-badge").hidden = !done;

    const startPct = done ? 100 : Math.max(0, Number(statusFor(mod.id)?.read_pct) || 0);
    state.lastSavedPct = startPct;
    setLessonReadBar(startPct);
    createIcons({ icons });
    renderProgress();
    window.scrollTo({ top: 0, behavior: "smooth" });
  } catch (err) {
    toast(err.message || "Could not load that module.", { type: "error" });
  }
}

async function submitQuiz(answers) {
  if (!state.currentId || state.busy) return;
  if (answers.some((n) => n < 0)) {
    toast("Answer every question first.", { type: "error" });
    return;
  }
  state.busy = true;
  try {
    const data = await apiFetch("/elearning/quiz", {
      method: "POST",
      body: { module_id: state.currentId, answers },
    });
    const result = data && data.result;
    const progress = data && data.progress;
    if (progress) state.progress.set(state.currentId, progress);
    if (result && result.passed) {
      applyLessonChrome(state.currentModule, true);
      showBadge(document.getElementById("learn-badge"), state.currentModule.title);
      renderQuiz(document.getElementById("learn-quiz"), state.currentModule.quiz || [], { disabled: true });
      setLessonReadBar(100);
      toast("Quiz passed — badge earned.", { type: "success" });
    } else {
      const score = result ? `${result.score}/${result.total}` : "";
      toast(`Not quite. You scored ${score}. Read again and retry.`, { type: "error" });
    }
  } catch (err) {
    toast(err.message || "Could not submit the quiz.", { type: "error" });
  } finally {
    state.busy = false;
    renderProgress();
    renderGrid();
  }
}

function onScroll() {
  if (!state.currentId || !state.currentModule) return;
  if (statusFor(state.currentId)?.status === "completed") return;
  const article = document.getElementById("learn-lesson-content");
  const pct = readingPercent(null, article);
  setLessonReadBar(pct);
  const row = statusFor(state.currentId) || { module_id: state.currentId, status: "in_progress" };
  row.read_pct = pct;
  state.progress.set(state.currentId, row);
  renderProgress();
  if (pct >= state.lastSavedPct + 10 || pct === 100) {
    state.lastSavedPct = pct;
    void saveReadPct(state.currentId, pct);
  }
}

function bindEvents() {
  const grid = document.getElementById("learn-grid");
  grid?.addEventListener("click", (e) => {
    const card = e.target.closest("[data-module]");
    if (!card) return;
    history.replaceState(null, "", `#${card.dataset.module}`);
    void openLesson(String(card.dataset.module));
  });

  document.getElementById("learn-back")?.addEventListener("click", () => {
    renderGrid();
    renderProgress();
    showList();
  });

  window.addEventListener("scroll", onScroll, { passive: true });
  window.addEventListener("hashchange", () => {
    const id = decodeURIComponent(window.location.hash.slice(1));
    if (id) void openLesson(id);
    else showList();
  });
}

async function boot() {
  bootstrapPageAuth();
  const user = requireAuth(PORTAL_ROLES);
  if (!user) return;
  initResidentShell();
  bindEvents();

  try {
    const modulesFull = await apiFetchFull("/elearning/modules?per_page=100");
    const items = (modulesFull && modulesFull.data) || [];
    state.modules = Array.isArray(items)
      ? items.filter((m) => m && m.published_status === "published")
      : [];
    state.loadError = "";
  } catch (err) {
    state.loadError = err.message || "Could not load modules.";
    toast(state.loadError, { type: "error" });
  }

  try {
    const progress = await apiFetch("/elearning/progress");
    const rows = (progress && progress.progress) || [];
    state.progress = new Map((Array.isArray(rows) ? rows : []).map((r) => [r.module_id, r]));
  } catch {
    toast("Saved progress is temporarily unavailable. You can still read modules.", {
      type: "error",
    });
  }

  renderGrid();
  renderProgress();

  const fromHash = decodeURIComponent(window.location.hash.slice(1));
  if (fromHash && state.modules.some((m) => m.id === fromHash)) {
    void openLesson(fromHash);
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    void boot();
  });
} else {
  void boot();
}
