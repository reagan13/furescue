/**
 * Scan markup/JS for Lucide icon names, then write a local ESM subset
 * with createIcons + only those icons. Pages keep importing from
 * "lucide"; the import map points at the generated file.
 *
 * Run: npm run build:lucide
 */
import { mkdir, readdir, readFile, writeFile, stat } from "node:fs/promises";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const lucideEsm = join(root, "node_modules/lucide/dist/esm");
const outFile = join(root, "public/assets/js/vendor/lucide.js");
const phpOutFile = join(root, "public/shared/components/icons/icons.php");

const SCAN_DIRS = ["public", "views"];
const SCAN_EXT = new Set([".js", ".php", ".html"]);
const SKIP_DIR = new Set(["node_modules", "vendor", "uploads"]);

/** Names used in markup that Lucide 0.469 ships under a different file. */
const ALIASES = {
  "venus-mars": "users",
};

/** Icons referenced only via variables / ternaries. */
const EXTRA = [
  "alert-triangle",
  "bell",
  "bell-off",
  "bird",
  "calendar-check",
  "cat",
  "check-circle-2",
  "circle",
  "circle-dot",
  "clock",
  "dog",
  "eye",
  "eye-off",
  "file-plus-2",
  "file-text",
  "flag",
  "heart",
  "heart-handshake",
  "list-checks",
  "maximize",
  "message-square-plus",
  "minimize",
  "paw-print",
  "play",
  "rabbit",
  "rotate-ccw",
  "search-x",
  "shield-question",
  "siren",
  "sticky-note",
  "triangle-alert",
  "venus-mars",
  "wifi-off",
];

const NAME_RES = [
  /data-lucide=["']([a-z][a-z0-9-]*)["']/g,
  /data-lucide=["']\$\{[^}]*["']([a-z][a-z0-9-]*)["']/g,
  /icon:\s*["']([a-z][a-z0-9-]*)["']/g,
  /['"]icon['"]\s*=>\s*['"]([a-z][a-z0-9-]*)['"]/g,
];

function toPascalCase(string) {
  return string.replace(/(\w)(\w*)(_|-|\s*)/g, (_g0, g1, g2) => g1.toUpperCase() + g2.toLowerCase());
}

async function walk(dir, files = []) {
  let entries;
  try {
    entries = await readdir(dir, { withFileTypes: true });
  } catch {
    return files;
  }
  for (const entry of entries) {
    if (SKIP_DIR.has(entry.name) || entry.name.startsWith(".")) continue;
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      await walk(full, files);
      continue;
    }
    const ext = entry.name.slice(entry.name.lastIndexOf(".")).toLowerCase();
    if (SCAN_EXT.has(ext)) files.push(full);
  }
  return files;
}

function collectNames(source) {
  const names = new Set();
  for (const re of NAME_RES) {
    re.lastIndex = 0;
    let match;
    while ((match = re.exec(source))) names.add(match[1]);
  }
  return names;
}

async function loadLucideExports() {
  const source = await readFile(join(lucideEsm, "lucide.js"), "utf8");
  const byPascal = new Map();
  const re = /export\s*\{([^}]+)\}\s*from\s*'\.\/icons\/([^']+)\.js'/g;
  let match;
  while ((match = re.exec(source))) {
    const file = match[2];
    const names = [...match[1].matchAll(/default as ([A-Za-z0-9]+)/g)].map((m) => m[1]);
    for (const pascal of names) byPascal.set(pascal, file);
  }
  return byPascal;
}

function extractIconArray(source) {
  const start = source.indexOf("const ");
  const eq = source.indexOf(" = [", start);
  const end = source.lastIndexOf("];");
  if (start < 0 || eq < 0 || end < 0) return null;
  const name = source.slice(start + 6, eq).trim();
  let body = source.slice(eq + 3, end + 1);
  body = body.replace(/\bdefaultAttributes\b/g, "defaultAttributes");
  return { name, body };
}

const files = [];
for (const dir of SCAN_DIRS) {
  await walk(join(root, dir), files);
}

const kebabNames = new Set(EXTRA);
for (const file of files) {
  if (file.replaceAll("\\", "/").endsWith("assets/js/vendor/lucide.js")) continue;
  const source = await readFile(file, "utf8");
  for (const name of collectNames(source)) kebabNames.add(name);
}

const byPascal = await loadLucideExports();
const neededFiles = new Map();
const missing = [];

for (const kebab of kebabNames) {
  const pascal = toPascalCase(kebab);
  const file = byPascal.get(pascal) || byPascal.get(toPascalCase(ALIASES[kebab] || ""));
  if (!file) {
    missing.push(kebab);
    continue;
  }
  if (!neededFiles.has(file)) neededFiles.set(file, new Set());
  neededFiles.get(file).add(pascal);
  if (ALIASES[kebab]) neededFiles.get(file).add(toPascalCase(ALIASES[kebab]));
}

if (!neededFiles.size) {
  throw new Error("No Lucide icons resolved from the codebase scan.");
}

for (const [file, names] of neededFiles) {
  for (const [pascal, src] of byPascal) {
    if (src === file) names.add(pascal);
  }
}

const runtime = `
const defaultAttributes = {
  xmlns: "http://www.w3.org/2000/svg",
  width: 24,
  height: 24,
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": 2,
  "stroke-linecap": "round",
  "stroke-linejoin": "round"
};

const createElement = (tag, attrs, children = []) => {
  const element = document.createElementNS("http://www.w3.org/2000/svg", tag);
  Object.keys(attrs).forEach((name) => {
    element.setAttribute(name, String(attrs[name]));
  });
  if (children.length) {
    children.forEach((child) => {
      const childElement = createElement(...child);
      element.appendChild(childElement);
    });
  }
  return element;
};
const createSvg = ([tag, attrs, children]) => createElement(tag, attrs, children);

const getAttrs = (element) => Array.from(element.attributes).reduce((attrs, attr) => {
  attrs[attr.name] = attr.value;
  return attrs;
}, {});
const getClassNames = (attrs) => {
  if (typeof attrs === "string") return attrs;
  if (!attrs || !attrs.class) return "";
  if (typeof attrs.class === "string") return attrs.class.split(" ");
  if (Array.isArray(attrs.class)) return attrs.class;
  return "";
};
const combineClassNames = (arrayOfClassnames) => {
  const classNameArray = arrayOfClassnames.flatMap(getClassNames);
  return classNameArray.map((classItem) => classItem.trim()).filter(Boolean)
    .filter((value, index, self) => self.indexOf(value) === index).join(" ");
};
const toPascalCase = (string) => string.replace(/(\\w)(\\w*)(_|-|\\s*)/g, (g0, g1, g2) => g1.toUpperCase() + g2.toLowerCase());
const replaceElement = (element, { nameAttr, icons, attrs }) => {
  const iconName = element.getAttribute(nameAttr);
  if (iconName == null) return;
  const ComponentName = toPascalCase(iconName);
  const iconNode = icons[ComponentName];
  if (!iconNode) {
    return console.warn(element.outerHTML + " icon name was not found in the provided icons object.");
  }
  const elementAttrs = getAttrs(element);
  const [tag, iconAttributes, children] = iconNode;
  const iconAttrs = { ...iconAttributes, "data-lucide": iconName, ...attrs, ...elementAttrs };
  const classNames = combineClassNames(["lucide", "lucide-" + iconName, elementAttrs, attrs]);
  if (classNames) Object.assign(iconAttrs, { class: classNames });
  const svgElement = createSvg([tag, iconAttrs, children]);
  return element.parentNode && element.parentNode.replaceChild(svgElement, element);
};

const createIcons = ({ icons = {}, nameAttr = "data-lucide", attrs = {} } = {}) => {
  if (!Object.values(icons).length) {
    throw new Error("Please provide an icons object.");
  }
  if (typeof document === "undefined") {
    throw new Error("\`createIcons()\` only works in a browser environment.");
  }
  Array.from(document.querySelectorAll("[" + nameAttr + "]")).forEach((element) => {
    if (element.tagName && element.tagName.toLowerCase() === "svg") return;
    replaceElement(element, { nameAttr, icons, attrs });
  });
};

export { createIcons };
`.trim();

const iconBlocks = [];
const iconEntries = [];

for (const [file, pascals] of [...neededFiles.entries()].sort((a, b) => a[0].localeCompare(b[0]))) {
  const source = await readFile(join(lucideEsm, "icons", `${file}.js`), "utf8");
  const extracted = extractIconArray(source);
  if (!extracted) {
    missing.push(file);
    continue;
  }
  iconBlocks.push(`const ${extracted.name} = ${extracted.body};`);
  for (const pascal of [...pascals].sort()) {
    iconEntries.push(
      pascal === extracted.name ? `  ${pascal}` : `  ${pascal}: ${extracted.name}`
    );
  }
}

const banner = `/* Lucide subset generated by scripts/build-lucide.mjs — ${neededFiles.size} icons. Do not edit by hand. */\n`;
const output = `${banner}${runtime}

${iconBlocks.join("\n")}

export const icons = {
${iconEntries.join(",\n")},
};
`;

function iconChildren(source) {
  const extracted = extractIconArray(source);
  if (!extracted) return [];
  try {
    const node = new Function("defaultAttributes", `return ${extracted.body}`)({});
    return Array.isArray(node?.[2]) ? node[2] : [];
  } catch {
    return [];
  }
}

function nodeToHtml(node) {
  if (!Array.isArray(node)) return "";
  const [tag, attrs = {}, kids = []] = node;
  const attrHtml = Object.entries(attrs)
    .filter(([key]) => key !== "key")
    .map(([key, value]) => `${key}="${String(value).replace(/&/g, "&amp;").replace(/"/g, "&quot;")}"`)
    .join(" ");
  const inner = (Array.isArray(kids) ? kids : []).map(nodeToHtml).join("");
  const open = attrHtml ? `<${tag} ${attrHtml}>` : `<${tag}>`;
  return inner ? `${open}${inner}</${tag}>` : `${open.slice(0, -1)}/>`;
}

function phpString(value) {
  return `'${String(value).replace(/\\/g, "\\\\").replace(/'/g, "\\'")}'`;
}

const phpIcons = [];
for (const kebab of [...kebabNames].sort()) {
  const pascal = toPascalCase(kebab);
  const file = byPascal.get(pascal) || byPascal.get(toPascalCase(ALIASES[kebab] || ""));
  if (!file) continue;
  const source = await readFile(join(lucideEsm, "icons", `${file}.js`), "utf8");
  const inner = iconChildren(source).map(nodeToHtml).join("");
  if (!inner) continue;
  phpIcons.push(`        ${phpString(kebab)} => ${phpString(inner)},`);
}

const phpOutput = `<?php

declare(strict_types=1);

/**
 * Generated by scripts/build-lucide.mjs — do not edit by hand.
 * Server-side Lucide subset so chrome icons paint with the HTML.
 */

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

if (!function_exists('lucide_icon')) {
    function lucide_icon(string $name, string $class = '', string $attrs = ''): string
    {
        static $icons = null;
        $icons ??= lucide_icon_inner();
        $inner = $icons[$name] ?? '';
        if ($inner === '') {
            return '';
        }
        $className = trim('lucide lucide-' . $name . ' ' . $class);
        $extra = $attrs !== '' ? ' ' . $attrs : '';
        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="'
            . htmlspecialchars($className, ENT_QUOTES, 'UTF-8')
            . '" aria-hidden="true"' . $extra . '>' . $inner . '</svg>';
    }
}

if (!function_exists('lucide_icon_inner')) {
    function lucide_icon_inner(): array
    {
        return [
${phpIcons.join("\n")}
        ];
    }
}
`;

await mkdir(dirname(outFile), { recursive: true });
await writeFile(outFile, output);
await mkdir(dirname(phpOutFile), { recursive: true });
await writeFile(phpOutFile, phpOutput);

const { size } = await stat(outFile);
const kb = (size / 1024).toFixed(1);
const rel = relative(root, outFile).replaceAll("\\", "/");
const phpRel = relative(root, phpOutFile).replaceAll("\\", "/");
console.log(`Lucide subset: ${neededFiles.size} icons → ${rel} (${kb} KB)`);
console.log(`Lucide PHP helper: ${phpIcons.length} icons → ${phpRel}`);
if (missing.length) {
  console.warn(`Skipped unknown icon names: ${missing.join(", ")}`);
}
