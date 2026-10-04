import { Badge } from "/shared/components/badge/badge.js";
import { Button } from "/shared/components/button/button.js";
import { esc, photos } from "./util.js";

const ATTACH_ACCEPT = "image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm";
const PROOF_ACCEPT = "image/jpeg,image/png,image/webp";

function uploadForm({ kind, accept, hint }) {
  return `
    <form class="cd-upload" data-cd-upload="${esc(kind)}" enctype="multipart/form-data">
      <p class="cd-upload-hint">${esc(hint)}</p>
      <div class="cd-upload-row">
        <input class="input" type="file" name="files[]" accept="${esc(accept)}" multiple>
        ${Button({ text: "Upload", variant: "outline", size: "sm", icon: "upload", type: "submit" })}
      </div>
      <p class="cd-upload-names" hidden></p>
      <p class="cd-upload-error" hidden></p>
    </form>`;
}

export function renderAttachments(caseData) {
  const files = photos(caseData.report && caseData.report.photo_urls);
  const canUpload = caseData.status !== "resolved" && !!(caseData.report_id || (caseData.report && caseData.report.id));
  const gallery = files.length
    ? `<div class="cd-files">${
        files
          .map(
            (f) =>
              `<a class="cd-file" href="${esc(f)}" target="_blank" rel="noopener"><img src="${esc(f)}" alt="Case attachment" loading="lazy"></a>`
          )
          .join("")}</div>`
    : `<div class="empty-state"><i data-lucide="image-off"></i><span>${
        canUpload ? "Upload at least one attached file. Required before resolve." : "No attachments submitted."
      }</span></div>`;
  const form = canUpload
    ? uploadForm({
        kind: "attachments",
        accept: ATTACH_ACCEPT,
        hint: "JPG, PNG, GIF, WEBP, MP4, or WEBM. Up to 10 MB each.",
      })
    : "";
  return `
    <div class="panel case-detail-panel">
      <div class="panel-head">
        <div class="panel-title-wrap">
          <i data-lucide="paperclip"></i>
          <h2 class="panel-title">Attached files</h2>
        </div>
        ${files.length ? `<span class="stamp stamp--sm stamp--muted">${files.length}</span>` : ""}
      </div>
      <div class="panel-body">${gallery}${form}</div>
    </div>`;
}

export function renderProof(caseData) {
  const proof = photos(caseData.resolution_photos);
  const canUpload = caseData.status === "in_progress";
  const gallery = proof.length
    ? `<div class="cd-files">${
        proof
          .map(
            (p) =>
              `<a class="cd-file" href="${esc(p)}" target="_blank" rel="noopener"><img src="${esc(p)}" alt="Rescue proof" loading="lazy"></a>`
          )
          .join("")}</div>`
    : `<div class="empty-state"><i data-lucide="image-off"></i><span>${
        canUpload ? "Upload rescue proof. Required before resolve." : "No rescue proof uploaded."
      }</span></div>`;
  const form = canUpload
    ? uploadForm({
        kind: "proof",
        accept: PROOF_ACCEPT,
        hint: "JPG, PNG, or WEBP. Up to 5 MB each.",
      })
    : "";
  return `
    <div class="panel case-detail-panel">
      <div class="panel-head">
        <div class="panel-title-wrap">
          <i data-lucide="camera"></i>
          <h2 class="panel-title">Rescue proof</h2>
        </div>
        ${proof.length ? `<div class="cd-rescuer-meta">${Badge({ text: caseData.rescuer_name || "Rescuer", variant: "secondary", icon: "user" })}</div>` : ""}
      </div>
      <div class="panel-body">${gallery}${form}</div>
    </div>`;
}
