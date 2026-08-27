-- Verified email changes. The address on the account only moves once the NEW
-- inbox proves control by following a token, so a stolen session cannot
-- silently redirect account recovery to an attacker's address.
--
-- One row per user: requesting another change replaces the pending one, which
-- is what the unique key on user_id enforces.

CREATE TABLE IF NOT EXISTS pending_email_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    new_email VARCHAR(150) NOT NULL,
    token VARCHAR(64) NOT NULL COMMENT 'sha256 of the token that was emailed; the raw token is never stored',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pending_email_user (user_id),
    INDEX idx_pending_email_token (token),
    INDEX idx_pending_email_expires (expires_at),
    CONSTRAINT fk_pending_email_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pending email address changes awaiting confirmation from the new address';
