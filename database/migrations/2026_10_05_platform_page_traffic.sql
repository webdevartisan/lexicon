-- The platform's own pages are counted too, and the platform, not each blog
-- owner, decides whether visits are counted at all.

ALTER TABLE blog_settings
    DROP COLUMN traffic_enabled;

ALTER TABLE traffic_hits
    MODIFY blog_id INT DEFAULT NULL COMMENT 'Empty for the platform''s own pages, and once a blog is deleted so its views still count for the site',
    MODIFY page_type ENUM('landing','post','archive','category','tag','home','discover','static_page','guide','profile','auth') NOT NULL COMMENT 'The first five are blog pages, the rest the platform''s own',
    MODIFY path VARCHAR(255) NOT NULL COMMENT 'Page path without the locale prefix or query string',
    DROP INDEX idx_traffic_hits_visitor,
    ADD INDEX idx_traffic_hits_visitor (visitor_hash, path_hash, created_at);
