-- Outbound mail queue. Subscriber announcements used to go out inline during
-- the publish request, which meant a provider's per-second limit silently cut
-- the fan-out short and the rest of the list was never notified. Mail now
-- lands here and mail:queue-work delivers it at a paced rate with retries.

CREATE TABLE IF NOT EXISTS mail_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255) DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text LONGTEXT DEFAULT NULL,
    status ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0 COMMENT 'Delivery attempts made so far',
    max_attempts INT NOT NULL DEFAULT 3 COMMENT 'Give up and mark failed past this',
    last_error TEXT DEFAULT NULL COMMENT 'Transport complaint from the most recent failure',
    claim_token CHAR(32) DEFAULT NULL COMMENT 'Identifies which worker run owns a sending row',
    related_type VARCHAR(50) DEFAULT NULL COMMENT 'What triggered this mail, e.g. post',
    related_id INT DEFAULT NULL,
    next_attempt_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Backoff gate; worker ignores rows dated ahead',
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mail_queue_claim (status, next_attempt_at),
    INDEX idx_mail_queue_token (claim_token),
    INDEX idx_mail_queue_related (related_type, related_id),
    INDEX idx_mail_queue_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Outbound email queue drained by the mail:queue-work cron worker';

-- Dedicated control panel permission. Administrators pass Gate checks by role,
-- so this exists to let a non-admin operations role be granted the queue view
-- without also handing over site settings.
INSERT INTO permissions (permission_name, permission_slug, resource, action, description) VALUES
('Manage Mail Queue', 'manage_mail_queue', 'mail', 'manage', 'Inspect the outbound mail queue and retry failed sends')
ON DUPLICATE KEY UPDATE description = VALUES(description);
