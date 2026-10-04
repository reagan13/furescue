<?php

/**
 * Extra reports for named residents and cases for rescuer@ so phone
 * demo logins are not empty after the bulk seeder spreads rows around.
 *
 *   php seeders\seed_demo_roles.php
 */

require_once __DIR__ . '/lib/bootstrap.php';

function seed_demo_roles(SeedContext $ctx): void
{
    $ctx->loadUsers();
    $admin = $ctx->adminId;
    $rescuer = $ctx->userId('rescuer@furescue.local');
    if (!$admin) {
        echo "Demo role rows skipped: seed accounts first.\n";
        return;
    }

    $addedReports = 0;
    $addedCases = 0;

    $residentPacks = [
        'juan@furescue.local' => [
            'brgy' => ['Central', 6.9550, 126.2020],
            'rows' => [
                ['tag' => 'J-01', 'status' => 'pending_verification', 'case' => null,
                    'desc' => 'Injured Aspin limping near Central plaza. Still there this morning.'],
                ['tag' => 'J-02', 'status' => 'verified', 'case' => 'assigned',
                    'desc' => 'Thin Puspin by the Central market stall. Took water from a bowl.'],
                ['tag' => 'J-03', 'status' => 'verified', 'case' => 'in_progress',
                    'desc' => 'Mother dog and two pups behind the waiting shed on Purok 2.'],
                ['tag' => 'J-04', 'status' => 'dismissed', 'case' => null,
                    'desc' => 'Reported a dog by the chapel; owner later confirmed they took it home.'],
                ['tag' => 'J-05', 'status' => 'verified', 'case' => 'resolved',
                    'desc' => 'Stray dog with an ear wound at Purok 2, Barangay Central.'],
            ],
        ],
        'maria@furescue.local' => [
            'brgy' => ['Dahican', 6.9412, 126.2300],
            'rows' => [
                ['tag' => 'M-01', 'status' => 'verified', 'case' => 'assigned',
                    'desc' => 'Beach dog with a torn collar near Dahican surf side.'],
                ['tag' => 'M-02', 'status' => 'pending_verification', 'case' => null,
                    'desc' => 'Cat colony by the Dahican road, one kitten not moving much.'],
            ],
        ],
        'ana@furescue.local' => [
            'brgy' => ['Mayo', 6.9610, 126.1650],
            'rows' => [
                ['tag' => 'A-01', 'status' => 'verified', 'case' => 'in_progress',
                    'desc' => 'Large Aspin blocking the Mayo purok path after a dog fight.'],
            ],
        ],
    ];

    foreach ($residentPacks as $email => $pack) {
        $residentId = $ctx->userId($email);
        if (!$residentId) {
            continue;
        }
        $brgy = $pack['brgy'];
        foreach ($pack['rows'] as $i => $row) {
            $marker = 'Demo seed ' . $row['tag'];
            if ($ctx->rowExists('reports', 'animal_description LIKE ?', ['%' . $marker . '%'])) {
                continue;
            }
            $desc = $row['desc'] . ' ' . $marker . '.';
            $lat = (float) $brgy[1] + ($i * 0.0004);
            $lng = (float) $brgy[2] + ($i * 0.0003);
            $createdAt = $ctx->daysAgo(2 + $i, 12 + $i);
            $day = substr($createdAt, 0, 10);
            $hash = \App\Services\DedupService::contentHash($desc, $lat, $lng, $day);

            $payload = [
                'resident_id' => $residentId,
                'animal_description' => $desc,
                'latitude' => $lat,
                'longitude' => $lng,
                'address_text' => $brgy[0] . ', City of Mati, Davao Oriental',
                'content_hash' => $hash,
                'created_at' => $createdAt,
            ];

            if ($row['status'] === 'pending_verification') {
                $payload['validation_status'] = 'pending';
                $payload['status'] = 'pending_verification';
            } elseif ($row['status'] === 'dismissed') {
                $payload['validation_status'] = 'invalid';
                $payload['status'] = 'dismissed';
                $payload['dismiss_reason'] = 'Owner retrieved the animal before a field visit.';
                $payload['verified_by'] = $admin;
                $payload['verified_at'] = $ctx->daysAgo(1, 6);
            } else {
                $payload['validation_status'] = 'validated';
                $payload['status'] = 'verified';
                $payload['verified_by'] = $admin;
                $payload['verified_at'] = date('Y-m-d H:i:s', strtotime($createdAt) + 7200);
            }

            $reportId = $ctx->insert('reports', $payload);
            $addedReports++;

            $caseStatus = $row['case'];
            if ($caseStatus === null || !$rescuer || $ctx->rowExists('cases', 'report_id = ?', [$reportId])) {
                continue;
            }

            $notes = $caseStatus === 'resolved'
                ? 'Animal contained and brought to the City Vet holding kennel. Demo seed case.'
                : null;
            $caseId = $ctx->insert('cases', [
                'report_id' => $reportId,
                'assigned_rescuer_id' => $rescuer,
                'assigned_by' => $admin,
                'status' => $caseStatus,
                'resolution_notes' => $notes,
                'created_at' => $createdAt,
            ]);
            seed_cases_activity($ctx, $caseId, $caseStatus, $rescuer, $createdAt);
            $addedCases++;
        }
    }

    echo "Demo role rows: {$addedReports} reports, {$addedCases} cases for named resident/rescuer logins.\n";
}

if (seeders_is_entry(__FILE__)) {
    require_once __DIR__ . '/seed_cases.php';
    seed_demo_roles($ctx);
}
