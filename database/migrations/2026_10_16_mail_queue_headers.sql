-- ----------------------------------------------------------------------------
-- Queued mail keeps its extra headers, so new-post emails can carry
-- List-Unsubscribe and List-Unsubscribe-Post (RFC 8058 one-click).

ALTER TABLE mail_queue
    ADD COLUMN headers JSON DEFAULT NULL COMMENT 'Extra message headers, e.g. List-Unsubscribe' AFTER body_text;
