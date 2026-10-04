import { createIcons, icons } from "lucide";
import { toast } from "/shared/components/toast/toast.js";

const MAX_FILES = 8;
const MAX_FILE_SIZE = 10 * 1024 * 1024;

const el = (id) => document.getElementById(id);

let selectedFiles = [];
let onFilesChange = () => {};

export function getSelectedFiles() {
  return selectedFiles;
}

export function renderSelectedFiles() {
  const list = el("photo-list");
  if (!list) return;
  list.innerHTML = "";
  selectedFiles.forEach((file, i) => {
    const li = document.createElement("li");
    li.className =
      "flex items-center gap-2 rounded-md border border-input bg-background px-2.5 py-1.5 text-xs";
    const name = document.createElement("span");
    name.className = "min-w-0 flex-1 truncate font-semibold";
    name.textContent = file.name;
    const size = document.createElement("span");
    size.className = "shrink-0 tabular-nums text-muted-foreground";
    size.textContent = `${(file.size / 1024 / 1024).toFixed(1)} MB`;
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className =
      "inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive";
    remove.setAttribute("aria-label", `Remove ${file.name}`);
    remove.innerHTML = '<i data-lucide="x" class="h-3.5 w-3.5"></i>';
    remove.addEventListener("click", () => {
      selectedFiles.splice(i, 1);
      renderSelectedFiles();
      onFilesChange();
    });
    li.append(name, size, remove);
    list.appendChild(li);
  });
  createIcons({ icons });
}

export function initPhotoInput(options = {}) {
  const input = el("report-photos");
  if (!input) return;
  if (typeof options.onChange === "function") {
    onFilesChange = options.onChange;
  }
  input.addEventListener("change", () => {
    for (const file of Array.from(input.files || [])) {
      if (selectedFiles.length >= MAX_FILES) {
        toast(`Up to ${MAX_FILES} files allowed.`, { type: "error" });
        break;
      }
      if (file.size > MAX_FILE_SIZE) {
        toast(`"${file.name}" is larger than 10 MB.`, { type: "error" });
        continue;
      }
      if (selectedFiles.some((f) => f.name === file.name && f.size === file.size)) continue;
      selectedFiles.push(file);
    }
    input.value = "";
    renderSelectedFiles();
    onFilesChange();
  });
}
