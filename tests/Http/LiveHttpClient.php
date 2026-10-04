<?php

namespace App\Tests\Http;

final class LiveHttpClient
{
    public static function baseUrl(): string
    {
        $url = getenv('FURESCUE_TEST_BASE_URL') ?: 'http://127.0.0.1:8000';
        return rtrim((string) $url, '/');
    }

    public static function reachable(): bool
    {
        $ch = curl_init(self::baseUrl() . '/');
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ]);
        $ok = curl_exec($ch) !== false;
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $ok && $code > 0;
    }

    /**
     * @param array<string, string> $headers
     * @return array{status:int,headers:array<string,string>,body:string,header_block:string}
     */
    public static function request(
        string $method,
        string $path,
        ?string $body = null,
        array $headers = [],
        ?string $cookieFile = null
    ): array {
        $ch = curl_init(self::baseUrl() . $path);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $opts = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headerLines,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        if ($cookieFile !== null) {
            $opts[CURLOPT_COOKIEJAR] = $cookieFile;
            $opts[CURLOPT_COOKIEFILE] = $cookieFile;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('curl failed: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerBlock = substr($raw, 0, $headerSize);
        $parsed = [];
        foreach (preg_split("/\r\n|\n|\r/", $headerBlock) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $parsed[strtolower(trim($name))] = trim($value);
            }
        }
        return [
            'status' => $status,
            'headers' => $parsed,
            'body' => substr($raw, $headerSize),
            'header_block' => $headerBlock,
        ];
    }

    public static function location(array $response): string
    {
        if (preg_match('/^Location:\s*(.+)$/mi', $response['header_block'] ?? '', $m)) {
            return trim($m[1]);
        }
        return $response['headers']['location'] ?? '';
    }
}
