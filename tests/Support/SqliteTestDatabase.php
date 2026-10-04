<?php

namespace App\Tests\Support;

use PDO;

final class SqliteTestDatabase
{
    public static function env(): void
    {
        $_ENV['MATI_LAT_MIN'] = '6.89';
        $_ENV['MATI_LAT_MAX'] = '7.01';
        $_ENV['MATI_LNG_MIN'] = '126.13';
        $_ENV['MATI_LNG_MAX'] = '126.27';
        $_ENV['DEDUP_RADIUS_METERS'] = '50';
        $_ENV['DEDUP_TIME_WINDOW_HOURS'] = '24';
        $_ENV['DEVICE_API_KEY'] = 'test-device-key';
        $_ENV['APP_TESTING'] = '1';
    }

    public static function create(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('CURDATE', static fn (): string => date('Y-m-d'), 0);

        foreach (self::schema() as $ddl) {
            $pdo->exec($ddl);
        }

        return $pdo;
    }

    private static function schema(): array
    {
        return [
            "CREATE TABLE users (
                id TEXT PRIMARY KEY,
                full_name TEXT NOT NULL DEFAULT '',
                email TEXT UNIQUE,
                password_hash TEXT,
                auth_provider TEXT NOT NULL DEFAULT 'native',
                google_id TEXT,
                phone_number TEXT,
                address TEXT,
                role TEXT NOT NULL,
                account_status TEXT NOT NULL DEFAULT 'active',
                profile_photo_url TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE rescuer_approvals (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL UNIQUE,
                reviewed_by TEXT,
                decision TEXT,
                remarks TEXT,
                reviewed_at TEXT
            )",
            "CREATE TABLE reports (
                id TEXT PRIMARY KEY,
                resident_id TEXT NOT NULL,
                animal_description TEXT NOT NULL,
                photo_urls TEXT,
                latitude TEXT NOT NULL,
                longitude TEXT NOT NULL,
                address_text TEXT,
                content_hash TEXT NOT NULL,
                duplicate_of_report_id TEXT,
                validation_status TEXT NOT NULL DEFAULT 'pending',
                status TEXT NOT NULL DEFAULT 'pending_verification',
                dismiss_reason TEXT,
                verified_by TEXT,
                verified_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE cases (
                id TEXT PRIMARY KEY,
                report_id TEXT NOT NULL,
                assigned_rescuer_id TEXT,
                assigned_by TEXT,
                status TEXT NOT NULL DEFAULT 'assigned',
                resolution_notes TEXT,
                resolution_photos TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE case_activity_log (
                id TEXT PRIMARY KEY,
                case_id TEXT NOT NULL,
                actor_id TEXT NOT NULL,
                actor_role TEXT NOT NULL,
                action TEXT NOT NULL,
                notes TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE rescuer_duty_status (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT 'off_duty',
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE notifications (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                type TEXT NOT NULL,
                message TEXT NOT NULL,
                related_type TEXT,
                related_id TEXT,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE animals (
                id TEXT PRIMARY KEY,
                name TEXT,
                species TEXT NOT NULL,
                breed_type TEXT NOT NULL,
                sex TEXT NOT NULL,
                age_estimate TEXT,
                birth_date TEXT,
                color_markings TEXT,
                barangay TEXT,
                description TEXT,
                photo_urls TEXT,
                model_3d_url TEXT,
                photo_360_set TEXT,
                adoption_status TEXT NOT NULL DEFAULT 'not_listed',
                source TEXT NOT NULL DEFAULT 'rescued_case',
                case_id TEXT,
                created_by TEXT,
                deleted_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE animal_field_status (
                id TEXT PRIMARY KEY,
                animal_id TEXT NOT NULL,
                case_id TEXT,
                rescue_status TEXT NOT NULL,
                health_status TEXT NOT NULL,
                logged_by TEXT NOT NULL,
                logged_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE animal_medical_records (
                id TEXT PRIMARY KEY,
                animal_id TEXT NOT NULL UNIQUE,
                medical_history_notes TEXT,
                vaccination_status TEXT NOT NULL DEFAULT 'none',
                vaccination_details TEXT,
                vaccine_protocols TEXT,
                vaccination_records TEXT,
                last_checkup_date TEXT,
                next_checkup_due TEXT,
                vaccination_expiry TEXT,
                \"condition\" TEXT,
                treatment_stage TEXT,
                deworming_status TEXT NOT NULL DEFAULT 'unknown',
                neutered TEXT NOT NULL DEFAULT 'unknown',
                weight_kg TEXT,
                temperature_c TEXT,
                vet_name TEXT,
                updated_by TEXT,
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE vitals_log (
                id TEXT PRIMARY KEY,
                animal_id TEXT NOT NULL,
                heart_rate_bpm INTEGER,
                respiratory_rate_bpm INTEGER,
                recorded_at TEXT NOT NULL DEFAULT (datetime('now')),
                source TEXT
            )",
            "CREATE TABLE animal_documents (
                id TEXT PRIMARY KEY,
                animal_id TEXT NOT NULL,
                name TEXT NOT NULL,
                doc_type TEXT,
                file_url TEXT,
                meta TEXT,
                uploaded_by TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE adoptions (
                id TEXT PRIMARY KEY,
                animal_id TEXT NOT NULL,
                applicant_id TEXT NOT NULL,
                message TEXT,
                status TEXT NOT NULL DEFAULT 'pending',
                rejection_reason TEXT,
                reviewed_by TEXT,
                reviewed_at TEXT,
                completed_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE adoption_listings (
                id TEXT PRIMARY KEY,
                animal_id TEXT NOT NULL,
                posted_by TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending_review',
                reviewed_by TEXT,
                review_notes TEXT,
                reviewed_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE messages (
                id TEXT PRIMARY KEY,
                sender_id TEXT NOT NULL,
                receiver_id TEXT NOT NULL,
                related_type TEXT NOT NULL,
                related_id TEXT NOT NULL,
                message_text TEXT NOT NULL,
                sent_at TEXT NOT NULL DEFAULT (datetime('now')),
                read_at TEXT
            )",
            "CREATE TABLE elearning_modules (
                id TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                category TEXT NOT NULL,
                content_body TEXT NOT NULL,
                quiz_json TEXT,
                published_status TEXT NOT NULL DEFAULT 'draft',
                created_by TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE elearning_progress (
                id TEXT PRIMARY KEY,
                resident_id TEXT NOT NULL,
                module_id TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'not_started',
                read_pct INTEGER NOT NULL DEFAULT 0,
                quiz_passed INTEGER NOT NULL DEFAULT 0,
                badge_earned INTEGER NOT NULL DEFAULT 0,
                completed_at TEXT
            )",
        ];
    }
}
