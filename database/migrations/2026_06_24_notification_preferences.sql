-- ============================================================
-- Notification Preferences Migration — 2026-06-24
-- Extends user_preferences with three notification toggles
-- to gate email delivery per event family.
-- ============================================================

START TRANSACTION;

ALTER TABLE user_preferences
    ADD COLUMN notify_post_status BOOLEAN NOT NULL DEFAULT TRUE
        COMMENT 'Email me on post review/approve/publish events',
    ADD COLUMN notify_role_changes BOOLEAN NOT NULL DEFAULT TRUE
        COMMENT 'Email me when my collaborator role changes or I am removed',
    ADD COLUMN notify_invites BOOLEAN NOT NULL DEFAULT TRUE
        COMMENT 'Email me when invited to a blog or when an invite I sent is declined';

COMMIT;
