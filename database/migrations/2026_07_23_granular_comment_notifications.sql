-- ============================================================
-- Granular Comment Notifications — 2026-07-23
-- Splits the single notify_comments toggle into four, so a
-- reply to your own comment can be told apart from the general
-- firehose of every comment on a blog you own.
--
-- Each new column is backfilled from notify_comments so anyone
-- who had already opted out stays opted out of all four.
-- notify_comments is left in place but is no longer read or
-- written; it is dropped alongside notify_likes later.
-- ============================================================

START TRANSACTION;

ALTER TABLE user_preferences
    ADD COLUMN notify_comment_replies BOOLEAN NOT NULL DEFAULT TRUE
        COMMENT 'Email me when someone replies to a comment I wrote',
    ADD COLUMN notify_comments_authored BOOLEAN NOT NULL DEFAULT TRUE
        COMMENT 'Email me when someone comments on a post I wrote',
    ADD COLUMN notify_comments_moderation BOOLEAN NOT NULL DEFAULT TRUE
        COMMENT 'Email me when a comment is held for my approval',
    ADD COLUMN notify_comments_blog BOOLEAN NOT NULL DEFAULT TRUE
        COMMENT 'Email me about every comment on a blog I own';

UPDATE user_preferences
SET notify_comment_replies = notify_comments,
    notify_comments_authored = notify_comments,
    notify_comments_moderation = notify_comments,
    notify_comments_blog = notify_comments;

INSERT INTO migrations (filename) VALUES ('2026_07_23_granular_comment_notifications.sql');

COMMIT;
