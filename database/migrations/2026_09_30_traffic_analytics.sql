-- Traffic analytics behind Insights > Traffic.
--
-- A page view arrives from a small script on every blog page (the full-page and
-- browser caches mean the server never sees a lot of them), is written once to
-- traffic_hits, and is rolled up into the daily tables by traffic:aggregate.
-- Dashboards read the rollups only. Raw hits are pruned by privacy:prune.
--
-- visitor_hash is either a keyed hash of an account or cookie id, or a hash of
-- the IP and user agent under a salt that is deleted once its day is over.
-- No foreign keys: the write path stays a single cheap insert, and blog deletion
-- clears these tables explicitly (BlogDeletionService).

CREATE TABLE IF NOT EXISTS traffic_hits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    view_id BINARY(16) NOT NULL COMMENT 'Random per page view, made in the browser. The leave ping finds its row by it',
    blog_id INT NOT NULL,
    post_id INT DEFAULT NULL,
    page_type ENUM('landing','post','archive','category','tag') NOT NULL,
    path VARCHAR(255) NOT NULL COMMENT 'Blog page path without the locale prefix or query string',
    path_hash BINARY(8) NOT NULL,
    visitor_hash BINARY(16) NOT NULL,
    visitor_kind ENUM('account','cookie','daily') NOT NULL COMMENT 'account and cookie ids last across days, daily ones do not',
    channel ENUM('direct','internal','search','social','email','referral') NOT NULL,
    referrer_host VARCHAR(100) DEFAULT NULL,
    referrer_source VARCHAR(60) DEFAULT NULL COMMENT 'Friendly name for a known host, e.g. Google',
    utm_source VARCHAR(100) DEFAULT NULL,
    utm_medium VARCHAR(100) DEFAULT NULL,
    utm_campaign VARCHAR(100) DEFAULT NULL,
    device ENUM('desktop','mobile','tablet','other') NOT NULL,
    browser VARCHAR(30) NOT NULL,
    os VARCHAR(30) NOT NULL,
    country CHAR(2) DEFAULT NULL,
    locale VARCHAR(5) NOT NULL,
    engaged_seconds SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Visible, active time reported when the reader left',
    scroll_depth TINYINT UNSIGNED DEFAULT NULL COMMENT 'Furthest point reached, percent of the page',
    local_date DATE NOT NULL COMMENT 'The day in the blog timezone at the moment of the view',
    created_at DATETIME NOT NULL COMMENT 'UTC',
    PRIMARY KEY (id),
    UNIQUE KEY uq_traffic_hits_view (view_id),
    INDEX idx_traffic_hits_rollup (local_date, blog_id),
    INDEX idx_traffic_hits_visitor (visitor_hash, blog_id, path_hash, created_at),
    INDEX idx_traffic_hits_recent (blog_id, created_at),
    INDEX idx_traffic_hits_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Raw page views, kept for traffic.raw_retention_days';

CREATE TABLE IF NOT EXISTS traffic_daily (
    blog_id INT NOT NULL,
    post_id INT NOT NULL DEFAULT 0 COMMENT '0 is the whole blog',
    day DATE NOT NULL COMMENT 'Blog timezone',
    views INT UNSIGNED NOT NULL DEFAULT 0,
    visitors INT UNSIGNED NOT NULL DEFAULT 0,
    identified_visitors INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Visitors counted by account or cookie, the base for returning',
    returning_visitors INT UNSIGNED NOT NULL DEFAULT 0,
    bounces INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Visitors with one view and under 10 engaged seconds',
    read_views INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Views with 30 or more engaged seconds',
    engaged_views INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Views whose leave ping arrived',
    engaged_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
    scroll_depth_sum BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (blog_id, post_id, day),
    INDEX idx_traffic_daily_day (blog_id, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily totals per blog and per post, rebuilt from traffic_hits';

CREATE TABLE IF NOT EXISTS traffic_daily_dimensions (
    blog_id INT NOT NULL,
    post_id INT NOT NULL DEFAULT 0 COMMENT '0 is the whole blog',
    dimension ENUM('channel','source','utm_source','utm_medium','utm_campaign','device','browser','os','country','locale','page') NOT NULL,
    day DATE NOT NULL COMMENT 'Blog timezone',
    value VARCHAR(191) NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    visitors INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (blog_id, post_id, dimension, day, value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily breakdowns (sources, devices, countries, pages) per blog and per post';

CREATE TABLE IF NOT EXISTS traffic_site_daily (
    day DATE NOT NULL COMMENT 'UTC',
    views INT UNSIGNED NOT NULL DEFAULT 0,
    visitors INT UNSIGNED NOT NULL DEFAULT 0,
    blogs INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Blogs that had at least one view',
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Platform totals with no blog attached, so they survive blog deletion';

CREATE TABLE IF NOT EXISTS traffic_salts (
    day DATE NOT NULL COMMENT 'UTC',
    salt BINARY(32) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One random salt per UTC day for anonymous visitor hashes, deleted when the day is over';

ALTER TABLE blog_settings
    ADD COLUMN traffic_enabled BOOLEAN NOT NULL DEFAULT FALSE
        COMMENT 'The owner turned visit counting on for this blog' AFTER translations_enabled,
    ADD COLUMN traffic_exclude_members BOOLEAN NOT NULL DEFAULT TRUE
        COMMENT 'Do not count the blog team viewing their own blog' AFTER traffic_enabled,
    ADD COLUMN traffic_excluded_paths TEXT DEFAULT NULL
        COMMENT 'One blog-relative path prefix per line that is never counted' AFTER traffic_exclude_members,
    ADD COLUMN traffic_public_notice BOOLEAN NOT NULL DEFAULT FALSE
        COMMENT 'Show readers a line saying how visits are counted' AFTER traffic_excluded_paths;

INSERT INTO scheduled_tasks (label, command, arguments, schedule_type, interval_minutes, run_at, schedule_timezone, timeout_seconds, is_active, next_run_at) VALUES
('Aggregate traffic', 'traffic:aggregate', NULL, 'every_n_minutes', 5, NULL, 'UTC', 240, 1, UTC_TIMESTAMP()),
('Update the country database', 'traffic:update-geo', NULL, 'daily', NULL, '04:30:00', 'UTC', 300, 1, UTC_TIMESTAMP());
