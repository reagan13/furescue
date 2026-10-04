<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Auth\SessionAuth;
use App\Database;

App\Env::load();

if (SessionAuth::user()) {
    header('Location: ' . SessionAuth::homePath());
    exit;
}

$googleClientId = trim((string) Database::env('GOOGLE_CLIENT_ID', ''));

$pageTitle = 'FurEscue — Create account';
$pageDescription = 'Create a FurEscue account to report strays, follow rescue cases, and help Puspin & Aspin find permanent families.';
$pageCss = ['/auth/css/auth.css'];
$fontsHref = 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300..900&family=Nunito:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap';
require_once dirname(__DIR__, 2) . '/views/path.php';
require views_path('auth/signup.php');
