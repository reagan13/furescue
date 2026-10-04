<?php

/**
 * Serve the app with `.env.test` in the process environment.
 * Does not overwrite the `.env` file on disk.
 *
 *   php bin\serve-test.php
 *   php bin\serve-test.php 127.0.0.1:8000
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Env;

$root = dirname(__DIR__);
if (!Env::loadTest($root)) {
    fwrite(STDERR, "Missing .env.test. Copy .env.test.example and set DB_USER / DB_PASS.\n");
    exit(1);
}

if (!Env::isTestDatabase()) {
    fwrite(STDERR, "Refusing to serve: DB_NAME must be furescue_test (got " . Database::env('DB_NAME') . ").\n");
    exit(1);
}

$listen = $argv[1] ?? '127.0.0.1:8000';
$public = $root . DIRECTORY_SEPARATOR . 'public';
$router = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'serve-test-router.php';

fwrite(STDOUT, "Serving FurEscue from {$public} on {$listen} (DB=" . Database::env('DB_NAME') . ").\n");
fwrite(STDOUT, "Demo .env is unchanged. Ctrl+C to stop.\n");

$cmd = [
    PHP_BINARY,
    '-S',
    $listen,
    '-t',
    $public,
    $router,
];

passthru(implode(' ', array_map('escapeshellarg', $cmd)), $code);
exit($code);
