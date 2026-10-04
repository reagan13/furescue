<?php

namespace App\Services;

use App\Database;

class CaseProofUpload
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_FILES = 10;
    public const PUBLIC_PREFIX = '/uploads/cases/';

    private const FILE_KEYS = ['files', 'files[]', 'photos', 'proof', 'file'];

    private string $publicRoot;
    private bool $allowLocalCopy;

    public function __construct(?string $publicRoot = null, ?bool $allowLocalCopy = null)
    {
        $configured = Database::env('UPLOAD_PUBLIC_ROOT', '');
        $this->publicRoot = $publicRoot
            ?? ($configured !== '' && $configured !== null ? (string) $configured : dirname(__DIR__, 2) . '/public');
        $this->allowLocalCopy = $allowLocalCopy ?? ((string) Database::env('APP_TESTING', '') === '1');
    }

    public static function collect(array $filesGlobal): array
    {
        $out = [];
        foreach (self::FILE_KEYS as $key) {
            if (!isset($filesGlobal[$key])) {
                continue;
            }
            $out = array_merge($out, AnimalAssetUpload::normalizeFiles($filesGlobal[$key]));
        }
        return $out;
    }

    public function store(array $file): string
    {
        $error = AnimalAssetUpload::validate(
            $file,
            AnimalAssetUpload::photoRules(),
            self::MAX_BYTES,
            !$this->allowLocalCopy
        );
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        $ext = AnimalAssetUpload::extension((string) $file['name']);
        $relDir = date('Y') . '/' . date('m');
        $dir = $this->publicRoot . '/uploads/cases/' . $relDir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the upload directory.');
        }

        $stored = Database::uuidV4() . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $stored;
        $tmp = (string) ($file['tmp_name'] ?? '');
        $moved = $tmp !== '' && is_uploaded_file($tmp) && move_uploaded_file($tmp, $dest);
        if (!$moved && $this->allowLocalCopy && $tmp !== '' && is_file($tmp) && copy($tmp, $dest)) {
            $moved = true;
        }
        if (!$moved) {
            throw new \RuntimeException("Could not save '{$file['name']}'.");
        }

        return self::PUBLIC_PREFIX . $relDir . '/' . $stored;
    }
}
