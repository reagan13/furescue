<?php

require __DIR__ . '/../vendor/autoload.php';

App\Env::load();

use App\Database;

function splitStatements(string $sql): array
{
    $statements = [];
    $current = '';
    $inDollar = false;
    $n = strlen($sql);
    $i = 0;

    while ($i < $n) {
        if ($sql[$i] === '$') {
            $j = $i + 1;
            while ($j < $n && $sql[$j] !== '$') {
                $j++;
            }
            if ($j < $n) {
                $tag = substr($sql, $i, $j - $i + 1);
                $current .= $tag;
                $inDollar = !$inDollar;
                $i = $j + 1;
                continue;
            }
        }

        $ch = $sql[$i];
        if (!$inDollar && $ch === ';') {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
            $i++;
            continue;
        }

        $current .= $ch;
        $i++;
    }

    if (trim($current) !== '') {
        $statements[] = trim($current);
    }

    return $statements;
}

function listTables(\PDO $pdo, string $driver): array
{
    if ($driver === 'mysql') {
        return $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    }

    return $pdo->query(
        "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
    )->fetchAll(PDO::FETCH_COLUMN);
}

function quoteIdent(string $name, string $driver): string
{
    if ($driver === 'mysql') {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    return '"' . str_replace('"', '""', $name) . '"';
}

function dropAllTables(\PDO $pdo, string $driver): int
{
    $tables = listTables($pdo, $driver);
    if ($tables === []) {
        return 0;
    }

    if ($driver === 'mysql') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . quoteIdent((string) $table, $driver));
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        return count($tables);
    }

    foreach ($tables as $table) {
        $pdo->exec('DROP TABLE IF EXISTS ' . quoteIdent((string) $table, $driver) . ' CASCADE');
    }

    return count($tables);
}

$argvFlags = array_slice($argv ?? [], 1);
$fresh = in_array('--fresh', $argvFlags, true);
$yes = in_array('--yes', $argvFlags, true);

if ($fresh && !$yes) {
    fwrite(STDERR, "This drops ALL tables in the current database, then re-applies migrations.\n");
    fwrite(STDERR, "Re-run with --yes to confirm:\n");
    fwrite(STDERR, "  php bin\\migrate.php --fresh --yes\n");
    exit(1);
}

try {
    $pdo = Database::connect();
} catch (\PDOException $e) {
    fwrite(STDERR, "Cannot connect to the database. Check .env or .env.test and ensure the database exists.\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$driver = Database::env('DB_DRIVER', 'pgsql');

if ($fresh) {
    $dropped = dropAllTables($pdo, $driver);
    echo "Dropped {$dropped} table(s). Re-applying migrations...\n";
}

if ($driver === 'mysql') {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS migrations_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} else {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS migrations_log (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
}

$migDir = __DIR__ . '/../migrations';
$files = glob($migDir . '/*.sql');
sort($files);

$appliedRows = $pdo->query("SELECT migration FROM migrations_log")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($appliedRows);

$count = 0;
foreach ($files as $file) {
    $name = basename($file);

    if (isset($applied[$name])) {
        echo "skip  $name\n";
        continue;
    }

    $sql = (string) file_get_contents($file);
    $statements = splitStatements($sql);

    try {
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
        $pdo->prepare("INSERT INTO migrations_log (migration) VALUES (?)")->execute([$name]);
        echo "apply $name (" . count($statements) . " statements)\n";
        $count++;
    } catch (\PDOException $e) {
        fwrite(STDERR, "ERROR applying $name: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Done. Applied $count migration file(s). " . count($files) . " total migration file(s) present.\n";
if ($fresh) {
    echo "Schema is empty. Seed accounts with: php seeders\\seed_users.php\n";
    echo "Or full demo data with: php seeders\\seed.php\n";
}
