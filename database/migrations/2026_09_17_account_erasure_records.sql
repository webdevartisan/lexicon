-- A private, admin-only trail of who an erased account used to be, kept only long
-- enough to answer a late report or a legal request. Not shown to anyone publicly,
-- and pruned automatically by privacy:prune once it ages past config/privacy.php's
-- retention period. This does not weaken erasure: after that window it is gone,
-- same as everything else the account owned.
CREATE TABLE IF NOT EXISTS account_erasure_records (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    original_user_id  INT NOT NULL COMMENT 'The users.id that no longer exists',
    handle            VARCHAR(100) NOT NULL,
    email             VARCHAR(150) NOT NULL,
    post_ids          JSON NOT NULL COMMENT 'Posts authored by this account at the moment of erasure',
    comment_ids       JSON NOT NULL COMMENT 'Comments authored by this account at the moment of erasure',
    erased_by         INT NULL COMMENT 'Who triggered it: the account itself, or an administrator',
    erased_by_ip      VARCHAR(45) NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_erasure_record_user (original_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Admin-only, time-limited record of erased accounts, for abuse reports and legal requests';
