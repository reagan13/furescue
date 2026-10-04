<?php

namespace App\Http;

class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $body;
    public array $headers;
    public ?array $user = null;
    public array $permissions = [];
    public array $params = [];
    public bool $invalidJson = false;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path = $this->resolvePath();
        $this->query = $_GET;
        $this->headers = $this->resolveHeaders();
        $this->body = $this->resolveBody();
    }

    private function resolvePath(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $script = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($script !== '/' && str_starts_with($uri, $script)) {
            $uri = substr($uri, strlen($script));
        }
        return '/' . ltrim($uri, '/');
    }

    private function resolveHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }

    private function resolveBody(): array
    {
        if ($this->method === 'GET' || $this->method === 'DELETE') {
            return [];
        }
        $contentType = strtolower((string) ($this->headers['content-type'] ?? ''));
        if (
            str_contains($contentType, 'multipart/form-data')
            || str_contains($contentType, 'application/x-www-form-urlencoded')
        ) {
            return is_array($_POST) ? $_POST : [];
        }
        $raw = (string) (file_get_contents('php://input') ?: '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $this->invalidJson = true;
            return [];
        }
        return $decoded;
    }

    /**
     * Normalize a $_FILES entry (`photos` or `photos[]`) into a list of file arrays.
     *
     * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    public function files(string $key): array
    {
        $entry = $_FILES[$key] ?? null;
        if (!$entry || !is_array($entry) || !isset($entry['name'])) {
            return [];
        }
        if (!is_array($entry['name'])) {
            if (($entry['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return [];
            }
            return [[
                'name' => (string) ($entry['name'] ?? ''),
                'type' => (string) ($entry['type'] ?? ''),
                'tmp_name' => (string) ($entry['tmp_name'] ?? ''),
                'error' => (int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($entry['size'] ?? 0),
            ]];
        }
        $out = [];
        foreach (array_keys($entry['name']) as $i) {
            if (($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => (string) ($entry['name'][$i] ?? ''),
                'type' => (string) ($entry['type'][$i] ?? ''),
                'tmp_name' => (string) ($entry['tmp_name'][$i] ?? ''),
                'error' => (int) ($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($entry['size'][$i] ?? 0),
            ];
        }
        return $out;
    }

    public function header(string $name, $default = null)
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return $m[1];
        }
        return null;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }
}
