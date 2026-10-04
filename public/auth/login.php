<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Auth\SessionAuth;
use App\Database;

App\Env::load();

$googleClientId = trim((string) Database::env('GOOGLE_CLIENT_ID', ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && SessionAuth::user()) {
    header('Location: ' . SessionAuth::homePath());
    exit;
}

$error = '';
$emailValue = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $emailValue = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($emailValue === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $pdo = Database::connect();
            $user = SessionAuth::attemptLogin($emailValue, $password, $pdo);
        } catch (\PDOException) {
            $user = null;
            if (SessionAuth::$lastError === '') {
                http_response_code(500);
                $error = 'Cannot reach the server. Make sure the backend is running.';
            }
        }
        if ($error === '' && $user !== null) {
            header('Location: ' . SessionAuth::homePath($user->role()));
            exit;
        }
        if ($error === '') {
            $error = SessionAuth::$lastError === SessionAuth::ERR_ACCOUNT_PENDING
                ? 'Account is not active.'
                : 'Email or password is incorrect';
        }
    }
}

$pageTitle = 'FurEscue — Sign in';
$pageDescription = 'Sign in to FurEscue — the centralized rescue platform for Puspin & Aspin welfare.';
$pageCss = ['/auth/css/auth.css'];
$fontsHref = 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300..900&family=Nunito:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap';
require_once dirname(__DIR__, 2) . '/views/path.php';
require views_path('auth/login.php');
