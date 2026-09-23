-- ----------------------------------------------------------------------------
-- Mail queue: cancel and resend
-- ----------------------------------------------------------------------------
-- 'cancelled' is a genuine status, not a reused one, so claimBatch()'s
-- `WHERE status = 'pending'` naturally excludes it - the worker can never pick
-- a cancelled row up, without touching the claim query at all.
--
-- resent_from_id traces a resend back to the row it repeated, so admins can
-- see a send's history without the original row's status or sent_at ever
-- being overwritten.
-- ----------------------------------------------------------------------------

ALTER TABLE mail_queue
    MODIFY COLUMN status ENUM('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL AFTER sent_at,
    ADD COLUMN cancelled_by INT NULL DEFAULT NULL AFTER cancelled_at,
    ADD COLUMN resent_from_id INT NULL DEFAULT NULL AFTER cancelled_by,
    ADD INDEX idx_mail_queue_resent (resent_from_id);
