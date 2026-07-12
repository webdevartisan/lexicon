-- Per-blog toggle to publish new top-level comments instantly, mirroring
-- replies_auto_publish. Off by default to keep the moderation-first behavior.

ALTER TABLE blog_settings
    ADD COLUMN comments_auto_publish TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'When on, new comments publish instantly; owner moderates retroactively' AFTER comments_enabled;
