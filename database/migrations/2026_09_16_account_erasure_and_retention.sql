-- Deleting an account now removes the account row. These links recorded who did
-- something to someone else's record, so the record stays and forgets the person.
ALTER TABLE blog_users
    DROP FOREIGN KEY blog_users_ibfk_3,
    MODIFY assigned_by INT NULL COMMENT 'User ID who granted this blog membership';
ALTER TABLE blog_users
    ADD CONSTRAINT blog_users_ibfk_3 FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE post_reviewers
    DROP FOREIGN KEY post_reviewers_ibfk_3,
    MODIFY assigned_by INT NULL;
ALTER TABLE post_reviewers
    ADD CONSTRAINT post_reviewers_ibfk_3 FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE blog_invitations
    DROP FOREIGN KEY blog_invitations_ibfk_2,
    MODIFY invited_by INT NULL;
ALTER TABLE blog_invitations
    ADD CONSTRAINT blog_invitations_ibfk_2 FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL;

-- Reports stay with moderators after the reporter leaves.
ALTER TABLE post_reports
    DROP FOREIGN KEY post_reports_ibfk_2,
    MODIFY user_id INT NULL;
ALTER TABLE post_reports
    ADD CONSTRAINT post_reports_ibfk_2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE comment_reports
    DROP FOREIGN KEY comment_reports_ibfk_2,
    MODIFY user_id INT NULL;
ALTER TABLE comment_reports
    ADD CONSTRAINT comment_reports_ibfk_2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Sign-in and every request treat anything but 1 as suspended, so NULL must not exist.
ALTER TABLE users
    MODIFY is_active BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN age_confirmed_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When the person confirmed the minimum age at sign-up' AFTER is_active;

-- Reviews and submissions kept after an account is deleted belong to this account. It is
-- shared by every deleted person, so kept records cannot be grouped back by author.
INSERT INTO users (handle, email, password, display_name_cached, is_active)
VALUES ('deleted-user', 'deleted-user@lexicon.invalid', '', 'Deleted user', 0);

INSERT INTO reserved_handles (handle, match_type, reason) VALUES
    ('deleted', 'contains', 'Reads as a removed account')
ON DUPLICATE KEY UPDATE match_type = VALUES(match_type), reason = VALUES(reason);

-- Never read or written. Export is served on request and deletion happens on confirm.
DROP TABLE data_export_requests;
DROP TABLE account_deletion_requests;

INSERT INTO scheduled_tasks (label, command, arguments, schedule_type, interval_minutes, run_at, schedule_timezone, timeout_seconds, is_active, next_run_at) VALUES
('Apply data retention periods', 'privacy:prune', NULL, 'daily', NULL, '03:50:00', 'UTC', 600, 1, UTC_TIMESTAMP());
