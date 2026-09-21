-- Part D of platform moderation: a moderator can pause someone's reporting
-- after warning them about unfounded reports (DSA Art. 23). The warning and the
-- pause history live in activity_log; this column is only the current state.
ALTER TABLE users
    ADD COLUMN reports_paused_until TIMESTAMP NULL DEFAULT NULL
        COMMENT 'UTC; reports from this account are refused until then'
        AFTER suspended_until;
