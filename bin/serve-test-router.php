<?php

/**
 * php -S router that loads `.env.test` in this process.
 * Windows does not inherit Dotenv `$_ENV` into the built-in server child.
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (!App\Env::loadTest($root)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Missing .env.test\n";
    exit(1);
}

// php -S serves real files in a new process that does not see Dotenv $_ENV.
// Require PHP pages here so login/signup use furescue_test.
$public = realpath($root . DIRECTORY_SEPARATOR . 'public');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = $public !== false ? realpath($public . rawurldecode($uri)) : false;
if (
    is_string($public)
    && is_string($file)
    && is_file($file)
    && str_ends_with(strtolower($file), '.php')
    && str_starts_with($file, $public . DIRECTORY_SEPARATOR)
) {
    return require $file;
}

return require $root . '/public/index.php';
