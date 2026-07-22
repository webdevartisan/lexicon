-- ============================================================
-- Drop Dead Notification Columns — 2026-07-23
-- notify_likes never had a feature behind it (there is no
-- post-liked email), and notify_comments was superseded by the
-- four notify_comment* columns, which were backfilled from it
-- before it stopped being read or written. Both are now dead.
-- ============================================================

START TRANSACTION;

ALTER TABLE user_preferences
    DROP COLUMN notify_likes,
    DROP COLUMN notify_comments;

INSERT INTO migrations (filename) VALUES ('2026_07_23_drop_dead_notification_columns.sql');

COMMIT;
