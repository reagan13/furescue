<?php

use App\Auth\SessionAuth;

$navLinks = [
    ['label' => 'Home', 'href' => '#home'],
    ['label' => 'Who It Helps', 'href' => '#audiences'],
    ['label' => 'Features', 'href' => '#features'],
    ['label' => 'How It Works', 'href' => '#how'],
];

$navLinkItems = static function (array $links): string {
    $out = '';
    foreach ($links as $link) {
        $out .= '<a href="' . htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') . '" class="nav-link">' . htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') . '</a>';
    }
    return $out;
};

$navUser = SessionAuth::user();
$navRole = (string) ($navUser['role'] ?? '');
$navHomeHref = htmlspecialchars(SessionAuth::homePath($navRole), ENT_QUOTES, 'UTF-8');
$navHomeLabel = htmlspecialchars(SessionAuth::homeLabel($navRole), ENT_QUOTES, 'UTF-8');

$navActionsMarkup = $navUser
    ? '<a href="' . $navHomeHref . '" class="btn btn--solid btn--sm"><span>' . $navHomeLabel . '</span></a>'
      . '<a href="/auth/logout.php" class="btn btn--ghost btn--sm"><span>Log out</span></a>'
    : '<a href="/auth/login.php" class="btn btn--ghost btn--sm"><span>Log in</span></a>'
      . '<a href="/auth/signup.php" class="btn btn--solid btn--sm"><span>Get Started</span></a>';

$navLinkMarkup = $navLinkItems($navLinks);
?>
<header id="navbar" class="nav">
  <div class="container nav-inner">
    <a href="#home" class="brand">
      <span class="logo-mark" aria-hidden="true"><i data-lucide="paw-print"></i></span>
      <span>Fur<span class="text-primary">escue</span></span>
    </a>

    <nav class="nav-links">
      <?= $navLinkMarkup ?>
    </nav>

    <div class="nav-actions">
      <?= $navActionsMarkup ?>
    </div>

    <button id="menu-toggle" class="nav-toggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-menu">
      <span class="bar"></span>
      <span class="bar"></span>
      <span class="bar"></span>
    </button>
  </div>
</header>
<div id="mobile-menu" class="mobile-menu" aria-hidden="true">
  <button type="button" id="mobile-menu-backdrop" class="mobile-menu-backdrop" tabindex="-1" aria-label="Close menu"></button>
  <div class="mobile-menu-panel">
    <nav class="container mobile-menu-links">
      <?= $navLinkMarkup ?>
    </nav>
    <div class="container mobile-menu-actions">
      <?= $navActionsMarkup ?>
    </div>
  </div>
</div>
