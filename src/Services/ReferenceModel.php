<?php

namespace App\Services;

/**
 * Shared reference GLB chosen on the animal form.
 * An empty preset follows species: dog → aspin, cat → puspin.
 */
class ReferenceModel
{
    public const PRESETS = ['aspin', 'puspin', 'hungarian', 'german_shepherd'];

    private const FILES = [
        'aspin' => 'aspin.glb',
        'puspin' => 'puspin.glb',
        'hungarian' => 'hungarian.glb',
        'german_shepherd' => 'german-shepherd.glb',
    ];

    /** @var array<string, bool> */
    private static array $onDisk = [];

    public static function stored(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $preset = trim($value);
        return in_array($preset, self::PRESETS, true) ? $preset : null;
    }

    public static function url(?string $preset, ?string $species): ?string
    {
        $file = self::FILES[self::key($preset, $species)];
        if (!array_key_exists($file, self::$onDisk)) {
            self::$onDisk[$file] = is_file(self::directory() . DIRECTORY_SEPARATOR . $file);
        }
        if (!self::$onDisk[$file]) {
            return null;
        }
        return '/assets/models/' . $file;
    }

    public static function decorate(array $animal): array
    {
        $preset = $animal['model_preset'] ?? null;
        $animal['reference_model_url'] = self::url(
            is_string($preset) ? $preset : null,
            isset($animal['species']) ? (string) $animal['species'] : null
        );
        return $animal;
    }

    public static function key(?string $preset, ?string $species): string
    {
        $preset = strtolower(trim((string) $preset));
        if (in_array($preset, self::PRESETS, true)) {
            return $preset;
        }
        return strtolower(trim((string) $species)) === 'cat' ? 'puspin' : 'aspin';
    }

    public static function directory(): string
    {
        return dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'models';
    }
}
