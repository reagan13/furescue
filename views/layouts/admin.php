<?php

declare(strict_types=1);

/**
 * Shared admin chrome (AppShell = Sidebar + Topbar + main slot).
 *
 * Contract — define BEFORE including:
 *   $adminUser     array  id/full_name/email/role/profile_photo_url
 *   $activeNav     string lowercase nav key ('' falls back to 'dashboard')
 *   $navBadges     array  assoc map badgeKey => value (may be empty)
 *   $adminChildren string pre-rendered HTML for <main class="admin-main">
 *
 * Navigation is defined once in views/components/admin-nav.php and rendered
 * here as real <a href> links. Active state is resolved server-side from
 * $activeNav, so navigation works without JavaScript.
 *
 * Badge merge starts empty and applies $navBadges only. Sidebar items carry
 * no static badge values — badges are purely dynamic, so an explicit null
 * in $navBadges simply suppresses that item's badge on fresh load.
 */

require __DIR__ . '/../components/admin-nav.php';
if (!function_exists('shared_path')) {
    require_once __DIR__ . '/../path.php';
}
require_once shared_path('icons/icons.php');

$adminUser = $adminUser ?? [];
$activeNav = $activeNav ?? '';
$navBadges = $navBadges ?? [];
$adminChildren = $adminChildren ?? '';

$esc = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$adminBadgeMap = [];
foreach ($navBadges as $k => $v) {
    $adminBadgeMap[$k] = $v;
}

$adminNavItems = static function (array $items) use ($esc, $adminBadgeMap, $activeNav): string {
    $out = '';
    $resolvedActive = $activeNav !== '' ? $activeNav : 'dashboard';
    foreach ($items as $item) {
        $hasOverride = isset($item['badgeKey']) && array_key_exists($item['badgeKey'], $adminBadgeMap);
        $value = $hasOverride ? $adminBadgeMap[$item['badgeKey']] : ($item['badge'] ?? null);
        $showBadge = $value !== null && $value !== '' && $value !== 0;
        $badge = $showBadge
            ? '<span class="stamp stamp--sm sidebar-badge ' . $esc($item['badgeCls'] ?? '') . '">' . $esc($value) . '</span>'
            : '';
        $isActive = $resolvedActive === (string) ($item['key'] ?? '');
        $tone = $isActive ? ' sidebar-link--active' : '';
        $aria = $isActive ? ' aria-current="page"' : '';
        $out .= '
    <a href="' . $esc($item['href'] ?? '#') . '" class="sidebar-link' . $tone . '"' . $aria . '>
      ' . lucide_icon((string) ($item['icon'] ?? '')) . ' <span>' . $esc($item['label'] ?? '') . '</span>
      ' . $badge . '
    </a>';
    }
    return $out;
};

$adminGroupsHtml = '';
foreach ($adminNav as $group) {
    $adminGroupsHtml .= '
    <div class="sidebar-group">
      <div class="sidebar-label">' . $esc($group['label']) . '</div>
      <div class="sidebar-links">' . $adminNavItems($group['items']) . '</div>
    </div>';
}

$adminMenuItemBase = 'relative flex cursor-pointer select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground';
$adminMenuDanger = 'text-destructive hover:bg-destructive/10 hover:text-destructive';
$adminMenuLabel = static fn(string $text): string =>
    '<div class="px-2 py-1.5 text-xs font-semibold text-muted-foreground">' . $esc($text) . '</div>';
$adminMenuSeparator = '<div class="-mx-1 my-1 h-px bg-muted"></div>';
$adminMenuItem = static function (string $icon, string $label, string $href = '#', bool $danger = false) use ($esc, $adminMenuItemBase, $adminMenuDanger): string {
    $cls = $danger ? $adminMenuItemBase . ' ' . $adminMenuDanger : $adminMenuItemBase;
    return '
  <a href="' . $esc($href) . '" class="' . $cls . '">
    ' . lucide_icon($icon, 'h-4 w-4') . '
    <span>' . $esc($label) . '</span>
  </a>';
};

$adminAvatarSrc = trim((string) ($adminUser['profile_photo_url'] ?? ''));
$adminHasPhoto = $adminAvatarSrc !== '';
$adminInitials = '';
foreach (array_slice(preg_split('/\s+/u', trim((string) ($adminUser['full_name'] ?? ''))) ?: [], 0, 2) as $word) {
    if ($word === '') {
        continue;
    }
    $adminInitials .= mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
}
$adminInitials = $adminInitials !== '' ? $adminInitials : '?';

$adminProfileMenu = '
  <div id="profile-menu" data-dropdown class="relative">
    <button type="button" data-dropdown-trigger class="topbar-user" aria-haspopup="menu" aria-expanded="false" aria-label="Admin menu">
      <img src="' . $esc($adminAvatarSrc) . '" alt="" data-shell-avatar' . ($adminHasPhoto ? '' : ' hidden') . '>
      <span class="topbar-user-fallback" data-shell-avatar-fallback' . ($adminHasPhoto ? ' hidden' : '') . '>' . $esc($adminInitials) . '</span>
    </button>
    <div data-dropdown-content role="menu" class="absolute top-full z-50 mt-1 hidden min-w-56 overflow-hidden rounded-md border border-input bg-card p-1 text-card-foreground shadow-md right-0">'
        . $adminMenuLabel('Insights')
        . $adminMenuItem('bar-chart-3', 'Analytics', '/admin/analytics/')
        . $adminMenuItem('file-down', 'Reports & Exports', '/admin/analytics/')
        . $adminMenuSeparator
        . $adminMenuLabel('System')
        . $adminMenuItem('user', 'Account', '/account/')
        . $adminMenuItem('users', 'Users', '/admin/users/')
        . $adminMenuSeparator
        . $adminMenuItem('log-out', 'Log Out', '/auth/logout.php', true) . '
    </div>
  </div>';
?>

<div class="admin-shell">
  <aside id="sidebar" class="sidebar">
    <div class="sidebar-head">
      <a href="/admin/" class="sidebar-brand-link">
        <div class="sidebar-logo"><?= lucide_icon('paw-print') ?></div>
        <div>
          <div class="sidebar-brand">FurEscue</div>
          <div class="sidebar-tag">Admin Console</div>
        </div>
      </a>
    </div>

    <nav class="sidebar-nav">
<?= $adminGroupsHtml ?>
    </nav>
  </aside>
  <div id="overlay" class="admin-overlay"></div>
  <div class="admin-body">
    <header class="topbar">
      <button id="menu-toggle" class="topbar-menu" aria-label="Open menu">
        <?= lucide_icon('menu') ?>
      </button>

      <div class="topbar-actions">
        <span class="topbar-meta"><?= lucide_icon('calendar') ?> <span id="admin-date"></span> &middot; City of Mati</span>
        <a href="/admin/notifications/" class="topbar-bell" aria-label="Notifications"><?= lucide_icon('bell') ?></a>
        <span class="topbar-divider"></span>
        <?= $adminProfileMenu ?>
      </div>
    </header>
    <main class="admin-main">
<?= $adminChildren ?>
    </main>
  </div>
</div>
