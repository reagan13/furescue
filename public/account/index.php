<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Auth\JwtService;
use App\Database;

Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

$requiredRole = ['resident', 'rescuer', 'admin'];
require_once dirname(__DIR__, 2) . '/views/path.php';
require views_path('components/guard.php');

$uid = (string) $_SESSION['user']['id'];
$role = strtolower((string) ($_SESSION['user']['role'] ?? ''));
$pdo = Database::connect();
$userData = (new \App\Repositories\UserRepository($pdo))->find($uid);
$userData = $userData ? $userData->toArray() : [];

$accountUser = [
    'id' => $uid,
    'full_name' => (string) ($userData['full_name'] ?? ($_SESSION['user']['full_name'] ?? '')),
    'email' => (string) ($_SESSION['user']['email'] ?? ''),
    'role' => (string) ($_SESSION['user']['role'] ?? $role),
    'profile_photo_url' => (string) ($userData['profile_photo_url'] ?? ''),
];

ob_start();
require views_path('account/index.php');
$content = ob_get_clean();

$jwt = new JwtService();
$pageState = [
    'accessToken' => $jwt->issueAccessToken(['id' => $uid, 'role' => $accountUser['role']]),
    'user' => $accountUser,
];
$pageModules = ['/account/js/account.js'];
$pageTitle = 'FurEscue — Account';
$pageDescription = 'Update your FurEscue profile photo, name, phone number, and address.';
$activeNav = 'account';

if ($role === 'admin') {
    $adminUser = $accountUser;
    $navBadges = ['notifications' => null];
    $adminChildren = $content;
    ob_start();
    require views_path('layouts/admin.php');
    $pageHtml = (string) ob_get_clean();
    $pageCss = ['/admin/css/admin.css'];
    $fontsHref = 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300..900&family=Nunito:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap';
    require views_path('components/site-head.php');
    ?>
  <body>
    <div id="app"><?= $pageHtml ?></div>
    <script>window.__PAGE_STATE__ = <?= json_encode($pageState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script type="module" src="/account/js/account.js"></script>
  </body>
</html>
    <?php
} else {
    $residentUser = $accountUser;
    $residentShellTitle = 'Account';
    require views_path('layouts/resident.php');
}
