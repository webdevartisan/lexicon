-- ----------------------------------------------------------------------------
-- Sign-up proves the address. email_verified_at is NULL until the link mailed
-- at registration is followed. Everyone who already has an account is marked
-- verified, so turning the gate on locks nobody out.

ALTER TABLE users
    ADD COLUMN email_verified_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When the address was proven. Only self-service sign-up inserts NULL (unproven until the link is followed); every other way an account is made counts as proven'
        AFTER age_confirmed_at;

UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL;

CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL COMMENT 'sha256 of the token that was emailed; the raw token is never stored',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email_verification_user (user_id),
    INDEX idx_email_verification_token (token),
    INDEX idx_email_verification_expires (expires_at),
    CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Confirmation links for new accounts; one live link per user, a resend replaces it';
