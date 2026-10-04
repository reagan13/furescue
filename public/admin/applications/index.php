<?php

declare(strict_types=1);

use App\Database;
use App\Repositories\UserRepository;

require __DIR__ . '/../../../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__, 3))->safeLoad();

$requiredRole = 'admin';
require_once dirname(__DIR__, 3) . '/views/path.php';
require views_path('components/guard.php');

require views_path('components/admin-ui-helpers.php');

$uid = (string) $_SESSION['user']['id'];
$role = (string) ($_SESSION['user']['role'] ?? '');
$items = [];
$loadError = '';
$adminUser = [
    'id' => $uid,
    'full_name' => (string) ($_SESSION['user']['full_name'] ?? ''),
    'email' => (string) ($_SESSION['user']['email'] ?? ''),
    'role' => $role,
    'profile_photo_url' => '',
];

try {
    $pdo = Database::connect();
    $stmt = $pdo->prepare(
        "SELECT a.*, u.full_name AS applicant_name, an.name AS animal_name,
                an.species, an.breed_type, an.photo_urls
         FROM adoptions a
         LEFT JOIN users u ON u.id = a.applicant_id
         LEFT JOIN animals an ON an.id = a.animal_id
         ORDER BY a.created_at DESC"
    );
    $stmt->execute();
    $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $currentUser = (new UserRepository($pdo))->find($uid);
    $currentUserData = $currentUser ? $currentUser->toArray() : [];
    $adminUser = [
        'id' => $uid,
        'full_name' => (string) ($currentUserData['full_name'] ?? ($_SESSION['user']['full_name'] ?? '')),
        'email' => (string) ($_SESSION['user']['email'] ?? ''),
        'role' => $role,
        'profile_photo_url' => (string) ($currentUserData['profile_photo_url'] ?? ''),
    ];
} catch (\Throwable) {
    $loadError = 'Could not load applications.';
}

$statusCount = static function (string $status) use ($items): int {
    return count(array_filter($items, static fn(array $a) => ($a['status'] ?? '') === $status));
};
$counts = [
    'all' => count($items),
    'pending' => $statusCount('pending'),
    'approved' => $statusCount('approved'),
    'rejected' => $statusCount('rejected'),
    'completed' => $statusCount('completed'),
    'cancelled' => $statusCount('cancelled'),
];

$stampCls = static function (string $status): string {
    if ($status === 'pending') {
        return 'stamp--coral';
    }
    if ($status === 'approved' || $status === 'completed') {
        return 'stamp--accent';
    }
    return 'stamp--muted';
};

$appButton = static function (
    string $text,
    string $variant = 'default',
    string $size = 'default',
    string $icon = '',
    string $attrs = ''
): string {
    $variantCls = match ($variant) {
        'outline' => BTN_VARIANT_OUTLINE,
        'destructive' => 'bg-destructive text-destructive-foreground shadow-sm hover:bg-destructive/90',
        default => BTN_VARIANT_DEFAULT,
    };
    $sizeCls = $size === 'sm' ? BTN_SIZE_SM : BTN_SIZE_DEFAULT;
    $cls = trim(BTN_BASE . ' ' . $variantCls . ' ' . $sizeCls);
    $inner = ($icon !== '' ? '<i data-lucide="' . e($icon) . '" class="icon"></i>' : '') . '<span>' . e($text) . '</span>';
    return '<button type="button" class="' . e($cls) . '"' . ($attrs !== '' ? ' ' . $attrs : '') . '>' . $inner . '</button>';
};

$state = [
    'accessToken' => (new \App\Auth\JwtService())->issueAccessToken(['id' => $uid, 'role' => $role]),
    'user' => [
        'id' => $uid,
        'full_name' => (string) ($adminUser['full_name'] ?? ''),
        'email' => (string) ($adminUser['email'] ?? ''),
        'role' => $role,
    ],
    'items' => $items,
    'filter' => 'all',
    'query' => '',
    'page' => 1,
    'selectedId' => null,
    'loadError' => $loadError,
];

require views_path('admin/applications/index.php');

$activeNav = 'applications';
$navBadges = [
    'notifications' => null,
    'applications' => $counts['pending'],
];

ob_start();
require views_path('layouts/admin.php');
$pageHtml = (string) ob_get_clean();

$pageTitle = 'FurEscue — Applications';
$pageDescription = 'FurEscue admin applications — review, approve, decline, and complete pet adoptions for City of Mati.';
$pageCss = [
    '/admin/css/admin.css',
    '/admin/applications/css/applications.css',
];
$fontsHref = 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300..900&family=Nunito:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap';
require views_path('components/site-head.php');
?>
  <body>
    <div id="app"><?= $pageHtml ?></div>
    <script>window.__PAGE_STATE__ = <?= json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script type="module" src="/admin/applications/js/applications.js"></script>
  </body>
</html>
