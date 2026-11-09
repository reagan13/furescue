import { createIcons, icons } from "lucide";
import { esc } from "/assets/js/lib/format.js";

function openModal({ title, icon = "rotate-3d", wide = true }) {
  const overlay = document.createElement("div");
  overlay.className = "rmodal-overlay";
  overlay.innerHTML = `
    <div class="rmodal${wide ? " rmodal--wide" : ""}" role="dialog" aria-modal="true" aria-label="${esc(title)}">
      <div class="rmodal-head">
        <i data-lucide="${esc(icon)}" class="text-primary"></i>
        <h2 class="rmodal-title">${esc(title)}</h2>
        <button type="button" class="rmodal-x" aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <div class="rmodal-body" data-modal-body></div>
    </div>`;
  const host = document.querySelector(".resident-shell") || document.body;
  host.appendChild(overlay);
  createIcons({ icons });

  const close = () => {
    document.removeEventListener("keydown", onKey);
    overlay.remove();
  };
  const onKey = (e) => {
    if (e.key === "Escape") close();
  };
  document.addEventListener("keydown", onKey);
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) close();
  });
  overlay.querySelector(".rmodal-x").addEventListener("click", close);

  return { overlay, body: overlay.querySelector("[data-modal-body]"), close };
}

export async function openModelViewer(animal, options = {}) {
  const url = String((options && options.url) || animal.model_3d_url || "").trim();
  const name = animal.name || "this animal";
  const title = `3D model · ${name}`;
  const { overlay, body, close } = openModal({ title });
  if (!url) {
    body.innerHTML = `<p class="rmodel-hint">No 3D profile uploaded yet.</p>`;
    return;
  }
  body.innerHTML = `
    <div class="rmodel-stage" data-stage></div>
    <p class="rmodel-hint">Drag to rotate · scroll or pinch to zoom</p>`;

  const stage = body.querySelector("[data-stage]");
  let cleanup = null;

  try {
    const THREE = await import("three");
    const path = url.toLowerCase().split("?")[0];
    let object;

    if (path.endsWith(".glb") || path.endsWith(".gltf")) {
      const { GLTFLoader } = await import("three/addons/loaders/GLTFLoader.js");
      const gltf = await new GLTFLoader().loadAsync(url);
      object = gltf.scene;
    } else if (path.endsWith(".obj")) {
      const { OBJLoader } = await import("three/addons/loaders/OBJLoader.js");
      object = await new OBJLoader().loadAsync(url);
    } else {
      showFallback(body, name, url);
      return;
    }

    const width = stage.clientWidth || 640;
    const height = stage.clientHeight || 420;
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(width, height);
    stage.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const backdrop = tokenColor(THREE, stage, "--background", 0xf4f4f4);
    const floorColor = tokenColor(THREE, stage, "--surface-soft", 0xe6e6e6);
    scene.background = backdrop;
    scene.add(new THREE.AmbientLight(0xffffff, 0.9));
    const key = new THREE.DirectionalLight(0xffffff, 1.4);
    key.position.set(3, 5, 4);
    scene.add(key);
    const rim = new THREE.DirectionalLight(0x88bbff, 0.5);
    rim.position.set(-4, -2, -3);
    scene.add(rim);

    const box = new THREE.Box3().setFromObject(object);
    const size = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());
    const maxDim = Math.max(size.x, size.y, size.z) || 1;
    object.position.sub(center);
    const wrapper = new THREE.Group();
    wrapper.add(object);
    scene.add(wrapper);

    const ground = new THREE.Mesh(
      new THREE.CircleGeometry(Math.max(size.x, size.z, maxDim * 0.6) * 0.85, 48),
      new THREE.MeshStandardMaterial({ color: floorColor, roughness: 1, metalness: 0 })
    );
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -size.y / 2;
    scene.add(ground);

    const camera = new THREE.PerspectiveCamera(45, width / height, maxDim / 1000, maxDim * 20);
    camera.position.set(0, maxDim * 0.35, maxDim * 1.7);
    camera.lookAt(0, 0, 0);

    const { OrbitControls } = await import("three/addons/controls/OrbitControls.js");
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.minDistance = maxDim * 0.6;
    controls.maxDistance = maxDim * 6;

    let running = true;
    renderer.setAnimationLoop(() => {
      if (!running) return;
      controls.update();
      renderer.render(scene, camera);
    });

    const ro = new ResizeObserver(() => {
      const w = stage.clientWidth || width;
      const h = stage.clientHeight || height;
      camera.aspect = w / h;
      camera.updateProjectionMatrix();
      renderer.setSize(w, h);
    });
    ro.observe(stage);

    cleanup = () => {
      running = false;
      ro.disconnect();
      controls.dispose();
      ground.geometry.dispose();
      ground.material.dispose();
      renderer.setAnimationLoop(null);
      renderer.dispose();
    };
  } catch {
    showFallback(body, name, url);
  }

  // safety net: always stop the render loop when the dialog goes away
  const mo = new MutationObserver(() => {
    if (!document.body.contains(overlay)) {
      cleanup?.();
      mo.disconnect();
    }
  });
  mo.observe(document.body, { childList: true });
}

function tokenColor(THREE, el, token, fallbackHex) {
  const color = new THREE.Color(fallbackHex);
  const raw = getComputedStyle(el).getPropertyValue(token).trim();
  const parts = raw.match(/([\d.]+)\s+([\d.]+)%\s+([\d.]+)%/);
  if (!parts) return color;
  color.setHSL(Number(parts[1]) / 360, Number(parts[2]) / 100, Number(parts[3]) / 100);
  return color;
}

function showFallback(body, name, url) {
  body.innerHTML = `
    <div class="rempty">
      <i data-lucide="package-open"></i>
      <p class="rempty-title">Preview not available</p>
      <p class="rempty-text">The interactive 3D model for ${esc(name)} can't be displayed here, but it is available at the shelter.</p>
      <a class="rbtn rbtn--ghost" href="${esc(url)}" target="_blank" rel="noopener noreferrer">
        <i data-lucide="external-link"></i><span>Open model file</span>
      </a>
    </div>`;
  createIcons({ icons });
}

export function open360Viewer(animal) {
  const urls = parseSet(animal.photo_360_set);
  if (!urls.length) return;
  const name = animal.name || "this animal";
  const { body } = openModal({ title: `360° view · ${name}`, icon: "refresh-cw" });

  let frame = 0;
  body.innerHTML = `
    <div class="rspin-stage" data-stage></div>
    <input class="rspin-range" type="range" min="0" max="${urls.length - 1}" step="1" value="0" aria-label="Rotate view">
    <p class="rmodel-hint">${urls.length} views · drag the image or use the slider to spin</p>`;

  const stage = body.querySelector("[data-stage]");
  const range = body.querySelector(".rspin-range");
  const imgs = urls.map((src, i) => {
    const img = document.createElement("img");
    img.src = src;
    img.alt = `View ${i + 1} of ${esc(name)}`;
    img.loading = i === 0 ? "eager" : "lazy";
    img.hidden = i !== 0;
    stage.appendChild(img);
    return img;
  });

  const show = (index) => {
    frame = ((index % urls.length) + urls.length) % urls.length;
    imgs.forEach((img, i) => {
      img.hidden = i !== frame;
    });
    range.value = String(frame);
  };

  range.addEventListener("input", () => show(Number(range.value)));

  let dragging = false;
  let lastX = 0;
  let acc = 0;
  stage.addEventListener("pointerdown", (e) => {
    dragging = true;
    lastX = e.clientX;
    acc = 0;
    stage.classList.add("is-dragging");
    stage.setPointerCapture(e.pointerId);
  });
  stage.addEventListener("pointermove", (e) => {
    if (!dragging) return;
    acc += e.clientX - lastX;
    lastX = e.clientX;
    const step = Math.max(8, stage.clientWidth / 60);
    while (Math.abs(acc) >= step) {
      show(frame + (acc > 0 ? 1 : -1));
      acc -= acc > 0 ? step : -step;
    }
  });
  const endDrag = () => {
    dragging = false;
    stage.classList.remove("is-dragging");
  };
  stage.addEventListener("pointerup", endDrag);
  stage.addEventListener("pointercancel", endDrag);
}

function parseSet(value) {
  if (!value) return [];
  let parsed = value;
  if (typeof parsed === "string") {
    try {
      parsed = JSON.parse(parsed);
    } catch {
      return [];
    }
  }
  return Array.isArray(parsed) ? parsed.filter((u) => typeof u === "string" && u.trim() !== "") : [];
}
