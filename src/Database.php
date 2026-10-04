<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function env(string $key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        $v = getenv($key);
        return ($v === false) ? $default : $v;
    }

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $driver = self::env('DB_DRIVER', 'pgsql');
            $host   = self::env('DB_HOST', '127.0.0.1');
            $port   = self::env('DB_PORT', $driver === 'mysql' ? '3306' : '5432');
            $db     = self::env('DB_NAME', $driver === 'mysql' ? 'furescue' : 'postgres');
            $user   = self::env('DB_USER', 'postgres');
            $pass   = self::env('DB_PASS', '');

            if ($driver === 'mysql') {
                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            } else {
                $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
                $sslmode = self::env('DB_SSLMODE');
                if ($sslmode) {
                    $dsn .= ";sslmode={$sslmode}";
                }
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => false,
            ];

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new PDOException(
                    "Database connection failed: " . $e->getMessage() .
                    " (dsn=$dsn user=$user)",
                    (int) $e->getCode(),
                    $e
                );
            }
        }

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
