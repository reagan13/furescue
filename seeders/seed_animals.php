<?php

/**
 * Shelter and resident-listed animals, plus field-status logs.
 *
 *   php seeders\seed_animals.php
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/catalog.php';
require_once __DIR__ . '/lib/animal_portraits.php';

function seed_animals(SeedContext $ctx): void
{
    $ctx->loadUsers();
    $ctx->loadCases();
    $ctx->loadReports();
    if (!$ctx->adminId || $ctx->residentIds === []) {
        echo "Animals skipped: seed accounts first.\n";
        return;
    }

    $fromCases = seed_animals_from_cases($ctx);
    $fromResidents = seed_animals_resident_listings($ctx);
    $ctx->loadAnimals();
    echo 'Animals: ' . ($fromCases + $fromResidents) . ' new, ' . count($ctx->animalIds) . " active.\n";
    seed_animal_portraits($ctx);
}

function seed_animals_from_cases(SeedContext $ctx): int
{
    $dogNames = seed_catalog_dog_names();
    $catNames = seed_catalog_cat_names();
    $dogColors = seed_catalog_dog_colors();
    $catColors = seed_catalog_cat_colors();
    $ages = seed_catalog_ages();
    $created = 0;

    $reportById = [];
    foreach ($ctx->verifiedReports as $report) {
        $reportById[$report['id']] = $report;
    }

    foreach ($ctx->cases as $i => $case) {
        if (!in_array($case['status'], ['resolved', 'in_progress'], true)) {
            continue;
        }
        if ($ctx->rowExists('animals', 'case_id = ?', [$case['id']])) {
            continue;
        }

        $report = $reportById[$case['report_id']] ?? null;
        $desc = 'Intake from a verified rescue case.';
        $barangay = seed_catalog_barangays()[$i % count(seed_catalog_barangays())][0];
        if ($report) {
            $stmt = $ctx->pdo->prepare('SELECT animal_description, address_text FROM reports WHERE id = ?');
            $stmt->execute([$report['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $desc = (string) ($row['animal_description'] ?? $desc);
            $barangay = seed_animals_barangay((string) ($row['address_text'] ?? $barangay));
        }

        $species = (stripos($desc, 'cat') !== false && stripos($desc, 'dog') === false) ? 'cat' : 'dog';
        if (preg_match('/\b(dog|cat)\b/i', $desc, $m)) {
            $species = strtolower($m[1]);
        }
        $names = $species === 'cat' ? $catNames : $dogNames;
        $colors = $species === 'cat' ? $catColors : $dogColors;
        $age = $ages[$i % count($ages)];
        $birth = date('Y-m-d', time() - $age[1] * 86400 - $ctx->int(0, 20) * 86400);
        $rescued = $case['status'] === 'resolved' || $ctx->int(1, 100) > 20;

        $animalId = $ctx->insert('animals', [
            'name' => $names[$i % count($names)],
            'species' => $species,
            'breed_type' => $species === 'cat' ? 'puspin' : 'aspin',
            'sex' => $ctx->pick(['male', 'female']),
            'age_estimate' => $age[0],
            'birth_date' => $ctx->pick([$birth, $birth, null]),
            'color_markings' => $ctx->pick($colors),
            'barangay' => $barangay,
            'description' => $desc,
            'adoption_status' => 'not_listed',
            'source' => 'rescued_case',
            'case_id' => $case['id'],
            'created_by' => $ctx->adminId,
            'created_at' => $ctx->daysAgo(1, 200),
            'deleted_at' => ($i % 28 === 0) ? $ctx->daysAgo(10, 80) : null,
        ]);

        $logger = $case['rescuer_id'] ?: $ctx->adminId;
        if ($logger && !$ctx->rowExists('animal_field_status', 'animal_id = ? AND case_id = ?', [$animalId, $case['id']])) {
            $ctx->insert('animal_field_status', [
                'animal_id' => $animalId,
                'case_id' => $case['id'],
                'rescue_status' => $rescued ? 'rescued' : 'not_rescued',
                'health_status' => $ctx->pick(['healthy', 'healthy', 'healthy', 'not_healthy']),
                'logged_by' => $logger,
                'logged_at' => $ctx->daysAgo(1, 180),
            ]);
        }
        $created++;
    }

    return $created;
}

function seed_animals_resident_listings(SeedContext $ctx): int
{
    $current = $ctx->count('SELECT COUNT(*) FROM animals');
    $needed = max(0, SeedContext::TARGET_ANIMALS - $current);
    if ($needed === 0) {
        return 0;
    }

    $dogNames = seed_catalog_dog_names();
    $catNames = seed_catalog_cat_names();
    $dogColors = seed_catalog_dog_colors();
    $catColors = seed_catalog_cat_colors();
    $ages = seed_catalog_ages();
    $barangays = seed_catalog_barangays();
    $created = 0;

    for ($i = 0; $i < $needed; $i++) {
        $species = $i % 3 === 0 ? 'cat' : 'dog';
        $names = $species === 'cat' ? $catNames : $dogNames;
        $colors = $species === 'cat' ? $catColors : $dogColors;
        $age = $ages[$i % count($ages)];
        $brgy = $barangays[$i % count($barangays)][0];
        $resident = $ctx->residentIds[$i % count($ctx->residentIds)];
        $name = $names[($current + $i) % count($names)];
        $marker = "Resident surrender · {$name} · {$brgy} · " . ($current + $i + 1);
        if ($ctx->rowExists('animals', 'description = ?', [$marker])) {
            continue;
        }

        $animalId = $ctx->insert('animals', [
            'name' => $name,
            'species' => $species,
            'breed_type' => $species === 'cat' ? 'puspin' : 'aspin',
            'sex' => $ctx->pick(['male', 'female']),
            'age_estimate' => $age[0],
            'birth_date' => date('Y-m-d', time() - $age[1] * 86400),
            'color_markings' => $ctx->pick($colors),
            'barangay' => $brgy,
            'description' => $marker,
            'adoption_status' => 'not_listed',
            'source' => 'resident_listing',
            'created_by' => $resident,
            'created_at' => $ctx->daysAgo(2, 160),
        ]);

        if (!$ctx->rowExists('animal_field_status', 'animal_id = ?', [$animalId])) {
            $ctx->insert('animal_field_status', [
                'animal_id' => $animalId,
                'case_id' => null,
                'rescue_status' => 'rescued',
                'health_status' => $ctx->pick(['healthy', 'healthy', 'not_healthy']),
                'logged_by' => $ctx->adminId,
                'logged_at' => $ctx->daysAgo(1, 120),
            ]);
        }
        $created++;
    }

    return $created;
}

function seed_animals_barangay(string $address): string
{
    $address = trim($address);
    if ($address === '') {
        return 'Central';
    }
    $part = explode(',', $address)[0];
    return trim($part) !== '' ? trim($part) : 'Central';
}

if (seeders_is_entry(__FILE__)) {
    seed_animals($ctx);
}
