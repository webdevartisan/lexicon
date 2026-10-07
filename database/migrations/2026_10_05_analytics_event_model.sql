-- Insights rename, step two: the event model. Page views and every other
-- counted action move into analytics_events, grouped into analytics_visits;
-- goals, clicks and sign-ups get their kept daily counts in
-- analytics_daily_events; the two notice tables become analytics_notices_sent.
--
-- Visits are rebuilt from the old views: a visitor's next view more than 30
-- minutes later, or on another device, browser, system or country, starts a
-- new visit, the rule the recorder follows from now on. The old tables stay
-- until the numbers are checked; 2026_10_05_drop_traffic_tables.sql drops them.

CREATE TABLE IF NOT EXISTS analytics_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_key BINARY(16) NOT NULL COMMENT 'The same action twice has the same key: a page view''s own id, or a hash of what the action was on',
    name VARCHAR(32) NOT NULL COMMENT 'An event from config/analytics.php, e.g. page_view, like, outbound',
    view_id BINARY(16) DEFAULT NULL COMMENT 'The page view the event happened on; a page view''s own id',
    visit_id BINARY(16) DEFAULT NULL COMMENT 'Empty for events outside a counted visit',
    visitor_hash BINARY(16) DEFAULT NULL,
    visitor_kind ENUM('account','cookie','daily') DEFAULT NULL COMMENT 'account and cookie ids last across days, daily ones do not',
    blog_id INT DEFAULT NULL COMMENT 'Empty for the platform''s own pages, and once a blog is deleted so its views still count for the site',
    post_id INT DEFAULT NULL,
    from_post_id INT DEFAULT NULL COMMENT 'The post the reader came from inside the same blog',
    page_type ENUM('landing','post','archive','category','tag','home','discover','static_page','guide','profile','auth') DEFAULT NULL COMMENT 'The first five are blog pages, the rest the platform''s own',
    path VARCHAR(255) DEFAULT NULL COMMENT 'Page path without the locale prefix or query string',
    path_hash BINARY(8) DEFAULT NULL,
    locale VARCHAR(5) DEFAULT NULL COMMENT 'The language of the page read, which can change within a visit',
    channel ENUM('direct','internal','lexicon','search','social','email','referral') DEFAULT NULL COMMENT 'On a page view, how the reader got to it; on other events, how the visit began',
    referrer_host VARCHAR(100) DEFAULT NULL,
    referrer_source VARCHAR(60) DEFAULT NULL COMMENT 'Friendly name for a known host, e.g. Google. On lexicon views, the kind of page or blog:{id} the reader came from',
    utm_source VARCHAR(100) DEFAULT NULL,
    utm_medium VARCHAR(100) DEFAULT NULL,
    utm_campaign VARCHAR(100) DEFAULT NULL,
    engaged_seconds SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Page views: visible, active time reported when the reader left',
    scroll_depth TINYINT UNSIGNED DEFAULT NULL COMMENT 'Page views: furthest point reached, percent of the page',
    engaged_at DATETIME DEFAULT NULL COMMENT 'UTC, when the latest leave ping arrived',
    lcp_ms SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Page views: largest contentful paint',
    inp_ms SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Page views: interaction to next paint',
    cls DECIMAL(6,4) DEFAULT NULL COMMENT 'Page views: cumulative layout shift',
    ttfb_ms SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Page views: time to first byte',
    props JSON DEFAULT NULL COMMENT 'Everything else, checked against the event''s list in config/analytics.php',
    local_date DATE NOT NULL COMMENT 'The day in the blog timezone, UTC off a blog',
    local_hour TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Hour in the blog timezone, UTC off a blog',
    suspect BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Set by aggregation for a visitor that behaves like a script; left out of every total',
    created_at DATETIME NOT NULL COMMENT 'UTC',
    PRIMARY KEY (id, local_date),
    UNIQUE KEY uq_analytics_events_key (event_key, local_date),
    INDEX idx_analytics_events_rollup (local_date, blog_id),
    INDEX idx_analytics_events_name (name, local_date),
    INDEX idx_analytics_events_visitor (visitor_hash, path_hash, created_at),
    INDEX idx_analytics_events_view (view_id),
    INDEX idx_analytics_events_visit (visit_id),
    INDEX idx_analytics_events_recent (blog_id, created_at),
    INDEX idx_analytics_events_created (created_at),
    INDEX idx_analytics_events_engaged (engaged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Page views and every other counted action, kept for analytics.raw_retention_days'
-- analytics:aggregate adds one partition per local day ahead of time, and pruning drops whole days.
PARTITION BY RANGE COLUMNS(local_date) (
    PARTITION p_start VALUES LESS THAN ('2026-01-01'),
    PARTITION p_future VALUES LESS THAN (MAXVALUE)
);

CREATE TABLE IF NOT EXISTS analytics_visits (
    id BINARY(16) NOT NULL COMMENT 'Random, never derived from the visitor',
    visitor_hash BINARY(16) NOT NULL,
    seq INT UNSIGNED NOT NULL COMMENT 'The visitor''s visits in order; two first views at once both claim the same next number, so they share one visit',
    visitor_kind ENUM('account','cookie','daily') NOT NULL,
    started_at DATETIME NOT NULL COMMENT 'UTC',
    last_seen_at DATETIME NOT NULL COMMENT 'UTC, the latest page view. Thirty idle minutes end the visit',
    page_views SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    entry_path VARCHAR(255) NOT NULL,
    entry_page_type VARCHAR(20) NOT NULL,
    entry_blog_id INT DEFAULT NULL,
    entry_post_id INT DEFAULT NULL,
    channel ENUM('direct','internal','lexicon','search','social','email','referral') NOT NULL COMMENT 'How the visit began',
    referrer_host VARCHAR(100) DEFAULT NULL,
    referrer_source VARCHAR(60) DEFAULT NULL,
    utm_source VARCHAR(100) DEFAULT NULL,
    utm_medium VARCHAR(100) DEFAULT NULL,
    utm_campaign VARCHAR(100) DEFAULT NULL,
    device ENUM('desktop','mobile','tablet','other') NOT NULL,
    browser VARCHAR(30) NOT NULL,
    os VARCHAR(30) NOT NULL,
    country CHAR(2) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_analytics_visits_seq (visitor_hash, seq),
    INDEX idx_analytics_visits_started (started_at),
    INDEX idx_analytics_visits_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One row per visit: the same visitor, device and country with less than 30 minutes between page views. Not a login session. Pruned with the raw events';

CREATE TABLE IF NOT EXISTS analytics_daily_events (
    scope ENUM('site','platform','blog','post') NOT NULL,
    scope_id INT NOT NULL DEFAULT 0 COMMENT 'The blog or post id; 0 for site and platform',
    blog_id INT DEFAULT NULL COMMENT 'The blog a blog or post row belongs to',
    day DATE NOT NULL COMMENT 'The event''s local day: the blog timezone on a blog, UTC elsewhere',
    name VARCHAR(32) NOT NULL,
    breakdown VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Empty for the total; otherwise one of the event''s breakdowns in config/analytics.php',
    value VARCHAR(191) NOT NULL DEFAULT '',
    events INT UNSIGNED NOT NULL DEFAULT 0,
    visits INT UNSIGNED DEFAULT NULL COMMENT 'Different visits with the event; empty for days before visits were kept',
    PRIMARY KEY (scope, scope_id, name, breakdown, day, value),
    INDEX idx_analytics_daily_events_day (scope, day),
    INDEX idx_analytics_daily_events_blog (blog_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Goals, clicks and sign-ups per day, kept after the raw events are pruned';

CREATE TABLE IF NOT EXISTS analytics_notices_sent (
    kind ENUM('milestone','spike') NOT NULL,
    subject_id INT NOT NULL COMMENT 'The post for a milestone, the blog for a spike',
    notice_key VARCHAR(10) NOT NULL COMMENT 'The view threshold, or the blog timezone day of the spike',
    sent_at DATETIME NOT NULL COMMENT 'UTC',
    PRIMARY KEY (kind, subject_id, notice_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Milestone and spike notices already sent, so each goes out once';

-- The same day partitions as traffic_hits, so pruning keeps dropping whole days.
SET SESSION group_concat_max_len = 1000000;
SET @days = (
    SELECT GROUP_CONCAT(CONCAT('PARTITION ', PARTITION_NAME, ' VALUES LESS THAN (', PARTITION_DESCRIPTION, ')')
                        ORDER BY PARTITION_ORDINAL_POSITION SEPARATOR ', ')
    FROM information_schema.PARTITIONS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'traffic_hits' AND PARTITION_NAME REGEXP '^p[0-9]{8}$'
);
SET @reorganize = IF(
    @days IS NULL,
    'DO 0',
    CONCAT('ALTER TABLE analytics_events REORGANIZE PARTITION p_future INTO (', @days,
           ', PARTITION p_future VALUES LESS THAN (MAXVALUE))')
);
PREPARE reorganize FROM @reorganize;
EXECUTE reorganize;
DEALLOCATE PREPARE reorganize;

ALTER TABLE analytics_daily
    ADD COLUMN visits INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Visits that reached this scope, on the day they reached it' AFTER returning_visitors,
    ADD COLUMN engaged_visits INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Of those, visits with 2+ page views here, 10+ engaged seconds, or a goal' AFTER visits,
    ADD COLUMN visit_pages INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Page views within those visits, for pages per visit' AFTER engaged_visits,
    ADD COLUMN visit_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'From the first page view to the last leave ping, summed, for visit length' AFTER visit_pages,
    ADD COLUMN read_to_end INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Read views that also reached the end of the page' AFTER read_views;

-- Which visit each old view starts or continues, numbered per visitor.
CREATE TABLE migrate_hit_visits (
    hit_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    visitor_hash BINARY(16) NOT NULL,
    seq INT UNSIGNED NOT NULL,
    first_hit_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX (visitor_hash, seq)
) ENGINE=InnoDB;

INSERT INTO migrate_hit_visits (hit_id, visitor_hash, seq, first_hit_id, created_at)
WITH ordered AS (
    SELECT h.id, h.visitor_hash, h.created_at, h.device, h.browser, h.os, h.country,
           LAG(h.created_at) OVER w AS prev_at, LAG(h.device) OVER w AS prev_device,
           LAG(h.browser) OVER w AS prev_browser, LAG(h.os) OVER w AS prev_os, LAG(h.country) OVER w AS prev_country
    FROM traffic_hits h
    WINDOW w AS (PARTITION BY h.visitor_hash ORDER BY h.created_at, h.id)
),
numbered AS (
    SELECT o.id, o.visitor_hash, o.created_at,
           SUM(o.prev_at IS NULL OR o.created_at > o.prev_at + INTERVAL 30 MINUTE
               OR NOT (o.device <=> o.prev_device AND o.browser <=> o.prev_browser
                       AND o.os <=> o.prev_os AND o.country <=> o.prev_country))
               OVER (PARTITION BY o.visitor_hash ORDER BY o.created_at, o.id) AS seq
    FROM ordered o
)
SELECT n.id, n.visitor_hash, n.seq,
       FIRST_VALUE(n.id) OVER (PARTITION BY n.visitor_hash, n.seq ORDER BY n.created_at, n.id),
       n.created_at
FROM numbered n;

INSERT INTO analytics_visits
    (id, visitor_hash, seq, visitor_kind, started_at, last_seen_at, page_views, entry_path, entry_page_type,
     entry_blog_id, entry_post_id, channel, referrer_host, referrer_source, utm_source, utm_medium, utm_campaign,
     device, browser, os, country)
SELECT RANDOM_BYTES(16), f.visitor_hash, v.seq, f.visitor_kind, v.started_at, v.last_seen_at, v.page_views,
       f.path, f.page_type, f.blog_id, f.post_id, f.channel, f.referrer_host, f.referrer_source,
       f.utm_source, f.utm_medium, f.utm_campaign, f.device, f.browser, f.os, f.country
FROM (SELECT visitor_hash, seq, first_hit_id, MIN(created_at) AS started_at, MAX(created_at) AS last_seen_at,
             COUNT(*) AS page_views
      FROM migrate_hit_visits
      GROUP BY visitor_hash, seq, first_hit_id) v
JOIN traffic_hits f ON f.id = v.first_hit_id;

INSERT INTO analytics_events
    (event_key, name, view_id, visit_id, visitor_hash, visitor_kind, blog_id, post_id, from_post_id, page_type,
     path, path_hash, locale, channel, referrer_host, referrer_source, utm_source, utm_medium, utm_campaign,
     engaged_seconds, scroll_depth, engaged_at, props, local_date, local_hour, suspect, created_at)
SELECT h.view_id, 'page_view', h.view_id, v.id, h.visitor_hash, h.visitor_kind, h.blog_id, h.post_id, h.from_post_id,
       h.page_type, h.path, h.path_hash, h.locale, h.channel, h.referrer_host, h.referrer_source,
       h.utm_source, h.utm_medium, h.utm_campaign, h.engaged_seconds, h.scroll_depth, h.engaged_at,
       IF(h.search_term IS NULL, NULL, JSON_OBJECT('q', h.search_term)), h.local_date, h.local_hour, h.suspect,
       h.created_at
FROM traffic_hits h
JOIN migrate_hit_visits m ON m.hit_id = h.id
JOIN analytics_visits v ON v.visitor_hash = m.visitor_hash AND v.seq = m.seq
ORDER BY h.id;

DROP TABLE migrate_hit_visits;

-- Goals, clicks and sign-ups had no visit. They are kept raw too, so the next
-- rollup of the raw window rebuilds the same daily counts; pruning drops the older ones.
INSERT INTO analytics_events
    (event_key, name, blog_id, post_id, channel, referrer_source, utm_source, utm_medium, utm_campaign,
     props, local_date, local_hour, created_at)
SELECT UNHEX(MD5(CONCAT('traffic_events:', t.id))), t.event, t.blog_id, t.post_id, t.channel, t.referrer_source,
       t.utm_source, t.utm_medium, t.utm_campaign,
       CASE
           WHEN t.event = 'outbound' THEN JSON_OBJECT('host', t.value)
           WHEN t.event = 'download' THEN JSON_OBJECT('file', t.value)
           WHEN t.event = 'signup' AND t.came_from IS NOT NULL THEN JSON_OBJECT('came_from', t.came_from)
       END,
       t.day, 12, TIMESTAMP(t.day, '12:00:00')
FROM traffic_events t
ORDER BY t.id;

-- Missing pages were counted per day, path and referring site; each count becomes one event.
INSERT INTO analytics_events (event_key, name, blog_id, path, path_hash, props, local_date, local_hour, created_at)
WITH RECURSIVE n AS (
    SELECT 1 AS i
    UNION ALL
    SELECT i + 1 FROM n WHERE i < (SELECT COALESCE(MAX(views), 1) FROM traffic_not_found)
)
SELECT UNHEX(MD5(CONCAT('traffic_not_found:', f.day, ':', HEX(f.path_hash), ':', f.referrer_host, ':', n.i))),
       'not_found', f.blog_id, f.path, f.path_hash,
       IF(f.referrer_host = '', NULL, JSON_OBJECT('referrer_host', f.referrer_host)),
       f.day, 12, TIMESTAMP(f.day, '12:00:00')
FROM traffic_not_found f
JOIN n ON n.i <= f.views;

-- The daily counts, the way the rollup writes them: every kept event per blog,
-- post and platform page; sign-ups for the site and the blog they began on.
-- A total row per event, plus one row per value of each of its breakdowns.
INSERT INTO analytics_daily_events (scope, scope_id, blog_id, day, name, breakdown, value, events, visits)
WITH breakdowns AS (
    SELECT 'like' AS name, '' AS breakdown
    UNION ALL SELECT 'like' AS name, 'post' AS breakdown
    UNION ALL SELECT 'like' AS name, 'channel' AS breakdown
    UNION ALL SELECT 'like' AS name, 'source' AS breakdown
    UNION ALL SELECT 'like' AS name, 'utm_campaign' AS breakdown
    UNION ALL SELECT 'dislike' AS name, '' AS breakdown
    UNION ALL SELECT 'dislike' AS name, 'post' AS breakdown
    UNION ALL SELECT 'dislike' AS name, 'channel' AS breakdown
    UNION ALL SELECT 'dislike' AS name, 'source' AS breakdown
    UNION ALL SELECT 'dislike' AS name, 'utm_campaign' AS breakdown
    UNION ALL SELECT 'save' AS name, '' AS breakdown
    UNION ALL SELECT 'save' AS name, 'post' AS breakdown
    UNION ALL SELECT 'save' AS name, 'channel' AS breakdown
    UNION ALL SELECT 'save' AS name, 'source' AS breakdown
    UNION ALL SELECT 'save' AS name, 'utm_campaign' AS breakdown
    UNION ALL SELECT 'comment' AS name, '' AS breakdown
    UNION ALL SELECT 'comment' AS name, 'post' AS breakdown
    UNION ALL SELECT 'comment' AS name, 'channel' AS breakdown
    UNION ALL SELECT 'comment' AS name, 'source' AS breakdown
    UNION ALL SELECT 'comment' AS name, 'utm_campaign' AS breakdown
    UNION ALL SELECT 'subscribe' AS name, '' AS breakdown
    UNION ALL SELECT 'subscribe' AS name, 'channel' AS breakdown
    UNION ALL SELECT 'subscribe' AS name, 'source' AS breakdown
    UNION ALL SELECT 'subscribe' AS name, 'utm_campaign' AS breakdown
    UNION ALL SELECT 'share' AS name, '' AS breakdown
    UNION ALL SELECT 'share' AS name, 'network' AS breakdown
    UNION ALL SELECT 'share' AS name, 'post' AS breakdown
    UNION ALL SELECT 'share' AS name, 'channel' AS breakdown
    UNION ALL SELECT 'share' AS name, 'source' AS breakdown
    UNION ALL SELECT 'signup' AS name, '' AS breakdown
    UNION ALL SELECT 'signup' AS name, 'channel' AS breakdown
    UNION ALL SELECT 'signup' AS name, 'source' AS breakdown
    UNION ALL SELECT 'signup' AS name, 'came_from' AS breakdown
    UNION ALL SELECT 'signup' AS name, 'utm_source' AS breakdown
    UNION ALL SELECT 'signup' AS name, 'utm_medium' AS breakdown
    UNION ALL SELECT 'signup' AS name, 'utm_campaign' AS breakdown
    UNION ALL SELECT 'outbound' AS name, '' AS breakdown
    UNION ALL SELECT 'outbound' AS name, 'host' AS breakdown
    UNION ALL SELECT 'outbound' AS name, 'post' AS breakdown
    UNION ALL SELECT 'download' AS name, '' AS breakdown
    UNION ALL SELECT 'download' AS name, 'file' AS breakdown
    UNION ALL SELECT 'download' AS name, 'post' AS breakdown
),
scoped AS (
    SELECT 'platform' AS scope, 0 AS scope_id, NULL AS scope_blog, e.local_date, e.name, e.post_id, e.channel,
           e.referrer_source, e.utm_source, e.utm_medium, e.utm_campaign, e.props
    FROM analytics_events e WHERE e.blog_id IS NULL AND e.name <> 'signup'
    UNION ALL
    SELECT 'blog', e.blog_id, e.blog_id, e.local_date, e.name, e.post_id, e.channel,
           e.referrer_source, e.utm_source, e.utm_medium, e.utm_campaign, e.props
    FROM analytics_events e WHERE e.blog_id IS NOT NULL AND e.name <> 'signup'
    UNION ALL
    SELECT 'post', e.post_id, e.blog_id, e.local_date, e.name, e.post_id, e.channel,
           e.referrer_source, e.utm_source, e.utm_medium, e.utm_campaign, e.props
    FROM analytics_events e WHERE e.post_id IS NOT NULL AND e.name <> 'signup'
    UNION ALL
    SELECT 'site', 0, NULL, e.local_date, e.name, e.post_id, e.channel,
           e.referrer_source, e.utm_source, e.utm_medium, e.utm_campaign, e.props
    FROM analytics_events e WHERE e.name = 'signup'
    UNION ALL
    SELECT 'blog', b.id, b.id, e.local_date, e.name, e.post_id, e.channel,
           e.referrer_source, e.utm_source, e.utm_medium, e.utm_campaign, e.props
    FROM analytics_events e
    JOIN blogs b ON b.id = CAST(SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(e.props, '$.came_from')), 6) AS UNSIGNED)
    WHERE e.name = 'signup' AND JSON_UNQUOTE(JSON_EXTRACT(e.props, '$.came_from')) LIKE 'blog:%'
),
valued AS (
    SELECT s.scope, s.scope_id, s.scope_blog, s.local_date, s.name, b.breakdown,
           CASE b.breakdown
               WHEN '' THEN ''
               WHEN 'post' THEN s.post_id
               WHEN 'channel' THEN s.channel
               WHEN 'source' THEN s.referrer_source
               WHEN 'utm_source' THEN s.utm_source
               WHEN 'utm_medium' THEN s.utm_medium
               WHEN 'utm_campaign' THEN s.utm_campaign
               ELSE JSON_UNQUOTE(JSON_EXTRACT(s.props, CONCAT('$.', b.breakdown)))
           END AS value
    FROM scoped s
    JOIN breakdowns b ON b.name = s.name
)
SELECT scope, scope_id, scope_blog, local_date, name, breakdown, value, COUNT(*), NULL
FROM valued
WHERE value IS NOT NULL
GROUP BY scope, scope_id, scope_blog, local_date, name, breakdown, value;

INSERT INTO analytics_notices_sent (kind, subject_id, notice_key, sent_at)
SELECT 'milestone', post_id, threshold, reached_at FROM traffic_milestones;

INSERT INTO analytics_notices_sent (kind, subject_id, notice_key, sent_at)
SELECT 'spike', blog_id, DATE_FORMAT(day, '%Y-%m-%d'), UTC_TIMESTAMP() FROM traffic_spike_notices;
