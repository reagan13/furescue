import { createIcons, icons } from "lucide";
import { initShell } from "/assets/js/admin/app-shell.js";
import { initDatePicker } from "/shared/components/date-picker/date-picker.js";
import { initSelect } from "/shared/components/select/select.js";
import { fetchAnimalHealthRecord } from "/assets/js/admin/admin-data.js";

export let user = null;
export let record = null;
export let ui = {
  editing: false,
  mode: null,
  openForm: null,
  saving: false,
  vaxSelecting: false,
  addedVaccination: false,
  addedVital: false,
};

export function setSession(nextUser, nextRecord, nextUi) {
  user = nextUser;
  record = nextRecord || null;
  if (nextUi) Object.assign(ui, nextUi);
}

export function setRecord(next) {
  record = next;
}

export function syncHidden(id, val) {
  const el = document.getElementById(`${id}-value`);
  if (el) el.value = val;
}

let pageHtml = () => "";
let afterReload = () => {};

export function setPageHtml(fn) {
  pageHtml = fn;
}

export function setAfterReload(fn) {
  afterReload = fn;
}

const SELECT_HANDLERS = {
  "hr-adoption-status": (val) => syncHidden("hr-adoption-status", val),
  "hr-deworming": (val) => syncHidden("hr-deworming", val),
  "hr-neutered": (val) => syncHidden("hr-neutered", val),
};

function mainHtml(html) {
  const holder = document.createElement("template");
  holder.innerHTML = html;
  const nextMain = holder.content.querySelector(".admin-main");
  return nextMain ? nextMain.innerHTML : html;
}

export function paint() {
  const app = document.getElementById("app");
  if (!app) return;
  let html;
  try {
    html = pageHtml();
  } catch (err) {
    console.error("health-record paint failed", err);
    return;
  }
  const main = app.querySelector(".admin-shell .admin-main");
  if (main) {
    main.innerHTML = mainHtml(html);
    createIcons({ icons });
    initDatePicker(main);
    initSelect(main, SELECT_HANDLERS);
    return;
  }
  app.innerHTML = html;
  createIcons({ icons });
  initShell();
  initDatePicker(app);
  initSelect(app, SELECT_HANDLERS);
}

export async function reloadRecord() {
  const id = record && record.id;
  if (!id) return;
  const fresh = await fetchAnimalHealthRecord(id);
  if (fresh) record = fresh;
  paint();
  afterReload();
}
