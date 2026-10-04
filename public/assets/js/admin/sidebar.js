
export function NavItem(item, map, activeNav) {
  const override = map && item.badgeKey ? map[item.badgeKey] : undefined;
  const value = override !== undefined ? override : item.badge;
  const badge = value
    ? `<span class="stamp stamp--sm sidebar-badge ${item.badgeCls}">${value}</span>`
    : "";
  const isActive = (activeNav || "dashboard") === item.key;
  const tone = isActive ? " sidebar-link--active" : "";
  return `
    <a href="${item.href || "#"}" class="sidebar-link${tone}">
      <i data-lucide="${item.icon}"></i> <span>${item.label}</span>
      ${badge}
    </a>`;
}

export function Sidebar({ groups = [] } = {}) {
  const map = {};
  const out = groups
    .map(
      (g) => `
    <div class="sidebar-group">
      <div class="sidebar-label">${g.label}</div>
      <div class="sidebar-links">${g.items.map((i) => NavItem(i, map)).join("")}</div>
    </div>`
    )
    .join("");
  return `
  <aside id="sidebar" class="sidebar">
    <div class="sidebar-head">
      <a href="/admin/" class="sidebar-brand-link">
        <div class="sidebar-logo"><i data-lucide="paw-print"></i></div>
        <div>
          <div class="sidebar-brand">FurEscue</div>
          <div class="sidebar-tag">Admin Console</div>
        </div>
      </a>
    </div>
    <nav class="sidebar-nav">
      ${out}
    </nav>
  </aside>`;
}
