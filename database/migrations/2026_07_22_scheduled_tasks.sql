-- Task scheduler. Cron gets one entry, `php cli schedule:run`, and everything
-- else is configured from the control panel. Before this, every recurring job
-- needed its own crontab line and there was no way to see whether it had run.
--
-- Timezone note: these tables store UTC in plain DATETIME columns and the code
-- compares against UTC_TIMESTAMP(), never NOW(). The connection does not pin a
-- session time_zone, so NOW() is whatever the MySQL host is set to while PHP
-- runs in UTC. TIMESTAMP columns would also shift under the session zone on
-- read and write, which is why DATETIME is used for anything the scheduler
-- reasons about. created_at and updated_at stay TIMESTAMP to match the other
-- tables since nothing compares them against PHP time.

CREATE TABLE IF NOT EXISTS scheduled_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL COMMENT 'Operator facing name, e.g. Subscriber mail',
    command VARCHAR(100) NOT NULL COMMENT 'Console command name, checked against the kernel allowlist',
    arguments JSON DEFAULT NULL COMMENT 'Validated against the command declared schema',

    schedule_type ENUM('every_minute','every_n_minutes','hourly','daily') NOT NULL DEFAULT 'every_minute',
    interval_minutes SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Used by every_n_minutes',
    minute_of_hour TINYINT UNSIGNED DEFAULT NULL COMMENT 'Used by hourly',
    run_at TIME DEFAULT NULL COMMENT 'Used by daily, local wall clock in schedule_timezone',
    schedule_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC' COMMENT 'IANA zone the rule is written in, so daily times survive DST',

    timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 300 COMMENT 'Runaway bound, the reaper kills the process past this',
    is_active TINYINT(1) NOT NULL DEFAULT 1,

    next_run_at DATETIME DEFAULT NULL COMMENT 'UTC, computed from the rule after every run',
    claim_token CHAR(32) DEFAULT NULL COMMENT 'Held while a run is in flight, doubles as the overlap lock',
    claimed_at DATETIME DEFAULT NULL COMMENT 'UTC, how the reaper spots an abandoned claim',
    current_run_id INT DEFAULT NULL COMMENT 'Open run row, drives the running indicator in the list',

    last_run_at DATETIME DEFAULT NULL COMMENT 'UTC',
    last_status VARCHAR(20) DEFAULT NULL,
    last_duration_ms INT UNSIGNED DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_scheduled_tasks_due (is_active, next_run_at, claim_token),
    INDEX idx_scheduled_tasks_claim (claim_token),
    INDEX idx_scheduled_tasks_command (command)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Recurring console tasks dispatched by the schedule:run cron entry';

-- One row per execution. Kept separate from the task so history survives a
-- schedule change and so the list view never has to load captured output.
CREATE TABLE IF NOT EXISTS scheduled_task_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    trigger_source ENUM('cron','manual','pseudo') NOT NULL DEFAULT 'cron' COMMENT 'Answers whether cron really fired or someone pressed the button',
    status ENUM('running','success','failed','timed_out','skipped_locked') NOT NULL DEFAULT 'running',
    pid INT UNSIGNED DEFAULT NULL COMMENT 'Child process, so a later tick can kill a hung detached run',
    exit_code SMALLINT DEFAULT NULL,
    started_at DATETIME(3) NOT NULL COMMENT 'UTC, millisecond precision so short runs do not all report as one second',
    finished_at DATETIME(3) DEFAULT NULL COMMENT 'UTC',
    duration_ms INT UNSIGNED DEFAULT NULL,
    output MEDIUMTEXT DEFAULT NULL COMMENT 'Captured stdout and stderr, truncated on write',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_task_runs_task FOREIGN KEY (task_id) REFERENCES scheduled_tasks(id) ON DELETE CASCADE,
    INDEX idx_task_runs_history (task_id, id),
    INDEX idx_task_runs_status (status),
    INDEX idx_task_runs_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Execution history for scheduled_tasks, pruned by schedule:prune-runs';

-- Lets an operations role inspect and manage schedules without also being
-- handed site settings. Administrators pass on role anyway.
INSERT INTO permissions (permission_name, permission_slug, resource, action, description) VALUES
('Manage Scheduled Tasks', 'manage_scheduled_tasks', 'system', 'manage', 'Configure recurring tasks, run them by hand, and read their output')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Ships with the jobs the platform actually needs, so a fresh install works
-- after the single crontab line and nothing has to be configured by hand.
INSERT INTO scheduled_tasks (label, command, schedule_type, interval_minutes, run_at, schedule_timezone, timeout_seconds, is_active, next_run_at) VALUES
('Publish scheduled posts', 'posts:publish-due', 'every_minute', NULL, NULL, 'UTC', 120, 1, UTC_TIMESTAMP()),
('Outbound mail', 'mail:queue-work', 'every_minute', NULL, NULL, 'UTC', 300, 1, UTC_TIMESTAMP()),
('Prune expired cache', 'cache:prune', 'daily', NULL, '03:20:00', 'UTC', 600, 1, UTC_TIMESTAMP()),
('Prune old notifications', 'notifications:prune', 'daily', NULL, '03:40:00', 'UTC', 600, 1, UTC_TIMESTAMP()),
('Prune task history', 'schedule:prune-runs', 'daily', NULL, '04:00:00', 'UTC', 300, 1, UTC_TIMESTAMP());
