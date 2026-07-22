-- ============================================================
-- Split Review Notifications — 2026-07-23
-- notify_post_status mixed two audiences: things that happen to
-- your own post (approved, changes requested, published, reset),
-- and review work handed to you (a draft to review, being
-- assigned, being unassigned). This carves the review-work half
-- into its own toggle so a contributor who never reviews can mute
-- it without losing news about their own posts.
--
-- notify_post_status keeps the author-facing half. The new column
-- is backfilled from it so nobody's existing choice changes.
-- ============================================================

START TRANSACTION;

ALTER TABLE user_preferences
    ADD COLUMN notify_review_requests BOOLEAN NOT NULL DEFAULT TRUE
        COMMENT 'Email me when a post is handed to me to review, or my review assignment changes';

UPDATE user_preferences
SET notify_review_requests = notify_post_status;

INSERT INTO migrations (filename) VALUES ('2026_07_23_split_review_notifications.sql');

COMMIT;
