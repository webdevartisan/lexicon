-- ----------------------------------------------------------------------------
-- User suspension (temporary + permanent) with cascade, and admin impersonation
-- ----------------------------------------------------------------------------
-- Suspension keeps users.is_active as the single sign-in gate, so Auth needs no
-- change to block a suspended account or to drop the sessions it already has.
-- The columns beside it carry why and until when; user_suspensions keeps the
-- history the account detail page reads.
--
-- The blog cascade reuses blogs.status rather than adding a second flag: every
-- public query already filters on status = 'published', so one new enum value
-- removes a blog from all 43 of them at once, with nothing left to forget.
-- status_before_suspension is what makes lifting exact rather than a guess.
-- ----------------------------------------------------------------------------

ALTER TABLE users
    ADD COLUMN suspended_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Set while a suspension is in force; NULL means not suspended',
    ADD COLUMN suspended_until TIMESTAMP NULL DEFAULT NULL COMMENT 'UTC end of a temporary suspension; NULL alongside suspended_at means permanent',
    ADD COLUMN suspension_reason VARCHAR(500) DEFAULT NULL,
    ADD COLUMN suspended_by INT DEFAULT NULL COMMENT 'Administrator who applied the suspension',
    ADD INDEX idx_suspended_until (suspended_until),
    ADD CONSTRAINT fk_users_suspended_by FOREIGN KEY (suspended_by) REFERENCES users(id) ON DELETE SET NULL;

-- Lexicon keeps no server-side session store, so there was no way to end an
-- account's other sessions. Each session remembers the epoch it signed in
-- under; bumping it here makes Auth drop every older session on its next request.
ALTER TABLE users
    ADD COLUMN session_epoch INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Bumped to invalidate every existing session for the account';

ALTER TABLE blogs
    MODIFY COLUMN status ENUM('draft','published','archived','suspended') NOT NULL DEFAULT 'draft',
    ADD COLUMN status_before_suspension VARCHAR(20) DEFAULT NULL COMMENT 'Status to restore when the owner suspension is lifted';

ALTER TABLE comments
    ADD COLUMN hidden_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft-hidden from public reads without touching the moderation status',
    ADD COLUMN hidden_reason VARCHAR(32) DEFAULT NULL COMMENT 'Why it is hidden, e.g. author_suspended',
    ADD INDEX idx_comment_hidden (hidden_at);

-- ----------------------------------------------------------------------------
-- Suspension history
-- ----------------------------------------------------------------------------
-- One row per suspension, closed rather than deleted when lifted, so the
-- account detail page can show what happened and who did it after the fact.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_suspensions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('temporary','permanent') NOT NULL,
    reason VARCHAR(500) DEFAULT NULL,
    suspended_by INT DEFAULT NULL,
    suspended_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL COMMENT 'UTC; NULL for a permanent suspension',
    lifted_at TIMESTAMP NULL DEFAULT NULL,
    lifted_by INT DEFAULT NULL COMMENT 'NULL when the lift was automatic',
    lift_kind ENUM('manual','automatic') DEFAULT NULL,
    blogs_hidden INT NOT NULL DEFAULT 0 COMMENT 'How many blogs the cascade hid, for the admin to verify against',
    comments_hidden INT NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (suspended_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (lifted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_open (user_id, lifted_at),
    INDEX idx_suspended_at (suspended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Suspension history per account, including lifted ones';

-- ----------------------------------------------------------------------------
-- Impersonation sessions
-- ----------------------------------------------------------------------------
-- Mandatory audit trail for "log in as". A row opens when impersonation starts
-- and closes when it ends, so an unclosed row means a session that was never
-- exited cleanly rather than one that never happened. No foreign keys, like
-- activity_log: the trail has to outlive the accounts it is about.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS impersonation_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL COMMENT 'The real actor',
    target_user_id INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    end_kind ENUM('exited','expired','logout') DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    INDEX idx_admin (admin_id, started_at),
    INDEX idx_target (target_user_id, started_at),
    INDEX idx_open (ended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Start and end of every admin impersonation session';

-- next_run_at must be set here: claimDue() requires it to be non-null, so a task
-- seeded without one is never picked up.
INSERT INTO scheduled_tasks (label, command, schedule_type, minute_of_hour, schedule_timezone, timeout_seconds, is_active, next_run_at)
VALUES ('Lift expired user suspensions', 'users:lift-due-suspensions', 'hourly', 5, 'UTC', 120, 1, UTC_TIMESTAMP());
