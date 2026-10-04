import { DropdownMenu } from "/shared/components/dropdown-menu/dropdown-menu.js";

function initialsFrom(name) {
  const parts = String(name || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2);
  return parts.map((part) => part.slice(0, 1).toUpperCase()).join("") || "?";
}

export function Topbar({ user } = {}) {
  const photo = String(user?.profile_photo_url || "").trim();
  const initials = initialsFrom(user?.full_name);
  const profileMenu = DropdownMenu({
    id: "profile-menu",
    align: "right",
    trigger: `
    <button type="button" data-dropdown-trigger class="topbar-user" aria-haspopup="menu" aria-expanded="false" aria-label="Admin menu">
      <img src="${photo}" alt="" data-shell-avatar${photo ? "" : " hidden"}>
      <span class="topbar-user-fallback" data-shell-avatar-fallback${photo ? " hidden" : ""}>${initials}</span>
    </button>`,
    items: [
      { type: "label", text: "Insights" },
      { type: "item", icon: "bar-chart-3", label: "Analytics", href: "/admin/analytics/" },
      { type: "item", icon: "file-down", label: "Reports & Exports", href: "/admin/reports/" },
      { type: "separator" },
      { type: "label", text: "System" },
      { type: "item", icon: "user", label: "Account", href: "/account/" },
      { type: "item", icon: "users", label: "Users", href: "/admin/users/" },
      { type: "separator" },
      { type: "item", icon: "log-out", label: "Log Out", href: "/auth/logout.php", danger: true },
    ],
  });

  return `
  <header class="topbar">
    <button id="menu-toggle" class="topbar-menu" aria-label="Open menu">
      <i data-lucide="menu"></i>
    </button>

    <div class="topbar-search">
      <i data-lucide="search"></i>
      <input type="text" placeholder="Search case #, name, barangay…">
    </div>

    <div class="topbar-actions">
      <span class="topbar-meta"><i data-lucide="calendar"></i> <span id="admin-date"></span> &middot; City of Mati</span>
      <button class="topbar-bell" aria-label="Notifications"><i data-lucide="bell"></i></button>
      <span class="topbar-divider"></span>
      ${profileMenu}
    </div>
  </header>`;
}
