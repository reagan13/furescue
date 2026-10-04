ALTER TABLE elearning_modules
    ADD COLUMN quiz_json JSON NULL AFTER content_body;

ALTER TABLE elearning_progress
    ADD COLUMN read_pct TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN quiz_passed TINYINT(1) NOT NULL DEFAULT 0 AFTER read_pct,
    ADD COLUMN badge_earned TINYINT(1) NOT NULL DEFAULT 0 AFTER quiz_passed;
