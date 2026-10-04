<?php

namespace App\Http;

use App\Database;

class Response
{
    public const SERVER_ERROR_MESSAGE = 'Something went wrong. Try again.';

    public static bool $sent = false;
    public static int $statusCode = 200;
    public static array $headers = [];

    public static function json(array $payload, int $status = 200): void
    {
        self::$sent = true;
        self::$statusCode = $status;
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            self::sendCorsHeaders();
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function sendCorsHeaders(): void
    {
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Device-Key');

        $allowedOrigins = array_filter(
            array_map('trim', explode(',', (string) Database::env('CORS_ALLOWED_ORIGINS', '')))
        );

        if ($allowedOrigins === []) {
            error_log('[CORS] CORS_ALLOWED_ORIGINS is not set; falling back to Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Origin: *');
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
    }

    public static function success($data = null, int $status = 200, ?array $meta = null): void
    {
        $body = ['success' => true, 'data' => $data, 'error' => null];
        if ($meta !== null) {
            $body['meta'] = $meta;
        }
        self::json($body, $status);
    }

    public static function error(string $code, string $message, int $status = 400, $data = null): void
    {
        self::json([
            'success' => false,
            'data' => $data,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }

    public static function serverError(\Throwable $e): void
    {
        error_log('[SERVER_ERROR] ' . $e);
        self::error('SERVER_ERROR', self::SERVER_ERROR_MESSAGE, 500);
    }

    public static function paginated($items, array $meta, int $status = 200): void
    {
        self::json([
            'success' => true,
            'data' => $items,
            'meta' => $meta,
            'error' => null,
        ], $status);
    }
}
