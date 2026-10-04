<?php

namespace App\Tests\Support;

use App\Http\Request;
use App\Http\Response;

trait InteractsWithHttp
{
    protected function makeRequest(
        string $method,
        string $path,
        array $body = [],
        array $query = [],
        array $headers = [],
        ?array $user = null
    ): Request {
        $request = new Request();
        $request->method = strtoupper($method);
        $request->path = '/' . ltrim($path, '/');
        $request->body = $body;
        $request->query = $query;
        $request->headers = array_change_key_case($headers, CASE_LOWER);
        $request->user = $user;
        return $request;
    }

    protected function observe(callable $handler): array
    {
        Response::$sent = false;
        Response::$statusCode = 200;
        Response::$headers = [];
        ob_start();
        try {
            $handler();
        } finally {
            $raw = (string) ob_get_clean();
        }
        $decoded = json_decode($raw, true);
        return [
            'status' => Response::$statusCode,
            'body' => is_array($decoded) ? $decoded : [],
            'raw' => $raw,
            'headers' => Response::$headers,
        ];
    }
}
