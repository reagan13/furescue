<?php

namespace App\Services;

use App\Database;

class ListingPhotoUpload
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_COUNT = 8;
    public const PUBLIC_PREFIX = '/uploads/animals/listings/';

    private const EXT = [
        'jpg' => true,
        'jpeg' => true,
        'png' => true,
        'gif' => true,
        'webp' => true,
    ];

    private string $publicRoot;

    public function __construct(?string $publicRoot = null)
    {
        $this->publicRoot = $publicRoot ?? dirname(__DIR__, 2) . '/public';
    }

    /**
     * @param list<array{name:string,type:string,tmp_name:string,error:int,size:int}> $files
     * @return list<string>
     */
    public function store(array $files): array
    {
        if ($files === []) {
            return [];
        }
        if (count($files) > self::MAX_COUNT) {
            throw new \InvalidArgumentException('Up to ' . self::MAX_COUNT . ' photos can be attached.');
        }

        $dir = $this->publicRoot . '/uploads/animals/listings/' . date('Y') . '/' . date('m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the upload directory.');
        }

        $urls = [];
        foreach ($files as $file) {
            $this->assertFile($file);
            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $stored = Database::uuidV4() . '.' . $ext;
            $ok = is_uploaded_file((string) $file['tmp_name'])
                ? move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $stored)
                : copy((string) $file['tmp_name'], $dir . '/' . $stored);
            if (!$ok) {
                throw new \RuntimeException("Could not save '{$file['name']}'.");
            }
            $urls[] = self::PUBLIC_PREFIX . date('Y') . '/' . date('m') . '/' . $stored;
        }

        return $urls;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    public static function normalizeUrlList(mixed $value): array
    {
        if (is_string($value)) {
            $trim = trim($value);
            if ($trim === '' || $trim === '[]' || $trim === 'null') {
                return [];
            }
            $decoded = json_decode($trim, true);
            $value = is_array($decoded) ? $decoded : [$trim];
        }
        if (!is_array($value)) {
            return [];
        }
        $urls = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $url = trim($item);
            if ($url === '') {
                continue;
            }
            if (str_starts_with($url, '/') || preg_match('#^https?://#i', $url) === 1) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param array{name?:string,tmp_name?:string,error?:int,size?:int} $file
     */
    private function assertFile(array $file): void
    {
        $name = (string) ($file['name'] ?? 'file');
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException("Upload failed for '{$name}'.");
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new \InvalidArgumentException('Invalid upload.');
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new \InvalidArgumentException("'{$name}' exceeds the 10 MB size limit.");
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::EXT[$ext])) {
            throw new \InvalidArgumentException('Photos must be JPG, PNG, GIF, or WEBP.');
        }
        $mime = AnimalAssetUpload::detectMime($file);
        if (str_starts_with($mime, 'image/') === false && $mime !== 'application/octet-stream') {
            throw new \InvalidArgumentException('Photos must be JPG, PNG, GIF, or WEBP.');
        }
    }
}
