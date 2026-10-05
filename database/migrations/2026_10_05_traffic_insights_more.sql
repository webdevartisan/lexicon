-- Traffic: entry/exit/next pages, hour of day, script-like visitors, search terms,
-- scroll depth steps, engagement per breakdown, goals and clicks, 404s, beacon
-- outcomes, notifications and the owner's public switches. traffic_hits is split
-- into daily partitions by local_date so pruning can drop whole days.

ALTER TABLE traffic_hits
    ADD COLUMN from_post_id INT DEFAULT NULL COMMENT 'The post the reader came from inside the same blog' AFTER post_id,
    ADD COLUMN search_term VARCHAR(100) DEFAULT NULL COMMENT 'What was searched on Discover, pruned with the view' AFTER utm_campaign,
    ADD COLUMN engaged_at DATETIME DEFAULT NULL COMMENT 'UTC, when the latest leave ping arrived' AFTER scroll_depth,
    ADD COLUMN local_hour TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Hour of the view in the blog timezone, UTC on platform pages' AFTER local_date,
    ADD COLUMN suspect BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Set by aggregation for a visitor that behaves like a script; left out of every total' AFTER local_hour,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id, local_date),
    DROP INDEX uq_traffic_hits_view,
    ADD UNIQUE KEY uq_traffic_hits_view (view_id, local_date),
    ADD INDEX idx_traffic_hits_engaged (engaged_at);

UPDATE traffic_hits SET local_hour = HOUR(created_at);

ALTER TABLE traffic_hits
    PARTITION BY RANGE COLUMNS(local_date) (
        PARTITION p_start VALUES LESS THAN ('2026-01-01'),
        PARTITION p_future VALUES LESS THAN (MAXVALUE)
    );

ALTER TABLE traffic_daily
    ADD COLUMN scroll_25 INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Engaged views that reached a quarter of the page' AFTER scroll_depth_sum,
    ADD COLUMN scroll_50 INT UNSIGNED NOT NULL DEFAULT 0 AFTER scroll_25,
    ADD COLUMN scroll_75 INT UNSIGNED NOT NULL DEFAULT 0 AFTER scroll_50,
    ADD COLUMN scroll_100 INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Engaged views that reached the end' AFTER scroll_75;

ALTER TABLE traffic_daily_dimensions
    MODIFY dimension ENUM('channel','source','utm_source','utm_medium','utm_campaign','device','browser','os','country','locale',
                          'page','blog','lexicon','entry','exit','next','hour','category','tag','author') NOT NULL,
    ADD COLUMN read_views INT UNSIGNED NOT NULL DEFAULT 0 AFTER visitors,
    ADD COLUMN engaged_views INT UNSIGNED NOT NULL DEFAULT 0 AFTER read_views,
    ADD COLUMN engaged_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER engaged_views;

ALTER TABLE traffic_events
    MODIFY event ENUM('signup','subscribe','comment','like','save','outbound','download') NOT NULL,
    MODIFY channel ENUM('direct','internal','search','social','email','referral') DEFAULT NULL COMMENT 'How the visit began, on sign-ups',
    ADD COLUMN blog_id INT DEFAULT NULL COMMENT 'The blog a goal or click happened on' AFTER day,
    ADD COLUMN post_id INT DEFAULT NULL AFTER blog_id,
    ADD COLUMN value VARCHAR(191) DEFAULT NULL COMMENT 'The host of an outbound link, or the file name of a download' AFTER post_id,
    ADD INDEX idx_traffic_events_blog (blog_id, event, day);

CREATE TABLE IF NOT EXISTS traffic_outcomes (
    day DATE NOT NULL COMMENT 'UTC',
    outcome VARCHAR(20) NOT NULL COMMENT 'What the recorder decided, e.g. recorded, bot, duplicate',
    requests INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, outcome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='How many page view beacons each check let through or turned away, per day';

CREATE TABLE IF NOT EXISTS traffic_not_found (
    day DATE NOT NULL COMMENT 'UTC',
    path_hash BINARY(8) NOT NULL,
    referrer_host VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Empty when the reader typed the address or the referrer was hidden',
    blog_id INT DEFAULT NULL COMMENT 'The blog the missing page would have been in; empty for platform paths',
    path VARCHAR(255) NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, path_hash, referrer_host),
    INDEX idx_traffic_not_found_blog (blog_id, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Readers who reached a page that does not exist, and where they came from';

CREATE TABLE IF NOT EXISTS traffic_milestones (
    post_id INT NOT NULL,
    threshold INT UNSIGNED NOT NULL,
    reached_at DATETIME NOT NULL COMMENT 'UTC',
    PRIMARY KEY (post_id, threshold)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='View milestones already announced, so each one is sent once';

CREATE TABLE IF NOT EXISTS traffic_spike_notices (
    blog_id INT NOT NULL,
    day DATE NOT NULL COMMENT 'The blog timezone day the spike happened on',
    PRIMARY KEY (blog_id, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Spike notices already sent, at most one per blog per day';

ALTER TABLE blog_settings
    ADD COLUMN traffic_popular_posts BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Themes show the most read posts of the last 30 days' AFTER traffic_public_notice,
    ADD COLUMN traffic_public_stats BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Anyone can open the blog''s stats page' AFTER traffic_popular_posts;

ALTER TABLE user_preferences
    ADD COLUMN notify_traffic_digest BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Email me a weekly summary of my blogs'' traffic' AFTER notify_invites,
    ADD COLUMN notify_traffic_milestones BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Email me when a post of mine passes a view milestone' AFTER notify_traffic_digest,
    ADD COLUMN notify_traffic_spikes BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Email me when my blog gets far more readers than usual' AFTER notify_traffic_milestones;

UPDATE scheduled_tasks SET label = 'Update the country and network databases' WHERE command = 'traffic:update-geo';

INSERT INTO scheduled_tasks (label, command, schedule_type, minute_of_hour, schedule_timezone, timeout_seconds, is_active, next_run_at) VALUES
('Traffic milestones and spikes', 'traffic:notify', 'hourly', 20, 'UTC', 300, 1, UTC_TIMESTAMP());

INSERT INTO scheduled_tasks (label, command, arguments, schedule_type, interval_minutes, run_at, schedule_timezone, timeout_seconds, is_active, next_run_at) VALUES
('Weekly traffic digest', 'traffic:digest', NULL, 'daily', NULL, '06:00:00', 'UTC', 900, 1, UTC_TIMESTAMP());
