<?php

/**
 * Full FurEscue demo dataset (idempotent).
 *
 *   php seeders\seed.php
 *
 * Domain seeders (also runnable on their own after accounts exist):
 *   seed_permissions.php  permission slugs
 *   seed_accounts.php     users, rescuer approvals, duty status
 *   seed_reports.php      community reports
 *   seed_cases.php        rescue cases + activity
 *   seed_animals.php      animals + field status
 *   seed_health.php       medical records, vitals, documents
 *   seed_adoptions.php    listings + applications
 *   seed_messages.php     in-app messages
 *   seed_notifications.php
 *   seed_elearning.php    modules + progress
 *   seed_demo_roles.php   extra reports/cases for juan@ and rescuer@
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/catalog.php';
require_once __DIR__ . '/lib/grant.php';
require_once __DIR__ . '/seed_permissions.php';
require_once __DIR__ . '/seed_accounts.php';
require_once __DIR__ . '/seed_elearning.php';
require_once __DIR__ . '/seed_reports.php';
require_once __DIR__ . '/seed_cases.php';
require_once __DIR__ . '/seed_animals.php';
require_once __DIR__ . '/seed_health.php';
require_once __DIR__ . '/seed_adoptions.php';
require_once __DIR__ . '/seed_messages.php';
require_once __DIR__ . '/seed_notifications.php';
require_once __DIR__ . '/seed_demo_roles.php';

$started = microtime(true);
$ctx->pdo->beginTransaction();

try {
    seed_permissions($pdo);
    seed_accounts($ctx, false);
    echo 'Permissions granted this run: ' . seed_grant_role_permissions($ctx) . "\n";
    seed_elearning($ctx);
    seed_reports($ctx);
    seed_cases($ctx);
    seed_animals($ctx);
    seed_health($ctx);
    seed_adoptions($ctx);
    seed_messages($ctx);
    seed_notifications($ctx);
    seed_demo_roles($ctx);
    $ctx->pdo->commit();
} catch (Throwable $e) {
    if ($ctx->pdo->inTransaction()) {
        $ctx->pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    throw $e;
}

$ctx->loadUsers();
$pw = SeedContext::DEV_PASSWORD;
echo "Seeded successfully in " . number_format(microtime(true) - $started, 1) . "s.\n";
echo "  admin:     admin@furescue.local / {$pw}\n";
echo "  rescuers:  rescuer@furescue.local … rescuer5@ (on duty), rescuer6–7 pending / {$pw}\n";
echo "  residents: juan@, maria@, ana@, pedro@, rosa@, miguel@furescue.local / {$pw}\n";
$ctx->echoCount('users', 'SELECT COUNT(*) FROM users');
$ctx->echoCount('reports', 'SELECT COUNT(*) FROM reports');
$ctx->echoCount('  verified', "SELECT COUNT(*) FROM reports WHERE status = 'verified'");
$ctx->echoCount('  pending', "SELECT COUNT(*) FROM reports WHERE status = 'pending_verification'");
$ctx->echoCount('cases', 'SELECT COUNT(*) FROM cases');
$ctx->echoCount('animals', 'SELECT COUNT(*) FROM animals');
$ctx->echoCount('medical records', 'SELECT COUNT(*) FROM animal_medical_records');
$ctx->echoCount('vitals_log', 'SELECT COUNT(*) FROM vitals_log');
$ctx->echoCount('adoption listings', 'SELECT COUNT(*) FROM adoption_listings');
$ctx->echoCount('adoptions', 'SELECT COUNT(*) FROM adoptions');
$ctx->echoCount('messages', 'SELECT COUNT(*) FROM messages');
$ctx->echoCount('notifications', 'SELECT COUNT(*) FROM notifications');
$ctx->echoCount('e-learning modules', 'SELECT COUNT(*) FROM elearning_modules');
$ctx->echoCount('e-learning progress', 'SELECT COUNT(*) FROM elearning_progress');
