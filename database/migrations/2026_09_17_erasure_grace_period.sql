-- Self-requested erasure is deferred: the account is deactivated (locked out)
-- right away, but AccountErasureService does not run until the grace period
-- in config/privacy.php has passed, so a first offense nobody has reported
-- yet still has a window to be caught before the content is gone.
CREATE TABLE IF NOT EXISTS pending_erasures (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT NOT NULL UNIQUE,
    erased_by         INT NULL COMMENT 'Who requested it: always the account itself today',
    erased_by_ip      VARCHAR(45) NULL,
    scheduled_for     DATETIME NOT NULL COMMENT 'When privacy:process-due-erasures may run this one',
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pending_erasure_due (scheduled_for)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Self-requested account deletions waiting out a grace period before erasure runs';

INSERT INTO scheduled_tasks (label, command, schedule_type, run_at, schedule_timezone, timeout_seconds, is_active)
VALUES ('Process due account erasures', 'privacy:process-due-erasures', 'daily', '04:10:00', 'UTC', 300, 1);
