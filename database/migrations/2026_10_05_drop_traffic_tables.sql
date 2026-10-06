-- Insights rename, step three: once the numbers after
-- 2026_10_05_analytics_event_model.sql match, the old raw tables and the
-- bounce count go.

DROP TABLE traffic_hits, traffic_events, traffic_not_found, traffic_milestones, traffic_spike_notices;

ALTER TABLE analytics_daily DROP COLUMN bounces;
