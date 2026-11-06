import { Button } from "/shared/components/button/button.js";
import { state } from "../state.js";
import { CATEGORIES, esc, statusLabel } from "./util.js";

function categoryOptions(selected) {
  return CATEGORIES.map(
    (c) =>
      `<option value="${esc(c.key)}"${c.key === selected ? " selected" : ""}>${esc(c.label)}</option>`
  ).join("");
}

export function Editor() {
  const m = state.editor || {};
  const isNew = !m.id;
  const title = m.title || "";
  const body = m.content_body || "";
  const quiz = Array.isArray(m.quiz_json) ? JSON.stringify(m.quiz_json, null, 2) : m.quiz_json || "";
  const status = m.published_status === "published" ? "published" : "draft";
  const published = status === "published";
  const toggle = isNew
    ? ""
    : published
      ? Button({
          text: "Unpublish",
          variant: "outline",
          icon: "eye-off",
          attrs: `data-action="unpublish" data-id="${esc(m.id)}"`,
        })
      : Button({
          text: "Publish",
          variant: "default",
          icon: "upload",
          attrs: `data-action="publish" data-id="${esc(m.id)}"`,
        });

  return `
  <div class="page-head">
    <div>
      <span class="stamp stamp--coral">Content</span>
      <h1 class="page-title">${isNew ? "New module" : "Edit module"}</h1>
      <p class="page-sub">${isNew ? "Draft a lesson for the resident Learning Hub. HTML is allowed in the body." : "Update the lesson residents will see. HTML is allowed in the body."}</p>
    </div>
    <div class="page-head-actions">
      ${Button({ text: "Back to library", variant: "outline", icon: "arrow-left", attrs: 'data-action="cancel"' })}
    </div>
  </div>
  <div class="panel panel--padded">
    <div class="panel-title-wrap">
      <i data-lucide="file-text"></i>
      <h2 class="panel-title">${esc(isNew ? "Module details" : title || "Untitled")}</h2>
      ${isNew ? "" : `<span class="stamp stamp--sm ${published ? "stamp--accent" : "stamp--muted"}">${statusLabel(status)}</span>`}
    </div>
    <form id="elearn-form" class="elearn-form" novalidate>
      <div class="elearn-form-grid">
        <div class="field elearn-field--full">
          <label class="field-label" for="elearn-title">Title</label>
          <input id="elearn-title" name="title" type="text" class="input" maxlength="150" required value="${esc(title)}" placeholder="e.g. Loose leash walking">
          <span class="field-hint"><span id="elearn-title-count">${title.length}</span>/150 characters</span>
        </div>
        <div class="field">
          <label class="field-label" for="elearn-category">Category</label>
          <select id="elearn-category" name="category" class="input" required>
            ${categoryOptions(m.category || "general_care")}
          </select>
        </div>
        <div class="field">
          <label class="field-label" for="elearn-status">Status</label>
          <select id="elearn-status" name="published_status" class="input">
            <option value="draft"${status === "draft" ? " selected" : ""}>Draft</option>
            <option value="published"${status === "published" ? " selected" : ""}>Published</option>
          </select>
          <span class="field-hint">Published modules appear on resident /learning/.</span>
        </div>
        <div class="field elearn-field--full">
          <label class="field-label" for="elearn-body">Content body</label>
          <textarea id="elearn-body" name="content_body" class="input input--area" rows="16" maxlength="20000" required placeholder="<p>Lesson HTML…</p>">${esc(body)}</textarea>
          <span class="field-hint"><span id="elearn-body-count">${body.length}</span>/20000 characters. HTML is allowed — residents render this as the lesson.</span>
        </div>
        <div class="field elearn-field--full">
          <label for="elearn-quiz" class="field-label">Quiz JSON</label>
          <textarea id="elearn-quiz" name="quiz_json" class="input input--area" rows="8" placeholder='[{"q":"Question","options":["A","B"],"a":0}]'>${esc(quiz)}</textarea>
          <p class="field-hint">A list of questions. Each one has q, options, and a (the correct option index). Leave blank for no quiz.</p>
        </div>
      </div>
      <div class="elearn-form-actions">
        ${Button({ text: isNew ? "Create module" : "Save changes", variant: "default", icon: "save", attrs: 'data-action="save"' })}
        ${toggle}
        ${Button({ text: "Cancel", variant: "outline", attrs: 'data-action="cancel"' })}
      </div>
    </form>
  </div>`;
}

export function readEditorForm() {
  const titleEl = document.getElementById("elearn-title");
  const catEl = document.getElementById("elearn-category");
  const statusEl = document.getElementById("elearn-status");
  const bodyEl = document.getElementById("elearn-body");
  const quizEl = document.getElementById("elearn-quiz");
  return {
    title: titleEl ? titleEl.value.trim() : "",
    category: catEl ? catEl.value : "general_care",
    published_status: statusEl && statusEl.value === "published" ? "published" : "draft",
    content_body: bodyEl ? bodyEl.value : "",
    quiz_json: quizEl ? quizEl.value.trim() : "",
  };
}
