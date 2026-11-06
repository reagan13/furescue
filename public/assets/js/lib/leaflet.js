const LEAFLET_CSS = [
  "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css",
  "https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css",
];
const LEAFLET_JS = [
  "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js",
  "https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js",
];
const LEAFLET_HEAT = "https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js";
const OSM_TILES = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
const OSM_ATTR = "&copy; OpenStreetMap contributors";

let leafletPromise = null;
let heatPromise = null;

function hasStylesheet(hrefs) {
  return [...document.querySelectorAll("link[rel='stylesheet']")].some((link) =>
    hrefs.some((href) => link.href.includes("leaflet") || link.href === href)
  );
}

function loadStylesheet(href) {
  if (hasStylesheet([href])) return;
  const link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = href;
  document.head.appendChild(link);
}

function loadScript(src) {
  const existing = [...document.querySelectorAll("script")].find((s) => s.src === src);
  if (existing) {
    return existing.dataset.loaded === "1"
      ? Promise.resolve()
      : new Promise((resolve, reject) => {
          existing.addEventListener("load", () => resolve(), { once: true });
          existing.addEventListener("error", () => reject(new Error(`Failed to load ${src}`)), { once: true });
        });
  }
  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = src;
    script.async = true;
    script.onload = () => {
      script.dataset.loaded = "1";
      resolve();
    };
    script.onerror = () => reject(new Error(`Failed to load ${src}`));
    document.head.appendChild(script);
  });
}

async function loadFirst(urls) {
  for (const src of urls) {
    try {
      await loadScript(src);
      return true;
    } catch {
      /* try next CDN */
    }
  }
  return false;
}

export function ensureLeaflet({ heat = false } = {}) {
  if (!leafletPromise) {
    leafletPromise = (async () => {
      if (window.L) return true;
      loadStylesheet(LEAFLET_CSS[0]);
      const ok = await loadFirst(LEAFLET_JS);
      return Boolean(ok && window.L);
    })();
  }
  if (!heat) return leafletPromise;

  if (!heatPromise) {
    heatPromise = leafletPromise.then(async (ok) => {
      if (!ok) return false;
      if (window.L.heatLayer) return true;
      try {
        await loadScript(LEAFLET_HEAT);
      } catch {
        console.warn("leaflet.heat failed to load");
        return false;
      }
      return Boolean(window.L.heatLayer);
    });
  }
  return heatPromise;
}

export async function mountPinMap(el, lat, lng, options = {}) {
  const ready = await ensureLeaflet();
  if (!ready || !el || !window.L || !Number.isFinite(lat) || !Number.isFinite(lng)) return null;

  const interactive = options.interactive !== false;
  const map = window.L.map(
    el,
    interactive
      ? { scrollWheelZoom: false }
      : {
          scrollWheelZoom: false,
          dragging: false,
          touchZoom: false,
          doubleClickZoom: false,
          zoomControl: false,
          attributionControl: false,
        }
  ).setView([lat, lng], options.zoom ?? 15);

  window.L.tileLayer(OSM_TILES, { attribution: OSM_ATTR, maxZoom: 18 }).addTo(map);
  const marker = window.L.marker([lat, lng]).addTo(map);
  if (options.popup) {
    const maxWidth = Number.isFinite(options.popupMaxWidth) ? options.popupMaxWidth : 300;
    marker.bindPopup(options.popup, { maxWidth });
  }
  window.setTimeout(() => map.invalidateSize(), options.invalidateDelay ?? 300);
  return { map, marker };
}
