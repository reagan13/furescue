import { createIcons, icons } from "lucide";
import { apiFetch } from "/assets/js/lib/api.js";
import { toast } from "/shared/components/toast/toast.js";
import { esc } from "/assets/js/lib/format.js";

export function readingPercent(scroller, article) {
  if (!article) return 0;
  const root = scroller || document.documentElement;
  const top = article.getBoundingClientRect().top;
  const start = (scroller ? scroller.scrollTop : window.scrollY) + top;
  const usable = article.offsetHeight - (scroller ? scroller.clientHeight : window.innerHeight) * 0.45;
  if (usable <= 0) return 100;
  const travelled = (scroller ? scroller.scrollTop : window.scrollY) - start + (scroller ? 0 : 0);
  const fromDoc = article.getBoundingClientRect().top * -1 + 80;
  const pct = Math.round((fromDoc / usable) * 100);
  return Math.max(0, Math.min(100, pct));
}

export function renderQuiz(host, questions, { disabled, onSubmit, busy } = {}) {
  if (!host) return;
  if (!questions.length) {
    host.innerHTML = "";
    host.hidden = true;
    return;
  }
  host.hidden = false;
  host.innerHTML = `
    <h3 class="learn-quiz-title"><i data-lucide="list-checks"></i> Lesson quiz</h3>
    <p class="learn-quiz-lead">Answer every question from this lesson. A perfect score completes the module and awards the badge.</p>
    <form id="learn-quiz-form" class="learn-quiz-form">
      ${questions
        .map(
          (q, qi) => `
        <fieldset class="learn-quiz-q" ${disabled ? "disabled" : ""}>
          <legend>${esc(q.q)}</legend>
          ${q.options
            .map(
              (opt, oi) => `
            <label class="learn-quiz-opt">
              <input type="radio" name="q${qi}" value="${oi}" required>
              <span>${esc(opt)}</span>
            </label>`
            )
            .join("")}
        </fieldset>`
        )
        .join("")}
      ${
        disabled
          ? ""
          : `<button type="submit" class="rbtn rbtn--solid" id="learn-quiz-submit" ${busy ? "disabled" : ""}>
              <i data-lucide="award"></i><span>Submit quiz</span>
            </button>`
      }
    </form>`;
  createIcons({ icons });
  host.querySelector("#learn-quiz-form")?.addEventListener("submit", (event) => {
    event.preventDefault();
    if (typeof onSubmit !== "function") return;
    const answers = questions.map((_, i) => {
      const picked = host.querySelector(`input[name="q${i}"]:checked`);
      return picked ? Number(picked.value) : -1;
    });
    onSubmit(answers);
  });
}

export async function saveReadPct(moduleId, pct) {
  try {
    await apiFetch("/elearning/progress", {
      method: "POST",
      body: { module_id: moduleId, status: "in_progress", read_pct: pct },
    });
  } catch {
    /* progress write is best-effort */
  }
}

export function showBadge(host, title) {
  if (!host) return;
  host.hidden = false;
  host.innerHTML = `
    <div class="learn-badge">
      <span class="learn-badge-icon"><i data-lucide="award"></i></span>
      <div>
        <p class="learn-badge-title">Badge earned</p>
        <p class="learn-badge-sub">${esc(title)} is complete.</p>
      </div>
    </div>`;
  createIcons({ icons });
}
