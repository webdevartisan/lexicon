-- ----------------------------------------------------------------------------
-- Two-factor sign-in with an authenticator app. The secret is stored encrypted
-- (see App\Services\TwoFactor\SecretBox), the recovery codes only as hashes.
-- two_factor_confirmed_at stays NULL until the first code from the app has been
-- accepted, so a half-finished setup never locks anybody out.

ALTER TABLE users
    ADD COLUMN two_factor_secret VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Encrypted authenticator secret; set during setup, kept after confirmation'
        AFTER email_verified_at,
    ADD COLUMN two_factor_confirmed_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'When the first code was accepted; two-factor is on when this is set'
        AFTER two_factor_secret,
    ADD COLUMN two_factor_last_step BIGINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Newest 30 second step already accepted, so a code works once'
        AFTER two_factor_confirmed_at,
    ADD COLUMN two_factor_recovery_codes TEXT NULL DEFAULT NULL
        COMMENT 'JSON list of sha256 hashes of the unused recovery codes'
        AFTER two_factor_last_step;
