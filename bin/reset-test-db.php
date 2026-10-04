<?php

/**
 * Wipe and re-seed furescue_test. Never runs against the demo `furescue` DB.
 *
 *   php bin\reset-test-db.php
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
    fwrite(STDERR, "Refusing to reset: DB_NAME must be furescue_test (got " . Database::env('DB_NAME') . ").\n");
    exit(1);
}

putenv('FURESCUE_ENV_FILE=.env.test');
$_ENV['FURESCUE_ENV_FILE'] = '.env.test';
$_SERVER['FURESCUE_ENV_FILE'] = '.env.test';
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';

$php = PHP_BINARY;
$migrate = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'migrate.php';
$seed = $root . DIRECTORY_SEPARATOR . 'seeders' . DIRECTORY_SEPARATOR . 'seed.php';

passthru(escapeshellarg($php) . ' ' . escapeshellarg($migrate) . ' --fresh --yes', $migrateCode);
if ($migrateCode !== 0) {
    exit($migrateCode);
}

passthru(escapeshellarg($php) . ' ' . escapeshellarg($seed), $seedCode);
exit($seedCode);
