-- ----------------------------------------------------------------------------
-- Notification scoping: personal / content / admin
-- ----------------------------------------------------------------------------
-- Every notification used to land in the same bell regardless of who it was
-- actually for. `scope` decides which inbox a row belongs in:
--   personal - happened to the recipient or their own content (replies,
--              moderation action against them, their post's review status)
--   content  - a blog-management concern the dashboard already contextualizes
--              (a post submitted for review, a comment awaiting moderation,
--              blog-wide comment activity)
--   admin    - platform-operations events with no per-user relevance
--              (report thresholds, mail queue failures, a stalled scheduler)
--
-- Existing rows are backfilled by their `type`. Anything not listed defaults
-- to 'personal', which is the safer default: it is better for a legacy row to
-- show up in someone's personal inbox than to vanish from every inbox.
-- ----------------------------------------------------------------------------

ALTER TABLE notifications
    ADD COLUMN scope ENUM('personal','content','admin') NOT NULL DEFAULT 'personal' AFTER type,
    ADD INDEX idx_user_scope_read (user_id, scope, read_at);

UPDATE notifications
SET scope = 'content'
WHERE type IN ('post.submitted', 'post.submitted_unassigned', 'comment.awaiting_moderation', 'comment.on_blog');
