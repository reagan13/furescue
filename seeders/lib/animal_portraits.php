<?php

/**
 * Demo animal portraits so profiles are not empty paw placeholders.
 */

function seed_animal_portraits(SeedContext $ctx): int
{
    $ctx->loadAnimals();
    $dir = dirname(__DIR__, 2) . '/public/uploads/demo/animals';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create ' . $dir);
    }

    $stmt = $ctx->pdo->query(
        "SELECT a.id, a.name, a.species, a.sex, a.photo_urls, r.photo_urls AS report_photos
         FROM animals a
         LEFT JOIN cases c ON c.id = a.case_id
         LEFT JOIN reports r ON r.id = c.report_id
         WHERE a.deleted_at IS NULL"
    );
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    $update = $ctx->pdo->prepare('UPDATE animals SET photo_urls = ? WHERE id = ?');
    $filled = 0;

    foreach ($rows as $row) {
        $id = (string) $row['id'];
        $name = (string) ($row['name'] ?? 'Friend');
        $species = (string) ($row['species'] ?? 'dog');
        $sex = (string) ($row['sex'] ?? '');
        $fromReport = seed_animal_normalize_photo_json($row['report_photos'] ?? null);
        $hasOwn = seed_animal_has_photos($row['photo_urls'] ?? null);
        if ($hasOwn && str_contains((string) $row['photo_urls'], '/uploads/demo/animals/')) {
            seed_animal_write_portrait($dir, $id, $name, $species, $sex);
            continue;
        }
        if ($hasOwn) {
            continue;
        }
        $json = $fromReport ?? seed_animal_write_portrait($dir, $id, $name, $species, $sex);
        $update->execute([$json, $id]);
        $filled++;
    }

    echo "Animal portraits: {$filled} attached.\n";
    return $filled;
}

function seed_animal_has_photos(mixed $raw): bool
{
    return seed_animal_normalize_photo_json($raw) !== null;
}

function seed_animal_normalize_photo_json(mixed $raw): ?string
{
    if (!is_string($raw)) {
        return null;
    }
    $trim = trim($raw);
    if ($trim === '' || $trim === 'null' || $trim === '[]') {
        return null;
    }
    $decoded = json_decode($trim, true);
    if (is_array($decoded)) {
        $urls = array_values(array_filter($decoded, static fn($u) => is_string($u) && trim($u) !== ''));
        return $urls === [] ? null : json_encode($urls, JSON_UNESCAPED_SLASHES);
    }
    if (str_starts_with($trim, '/') || str_starts_with($trim, 'http')) {
        return json_encode([$trim], JSON_UNESCAPED_SLASHES);
    }
    return null;
}

function seed_animal_write_portrait(string $dir, string $id, string $name, string $species, string $sex): string
{
    $rel = '/uploads/demo/animals/' . $id . '.svg';
    $path = $dir . '/' . $id . '.svg';
    file_put_contents($path, seed_animal_portrait_svg($name, $species, $sex));
    return json_encode([$rel], JSON_UNESCAPED_SLASHES);
}

function seed_animal_portrait_svg(string $name, string $species, string $sex): string
{
    $palettes = [
        ['#3D7432', '#E7F0E5', '#2A5424'],
        ['#2F6F5E', '#E3F1EC', '#1F4A3E'],
        ['#C46A3A', '#F8EDE6', '#8A4320'],
        ['#2F5F8A', '#E6EEF6', '#1E3F5C'],
        ['#6B4C9A', '#F0EAF7', '#463066'],
    ];
    $idx = abs(crc32($name . $species . $sex)) % count($palettes);
    [$ink, $paper, $deep] = $palettes[$idx];
    $label = htmlspecialchars(trim($name) !== '' ? trim($name) : 'Animal', ENT_QUOTES, 'UTF-8');
    $face = strtolower($species) === 'cat'
        ? seed_animal_cat_face($paper, $deep, $ink)
        : seed_animal_dog_face($paper, $deep, $ink);

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="480" viewBox="0 0 640 480" role="img" aria-label="{$label}">
  <rect width="640" height="480" fill="{$paper}"/>
  {$face}
  <text x="320" y="446" text-anchor="middle" font-family="Nunito, system-ui, sans-serif" font-size="22" font-weight="700" fill="{$deep}">{$label}</text>
</svg>
SVG;
}

function seed_animal_dog_face(string $paper, string $deep, string $ink): string
{
    return <<<SVG
  <ellipse cx="198" cy="188" rx="58" ry="86" fill="{$deep}" transform="rotate(-32 198 188)"/>
  <ellipse cx="442" cy="188" rx="58" ry="86" fill="{$deep}" transform="rotate(32 442 188)"/>
  <ellipse cx="216" cy="198" rx="28" ry="48" fill="{$ink}" transform="rotate(-32 216 198)"/>
  <ellipse cx="424" cy="198" rx="28" ry="48" fill="{$ink}" transform="rotate(32 424 198)"/>
  <ellipse cx="320" cy="248" rx="138" ry="124" fill="{$ink}"/>
  <ellipse cx="320" cy="300" rx="78" ry="54" fill="{$paper}"/>
  <ellipse cx="268" cy="226" rx="18" ry="20" fill="{$paper}"/>
  <ellipse cx="372" cy="226" rx="18" ry="20" fill="{$paper}"/>
  <circle cx="272" cy="230" r="9" fill="{$deep}"/>
  <circle cx="368" cy="230" r="9" fill="{$deep}"/>
  <circle cx="275" cy="226" r="3" fill="{$paper}"/>
  <circle cx="371" cy="226" r="3" fill="{$paper}"/>
  <ellipse cx="320" cy="286" rx="20" ry="14" fill="{$deep}"/>
  <path d="M320 298 v20 M320 308 c-20 18 -42 14 -54 2 M320 308 c20 18 42 14 54 2" fill="none" stroke="{$deep}" stroke-width="6" stroke-linecap="round"/>
SVG;
}

function seed_animal_cat_face(string $paper, string $deep, string $ink): string
{
    return <<<SVG
  <path d="M198 214 L248 108 L286 230 Z" fill="{$ink}"/>
  <path d="M442 214 L392 108 L354 230 Z" fill="{$ink}"/>
  <path d="M220 208 L246 132 L268 220 Z" fill="{$deep}"/>
  <path d="M420 208 L394 132 L372 220 Z" fill="{$deep}"/>
  <ellipse cx="320" cy="256" rx="132" ry="118" fill="{$ink}"/>
  <ellipse cx="268" cy="236" rx="16" ry="22" fill="{$deep}"/>
  <ellipse cx="372" cy="236" rx="16" ry="22" fill="{$deep}"/>
  <ellipse cx="270" cy="238" rx="6" ry="10" fill="{$paper}"/>
  <ellipse cx="370" cy="238" rx="6" ry="10" fill="{$paper}"/>
  <path d="M320 262 L304 286 L336 286 Z" fill="{$deep}"/>
  <path d="M320 286 V312 M320 296 L286 308 M320 296 L354 308" fill="none" stroke="{$deep}" stroke-width="5" stroke-linecap="round"/>
  <path d="M214 276 H168 M214 290 H164 M214 304 H172 M426 276 H472 M426 290 H476 M426 304 H468" fill="none" stroke="{$deep}" stroke-width="3" stroke-linecap="round"/>
SVG;
}
