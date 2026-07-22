-- Delivery tiers for the mail queue.
--
-- Until now only the subscriber fan-out went through the queue and everything
-- else was sent inline during the request. Inline sending has no retry, so a
-- provider hiccup on a password reset lost it outright and nobody found out.
-- All mail goes through the queue from here, which means the queue has to be
-- able to tell a reset from a newsletter.
--
-- Tier comes from the Mailable class, never from a setting. Urgency follows
-- from what an email is, and a control for it would mostly be a way to
-- accidentally demote the one email nobody can afford to lose. What operators
-- do control is pace: each tier has its own scheduled worker, so a slow
-- provider is handled by running the bulk worker less often rather than by
-- holding up password resets.

ALTER TABLE mail_queue
    ADD COLUMN tier ENUM('critical','standard','bulk') NOT NULL DEFAULT 'standard'
        COMMENT 'Set by the Mailable class, decides which worker drains the row'
        AFTER status;

-- The worker claims by tier now, so tier leads the index. Without this the
-- critical worker would scan rows belonging to a large bulk send.
ALTER TABLE mail_queue
    DROP INDEX idx_mail_queue_claim,
    ADD INDEX idx_mail_queue_claim (tier, status, next_attempt_at);

-- Existing queued rows are all subscriber announcements, which is the bulk tier.
UPDATE mail_queue SET tier = 'bulk' WHERE related_type = 'post';

-- One worker per tier, each on its own schedule. Critical runs every minute so
-- a reset lands in seconds, bulk runs slower so a large send cannot crowd it
-- out. The single mail:queue-work task seeded with the scheduler is replaced,
-- since it would otherwise drain every tier at one pace and undo the point.
DELETE FROM scheduled_tasks WHERE command = 'mail:queue-work';

INSERT INTO scheduled_tasks (label, command, arguments, schedule_type, interval_minutes, schedule_timezone, timeout_seconds, is_active, next_run_at) VALUES
('Mail, critical', 'mail:queue-work', '{"tier":"critical"}', 'every_minute', NULL, 'UTC', 120, 1, UTC_TIMESTAMP()),
('Mail, standard', 'mail:queue-work', '{"tier":"standard"}', 'every_n_minutes', 5, 'UTC', 300, 1, UTC_TIMESTAMP()),
('Mail, bulk', 'mail:queue-work', '{"tier":"bulk"}', 'every_n_minutes', 10, 'UTC', 600, 1, UTC_TIMESTAMP());
