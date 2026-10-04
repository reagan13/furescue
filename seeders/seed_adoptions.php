<?php

/**
 * Adoption listings and applications.
 *
 *   php seeders\seed_adoptions.php
 */

require_once __DIR__ . '/lib/bootstrap.php';

function seed_adoptions(SeedContext $ctx): void
{
    $ctx->loadUsers();
    $ctx->loadAnimals();
    if (!$ctx->adminId || $ctx->residentIds === [] || $ctx->animals === []) {
        echo "Adoptions skipped: seed animals and residents first.\n";
        return;
    }

    $listed = seed_adoptions_listings($ctx);
    $apps = seed_adoptions_applications($ctx);
    $ctx->loadAdoptions();
    echo "Adoptions: {$listed} listings added, {$apps} applications added, "
        . $ctx->count('SELECT COUNT(*) FROM adoptions') . " applications total.\n";
}

function seed_adoptions_listings(SeedContext $ctx): int
{
    $eligible = [];
    $stmt = $ctx->pdo->query(
        "SELECT a.id
         FROM animals a
         JOIN animal_medical_records m ON m.animal_id = a.id
         WHERE a.deleted_at IS NULL
           AND a.adoption_status IN ('not_listed', 'available', 'pending')
           AND (
             (m.vaccination_records IS NOT NULL AND m.vaccination_records != '[]' AND m.vaccination_records != 'null')
             OR (m.vaccination_details IS NOT NULL AND m.vaccination_details != '[]' AND m.vaccination_details != 'null')
           )
           AND (m.weight_kg IS NOT NULL OR m.temperature_c IS NOT NULL)
         ORDER BY a.created_at ASC"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
        if (!$ctx->rowExists('adoption_listings', "animal_id = ? AND status IN ('pending_review','approved')", [$id])) {
            $eligible[] = $id;
        }
    }

    $targetListings = (int) floor(count($ctx->animals) * 0.45);
    $have = $ctx->count("SELECT COUNT(*) FROM adoption_listings WHERE status IN ('pending_review','approved')");
    $needed = max(0, $targetListings - $have);
    $toList = array_slice($eligible, 0, $needed);
    $created = 0;

    foreach ($toList as $i => $animalId) {
        $status = ($i % 8 === 0) ? 'pending_review' : 'approved';
        $ctx->insert('adoption_listings', [
            'animal_id' => $animalId,
            'posted_by' => $ctx->adminId,
            'status' => $status,
            'reviewed_by' => $status === 'approved' ? $ctx->adminId : null,
            'review_notes' => $status === 'approved'
                ? 'Health-ready and temperament-checked. Approved for public listing.'
                : null,
            'reviewed_at' => $status === 'approved' ? $ctx->daysAgo(1, 60) : null,
            'created_at' => $ctx->daysAgo(2, 90),
        ]);
        if ($status === 'approved') {
            $ctx->pdo->prepare("UPDATE animals SET adoption_status = 'available' WHERE id = ? AND adoption_status = 'not_listed'")
                ->execute([$animalId]);
        }
        $created++;
    }

    $heal = $ctx->pdo->exec(
        "UPDATE animals a
         INNER JOIN adoption_listings l ON l.animal_id = a.id AND l.status = 'approved'
         SET a.adoption_status = 'available'
         WHERE a.deleted_at IS NULL AND a.adoption_status = 'not_listed'"
    );
    if (is_int($heal) && $heal > 0) {
        echo "Adoption listings: marked {$heal} approved animals available.\n";
    }

    return $created;
}

function seed_adoptions_applications(SeedContext $ctx): int
{
    $listed = $ctx->ids(
        "SELECT animal_id FROM adoption_listings WHERE status = 'approved' ORDER BY created_at ASC"
    );
    if ($listed === []) {
        $listed = $ctx->animalIds;
    }
    if ($listed === []) {
        return 0;
    }

    $needed = max(0, SeedContext::TARGET_ADOPTIONS - $ctx->count('SELECT COUNT(*) FROM adoptions'));
    $statuses = ['pending', 'pending', 'approved', 'rejected', 'cancelled', 'completed', 'completed'];
    $created = 0;

    for ($i = 0; $i < $needed; $i++) {
        $animalId = $listed[$i % count($listed)];
        $applicant = $ctx->residentIds[$i % count($ctx->residentIds)];
        $status = $statuses[$i % count($statuses)];
        if ($ctx->rowExists('adoptions', 'animal_id = ? AND applicant_id = ?', [$animalId, $applicant])) {
            continue;
        }

        $nameStmt = $ctx->pdo->prepare('SELECT name FROM animals WHERE id = ?');
        $nameStmt->execute([$animalId]);
        $animalName = $nameStmt->fetchColumn() ?: 'this animal';

        $ctx->insert('adoptions', [
            'animal_id' => $animalId,
            'applicant_id' => $applicant,
            'message' => seed_adoptions_message((string) $animalName, $status),
            'status' => $status,
            'reviewed_by' => in_array($status, ['pending', 'cancelled'], true) ? null : $ctx->adminId,
            'reviewed_at' => in_array($status, ['pending', 'cancelled'], true) ? null : $ctx->daysAgo(1, 40),
            'completed_at' => $status === 'completed' ? $ctx->daysAgo(1, 30) : null,
            'rejection_reason' => $status === 'rejected'
                ? 'Home check could not confirm a secure fence / indoor setup for a newly rescued pet.'
                : null,
            'created_at' => $ctx->daysAgo(2, 70),
        ]);

        if ($status === 'completed') {
            $ctx->pdo->prepare("UPDATE animals SET adoption_status = 'adopted' WHERE id = ?")->execute([$animalId]);
        } elseif ($status === 'pending') {
            $ctx->pdo->prepare("UPDATE animals SET adoption_status = 'pending' WHERE id = ? AND adoption_status = 'available'")
                ->execute([$animalId]);
        }
        $created++;
    }

    return $created;
}

function seed_adoptions_message(string $name, string $status): string
{
    $lines = [
        "We live in a fenced home in Central and would like to adopt {$name}. Someone is home most afternoons.",
        "My children already finished the basic pet-care module. {$name} would have a quiet indoor space and regular vet visits.",
        "I work near City Hall and can bring {$name} back for follow-up vaccines. We had an aspin before who lived with us for 8 years.",
        "Requesting to meet {$name} this weekend. We can provide photos of the yard and the transport crate.",
    ];
    $extra = match ($status) {
        'cancelled' => ' I need to withdraw — we are moving barangays next month.',
        'rejected' => ' We are still willing to foster if a full adoption is not possible.',
        default => '',
    };
    return $lines[array_rand($lines)] . $extra;
}

if (seeders_is_entry(__FILE__)) {
    seed_adoptions($ctx);
}
