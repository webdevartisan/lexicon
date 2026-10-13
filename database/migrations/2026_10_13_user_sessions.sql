-- ----------------------------------------------------------------------------
-- Login sessions
-- ----------------------------------------------------------------------------
-- One row per signed-in session on every session driver: it is what the
-- "where you're signed in" list reads and what revoking deletes. A signed-in
-- session without its row is treated as ended. With the database driver the
-- session data lives here too, guests included (user_id NULL).
--
-- Times are Unix seconds, so idle and absolute limits compare the same way in
-- PHP and SQL whatever zone either side runs in.
CREATE TABLE IF NOT EXISTS user_sessions (
    id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 (hex) of the session id; the id itself is never stored',
    user_id INT DEFAULT NULL COMMENT 'NULL for a guest session (database driver only)',
    impersonated_by INT DEFAULT NULL COMMENT 'Administrator using this session to act as user_id',
    payload MEDIUMBLOB DEFAULT NULL COMMENT 'Session data, database driver only',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'Latest address the session was used from',
    user_agent VARCHAR(500) DEFAULT NULL,
    device VARCHAR(10) DEFAULT NULL COMMENT 'desktop, mobile, tablet or other',
    browser VARCHAR(40) DEFAULT NULL,
    browser_version VARCHAR(16) DEFAULT NULL,
    os VARCHAR(40) DEFAULT NULL,
    os_version VARCHAR(16) DEFAULT NULL,
    country CHAR(2) DEFAULT NULL,
    region VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    created_at INT UNSIGNED NOT NULL,
    signed_in_at INT UNSIGNED DEFAULT NULL,
    last_activity INT UNSIGNED NOT NULL,
    idle_seconds INT UNSIGNED NOT NULL,
    absolute_expires_at INT UNSIGNED DEFAULT NULL,
    expires_at INT UNSIGNED NOT NULL COMMENT 'Earlier of last_activity + idle_seconds and absolute_expires_at',
    PRIMARY KEY (id),
    INDEX idx_user_sessions_user (user_id, last_activity),
    INDEX idx_user_sessions_expires (expires_at),
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_sessions_impersonator FOREIGN KEY (impersonated_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Login sessions: the device list, and the session data for the database driver';

INSERT INTO scheduled_tasks (label, command, schedule_type, minute_of_hour, schedule_timezone, timeout_seconds, is_active, next_run_at)
SELECT 'Prune expired sessions', 'session:prune', 'hourly', 35, 'UTC', 120, 1, UTC_TIMESTAMP()
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM scheduled_tasks WHERE command = 'session:prune');
