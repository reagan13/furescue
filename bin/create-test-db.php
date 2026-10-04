<?php

/**
 * CREATE DATABASE furescue_test (utf8mb4). Does not touch the demo `furescue` schema.
 *
 *   php bin\create-test-db.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Env;

$root = dirname(__DIR__);
if (!Env::loadTest($root)) {
    fwrite(STDERR, "Missing .env.test. Copy .env.test.example and set DB_USER / DB_PASS.\n");
    exit(1);
}

$name = (string) Database::env('DB_NAME', '');
if ($name !== 'furescue_test') {
    fwrite(STDERR, "Refusing to create a database named '{$name}'. .env.test must set DB_NAME=furescue_test.\n");
    exit(1);
}

$host = (string) Database::env('DB_HOST', '127.0.0.1');
$port = (string) Database::env('DB_PORT', '3306');
$appUser = (string) Database::env('DB_USER', 'furescue');
$appPass = (string) Database::env('DB_PASS', '');
$adminUser = (string) (Database::env('FURESCUE_TEST_DB_ADMIN_USER') ?: $appUser);
$adminPass = (string) (Database::env('FURESCUE_TEST_DB_ADMIN_PASS') ?: $appPass);

$sql = <<<SQL
CREATE DATABASE IF NOT EXISTS `furescue_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON furescue_test.* TO '{$appUser}'@'localhost';
FLUSH PRIVILEGES;
SQL;

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $adminUser,
        $adminPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
    echo "Database furescue_test is ready (utf8mb4). Granted to {$appUser}.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Could not create furescue_test as '{$adminUser}'.\n");
    fwrite(STDERR, "Set FURESCUE_TEST_DB_ADMIN_USER / FURESCUE_TEST_DB_ADMIN_PASS in .env.test (a user with CREATE DATABASE), or run this as root:\n\n");
    fwrite(STDERR, $sql . "\n\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
