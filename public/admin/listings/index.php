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

$pdo = Database::connect();
$uid = (string) $_SESSION['user']['id'];

$stmt = $pdo->prepare(
    "SELECT l.*, a.name AS animal_name, a.species, u.full_name AS poster_name
     FROM adoption_listings l
     LEFT JOIN animals a ON a.id = l.animal_id
     LEFT JOIN users u ON u.id = l.posted_by
     ORDER BY l.created_at DESC
     LIMIT 100 OFFSET 0"
);
$stmt->execute();
$listings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$uniqueByAnimal = static function (array $rows): array {
    $rank = ['pending_review' => 0, 'approved' => 1, 'rejected' => 2];
    $best = [];
    foreach ($rows as $row) {
        $key = (string) ($row['animal_id'] ?? '');
        if ($key === '') {
            continue;
        }
        if (!isset($best[$key])) {
            $best[$key] = $row;
            continue;
        }
        $prev = $best[$key];
        $rNew = $rank[$row['status'] ?? ''] ?? 9;
        $rOld = $rank[$prev['status'] ?? ''] ?? 9;
        if ($rNew < $rOld) {
            $best[$key] = $row;
        } elseif ($rNew === $rOld) {
            $tNew = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
            $tOld = strtotime((string) ($prev['created_at'] ?? '')) ?: 0;
            if ($tNew > $tOld) {
                $best[$key] = $row;
            }
        }
    }
    return array_values($best);
};
$listings = $uniqueByAnimal($listings);

usort($listings, static function (array $a, array $b): int {
    $rankA = (($a['status'] ?? '') === 'pending_review') ? 0 : 1;
    $rankB = (($b['status'] ?? '') === 'pending_review') ? 0 : 1;
    if ($rankA !== $rankB) {
        return $rankA <=> $rankB;
    }
    return -((int) strtotime((string) ($a['created_at'] ?? ''))) <=> -((int) strtotime((string) ($b['created_at'] ?? '')));
});

$role = (string) ($_SESSION['user']['role'] ?? '');
$state = [
    'accessToken' => (new \App\Auth\JwtService())->issueAccessToken(['id' => $uid, 'role' => $role]),
    'user' => [
        'id' => $uid,
        'full_name' => (string) ($_SESSION['user']['full_name'] ?? ''),
        'email' => (string) ($_SESSION['user']['email'] ?? ''),
        'role' => $role,
    ],
    'listings' => $listings,
    'filter' => 'all',
    'query' => '',
    'page' => 1,
    'error' => null,
];

require views_path('admin/listings/index.php');

$currentUser = (new UserRepository($pdo))->find($uid);
$currentUserData = $currentUser ? $currentUser->toArray() : [];
$adminUser = [
    'id' => $uid,
    'full_name' => (string) ($currentUserData['full_name'] ?? ($_SESSION['user']['full_name'] ?? '')),
    'email' => (string) ($_SESSION['user']['email'] ?? ''),
    'role' => (string) ($_SESSION['user']['role'] ?? ''),
    'profile_photo_url' => (string) ($currentUserData['profile_photo_url'] ?? ''),
];
$activeNav = 'listings';
$navBadges = ['notifications' => null];

ob_start();
require views_path('layouts/admin.php');
$pageHtml = (string) ob_get_clean();

$pageTitle = 'FurEscue — Listings';
$pageDescription = 'FurEscue admin listings — review community adoption posts for City of Mati.';
$pageCss = ['/admin/css/admin.css'];
$fontsHref = 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300..900&family=Nunito:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap';
require views_path('components/site-head.php');
?>
  <body>
    <div id="app"><?= $pageHtml ?></div>
    <script>window.__PAGE_STATE__ = <?= json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script type="module" src="/admin/listings/js/listings.js"></script>
  </body>
</html>
