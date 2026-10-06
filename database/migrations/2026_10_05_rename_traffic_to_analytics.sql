-- Insights rename, step one: the tables, columns and stored names that keep
-- their shape take the analytics name. The raw tables (traffic_hits,
-- traffic_events, traffic_not_found, traffic_milestones, traffic_spike_notices)
-- are replaced by the event model in the next migration.
--
-- Scheduled tasks are renamed here too: the scheduler checks the command name
-- against the console kernel when it runs a task, so the old names would fail.

RENAME TABLE
    traffic_daily TO analytics_daily,
    traffic_daily_dimensions TO analytics_daily_dimensions,
    traffic_salts TO analytics_salts,
    traffic_outcomes TO analytics_beacon_outcomes;

ALTER TABLE analytics_daily
    RENAME INDEX idx_traffic_daily_day TO idx_analytics_daily_day,
    RENAME INDEX idx_traffic_daily_blog TO idx_analytics_daily_blog,
    COMMENT = 'Daily totals per scope, rebuilt from the raw events';

ALTER TABLE analytics_daily_dimensions
    RENAME INDEX idx_traffic_dimensions_day TO idx_analytics_dimensions_day,
    RENAME INDEX idx_traffic_dimensions_blog TO idx_analytics_dimensions_blog;

ALTER TABLE blog_settings
    RENAME COLUMN traffic_exclude_members TO analytics_exclude_members,
    RENAME COLUMN traffic_excluded_paths TO analytics_excluded_paths,
    RENAME COLUMN traffic_public_notice TO analytics_public_notice,
    RENAME COLUMN traffic_popular_posts TO analytics_popular_posts,
    RENAME COLUMN traffic_public_stats TO analytics_public_stats;

ALTER TABLE user_preferences
    RENAME COLUMN notify_traffic_digest TO notify_insights_digest,
    RENAME COLUMN notify_traffic_milestones TO notify_insights_milestones,
    RENAME COLUMN notify_traffic_spikes TO notify_insights_spikes;

UPDATE settings SET name = CONCAT('analytics.', SUBSTRING(name, 9)) WHERE name LIKE 'traffic.%';

UPDATE permissions
SET permission_name = 'View Platform Analytics', permission_slug = 'view_platform_analytics', resource = 'analytics'
WHERE permission_slug = 'view_platform_traffic';

UPDATE scheduled_tasks SET command = CONCAT('analytics:', SUBSTRING(command, 9)) WHERE command LIKE 'traffic:%';

UPDATE notifications SET type = CONCAT('analytics.', SUBSTRING(type, 9)) WHERE type LIKE 'traffic.%';

UPDATE activity_log SET action = REPLACE(action, '.traffic_settings_updated', '.analytics_settings_updated')
WHERE action IN ('blog.traffic_settings_updated', 'site.traffic_settings_updated');
