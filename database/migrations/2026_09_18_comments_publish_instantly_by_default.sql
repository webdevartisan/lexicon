-- New blogs publish comments instantly and the owner moderates afterwards.
-- Existing blogs keep whatever their owner chose.
ALTER TABLE blog_settings
    MODIFY comments_auto_publish BOOLEAN NOT NULL DEFAULT TRUE
    COMMENT 'When on, new comments publish instantly; owner moderates retroactively';
