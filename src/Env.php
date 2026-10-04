<?php

namespace App;

use Dotenv\Dotenv;

final class Env
{
    public static function load(?string $root = null): void
    {
        $root ??= dirname(__DIR__);
        Dotenv::createImmutable($root, self::fileName($root))->safeLoad();
    }

    public static function loadTest(?string $root = null): bool
    {
        $root ??= dirname(__DIR__);
        $path = $root . DIRECTORY_SEPARATOR . '.env.test';
        if (!is_file($path)) {
            return false;
        }
        Dotenv::createMutable($root, '.env.test')->load();
        $_ENV['FURESCUE_ENV_FILE'] = '.env.test';
        putenv('FURESCUE_ENV_FILE=.env.test');
        return true;
    }

    public static function fileName(?string $root = null): string
    {
        $root ??= dirname(__DIR__);
        $explicit = self::raw('FURESCUE_ENV_FILE');
        if ($explicit !== '') {
            return basename(str_replace('\\', '/', $explicit));
        }
        if (self::raw('APP_ENV') === 'testing' && is_file($root . DIRECTORY_SEPARATOR . '.env.test')) {
            return '.env.test';
        }
        return '.env';
    }

    public static function isTestDatabase(): bool
    {
        return Database::env('DB_NAME') === 'furescue_test';
    }

    private static function raw(string $key): string
    {
        if (array_key_exists($key, $_ENV) && is_string($_ENV[$key])) {
            return $_ENV[$key];
        }
        $value = getenv($key);
        return $value === false ? '' : $value;
    }
}
