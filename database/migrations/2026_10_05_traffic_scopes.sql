-- Traffic totals per scope: the whole site, the platform's own pages, one blog
-- or one post. Replaces the per-blog tables and the separate site table.
--
-- The totals are derived data, so they are dropped and rebuilt from the raw
-- views afterwards: php cli traffic:aggregate --days=29

ALTER TABLE traffic_hits
    MODIFY blog_id INT DEFAULT NULL COMMENT 'Empty once the blog is deleted, so the view still counts for the site';

DROP TABLE IF EXISTS traffic_site_daily;
DROP TABLE IF EXISTS traffic_daily;
DROP TABLE IF EXISTS traffic_daily_dimensions;

CREATE TABLE traffic_daily (
    scope ENUM('site','platform','blog','post') NOT NULL COMMENT 'The whole site, the platform''s own pages, one blog or one post',
    scope_id INT NOT NULL DEFAULT 0 COMMENT 'The blog or post id; 0 for site and platform',
    blog_id INT DEFAULT NULL COMMENT 'The blog a blog or post row belongs to',
    day DATE NOT NULL COMMENT 'UTC for site and platform, the blog timezone for blog and post',
    views INT UNSIGNED NOT NULL DEFAULT 0,
    visitors INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Different visitors in this scope that day',
    identified_visitors INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Visitors counted by account or cookie, the base for returning',
    returning_visitors INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Identified visitors seen in this scope on an earlier day',
    bounces INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Visitors with one view in this scope and under 10 engaged seconds; none on post rows',
    read_views INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Views with 30 or more engaged seconds',
    engaged_views INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Views whose leave ping arrived',
    engaged_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
    scroll_depth_sum BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (scope, scope_id, day),
    INDEX idx_traffic_daily_day (scope, day),
    INDEX idx_traffic_daily_blog (scope, blog_id, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily totals per scope, rebuilt from traffic_hits';

CREATE TABLE traffic_daily_dimensions (
    scope ENUM('site','platform','blog','post') NOT NULL,
    scope_id INT NOT NULL DEFAULT 0 COMMENT 'The blog or post id; 0 for site and platform',
    blog_id INT DEFAULT NULL COMMENT 'The blog a blog or post row belongs to',
    dimension ENUM('channel','source','utm_source','utm_medium','utm_campaign','device','browser','os','country','locale','page','blog') NOT NULL,
    day DATE NOT NULL COMMENT 'UTC for site and platform, the blog timezone for blog and post',
    value VARCHAR(191) NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    visitors INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (scope, scope_id, dimension, day, value),
    INDEX idx_traffic_dimensions_day (scope, day),
    INDEX idx_traffic_dimensions_blog (blog_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily breakdowns (sources, devices, countries, pages, blogs) per scope';
