<?php

/**
 * E-learning modules and resident progress.
 *
 *   php seeders\seed_elearning.php
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/elearning_lessons.php';

function seed_elearning(SeedContext $ctx): void
{
    $ctx->loadUsers();
    if (!$ctx->adminId) {
        echo "E-learning skipped: seed accounts first.\n";
        return;
    }

    seed_elearning_module_rows($ctx);
    seed_elearning_refresh_lessons($ctx);
    $ctx->loadModules();
    $progress = seed_elearning_progress($ctx);
    echo 'E-learning: ' . count($ctx->moduleIds) . " modules, {$progress} progress rows added.\n";
}

function seed_elearning_refresh_lessons(SeedContext $ctx): int
{
    $stmt = $ctx->pdo->prepare('UPDATE elearning_modules SET content_body = ?, quiz_json = ? WHERE title = ?');
    $updated = 0;
    foreach (seed_elearning_lesson_catalog() as $title => $lesson) {
        $stmt->execute([
            $lesson['body'],
            json_encode($lesson['quiz'], JSON_UNESCAPED_SLASHES),
            $title,
        ]);
        $updated += $stmt->rowCount() > 0 ? 1 : 0;
    }
    echo "E-learning lessons refreshed: {$updated}.\n";
    return $updated;
}

function seed_elearning_module_rows(SeedContext $ctx): int
{
    $rows = [
        ['Dog Behavior Basics', 'dog_behavior', 'published',
            'Learn how rescued dogs use body language — freeze, whale-eye, loose wag, and play bow. Approach sideways, let the dog sniff, and never reach over the head. This module is written for Mati residents who meet street dogs near markets and beaches.'],
        ['Cat Care 101', 'cat_behavior', 'published',
            'Shelter cats settle faster when they have a hiding box, vertical space, and a predictable feeding time. Cover how to tell a frightened puspin from a sick one, and when to leave the carrier open instead of forcing contact.'],
        ['Loose Leash Walking', 'basic_training', 'published',
            'A short, calm walk is better than a long, frantic one. Use a well-fitted flat collar or harness, reward slack leash, and stop when the dog pulls. Practice in a quiet purok before trying Dahican Beach on a weekend.'],
        ['Enrichment for Shelter Dogs', 'general_care', 'published',
            'Kibble in a bottle, a soaked towel to shred, and five minutes of scent work reduce kennel stress. Rotate toys so they stay interesting. Staff should log what each dog enjoys so adopters can continue it at home.'],
        ['Kitten Socialization', 'cat_behavior', 'published',
            'Between 2 and 9 weeks, kittens need gentle handling, household sounds, and positive food experiences. Keep litters together when possible. Wash hands between rooms to limit ringworm spread in the holding area.'],
        ['Emergency First Aid', 'general_care', 'draft',
            'Field checklist: scene safety, muzzle if the animal may bite, control bleeding with clean cloth, keep the animal warm, and call City Vet before moving a suspected fracture. Do not give human painkillers.'],
        ['Senior Dog Care', 'general_care', 'published',
            'Older aspins often need softer food, non-slip floors, and shorter walks. Watch for sudden thirst, cloudy eyes, or reluctance to rise. Adoption of seniors is encouraged — they usually housetrain faster than puppies.'],
        ['Feral Cat TNR Guide', 'cat_behavior', 'published',
            'Trap-neuter-return keeps community cat numbers stable. Bait at dusk, cover the trap once caught, and return the cat to the same colony after recovery. Coordinate with the barangay so residents do not relocate marked cats.'],
        ['Puppy Socialization', 'dog_behavior', 'published',
            'The critical window closes around 16 weeks. Expose puppies to umbrellas, tricycles, and friendly vaccinated dogs in short sessions. Never take an unvaccinated puppy to a crowded market.'],
        ['Separation Anxiety', 'basic_training', 'draft',
            'New adopters should practice short departures, a food puzzle at the door, and no dramatic goodbyes. Destruction and howling in the first week are common; this module lists when to ask City Vet for a behavior referral.'],
        ['Responsible Pet Ownership', 'general_care', 'published',
            'In Mati, registered dogs should be vaccinated against rabies and kept from roaming highways. Cover leash rules, waste pickup on the boulevard, and how to report a lost pet instead of abandoning it.'],
        ['Reading a Health Record', 'general_care', 'published',
            'Adopters should understand vaccine dates, next-due windows, deworming status, and why a “not healthy” flag blocks listing. Bring questions to the meet-and-greet instead of guessing dosages at home.'],
        ['Home Check Preparation', 'general_care', 'published',
            'Before applying, photograph the yard fence, indoor sleeping area, and food storage. Note other pets and children. A failed home check is usually about escape risk, not about how much you love animals.'],
        ['Handling Fearful Dogs', 'dog_behavior', 'published',
            'Crouch sideways, toss food behind the dog, and wait. Tight spaces and direct stares make fear worse. Rescuers should carry a slip lead and a towel; residents should wait for trained staff.'],
        ['Introducing a New Cat', 'cat_behavior', 'published',
            'Keep the newcomer in one room for several days. Swap bedding, feed on opposite sides of the door, then allow supervised visits. Rushing a resident cat often leads to urine marking and returned adoptions.'],
        ['Heatstroke and Rainy Season Care', 'general_care', 'published',
            'Pujada Bay weather swings from hot noon sun to long rains. Never leave a dog in a tricycle cab. Dry ears after rain to reduce infection, and watch panting that does not stop in the shade.'],
    ];

    $added = 0;
    foreach ($rows as $row) {
        if ($ctx->rowExists('elearning_modules', 'title = ?', [$row[0]])) {
            continue;
        }
        $ctx->insert('elearning_modules', [
            'title' => $row[0],
            'category' => $row[1],
            'published_status' => $row[2],
            'content_body' => $row[3],
            'created_by' => $ctx->adminId,
            'created_at' => $ctx->daysAgo(10, 200),
        ]);
        $added++;
    }
    return $added;
}

function seed_elearning_progress(SeedContext $ctx): int
{
    if ($ctx->moduleIds === [] || $ctx->residentIds === []) {
        return 0;
    }
    $needed = max(0, SeedContext::TARGET_PROGRESS - $ctx->count('SELECT COUNT(*) FROM elearning_progress'));
    $statuses = ['not_started', 'in_progress', 'completed', 'completed'];
    $added = 0;
    $moduleCount = count($ctx->moduleIds);
    $residentCount = count($ctx->residentIds);

    for ($i = 0; $i < $needed; $i++) {
        $resident = $ctx->residentIds[$i % $residentCount];
        $module = $ctx->moduleIds[intdiv($i, max(1, $residentCount)) % $moduleCount];
        if ($i >= $residentCount) {
            $module = $ctx->moduleIds[$i % $moduleCount];
            $resident = $ctx->residentIds[intdiv($i, $moduleCount) % $residentCount];
        }
        if ($ctx->rowExists('elearning_progress', 'resident_id = ? AND module_id = ?', [$resident, $module])) {
            continue;
        }
        $status = $statuses[$i % count($statuses)];
        $ctx->insert('elearning_progress', [
            'resident_id' => $resident,
            'module_id' => $module,
            'status' => $status,
            'completed_at' => $status === 'completed' ? $ctx->daysAgo(1, 90) : null,
        ]);
        $added++;
    }
    return $added;
}

if (seeders_is_entry(__FILE__)) {
    seed_elearning($ctx);
}
