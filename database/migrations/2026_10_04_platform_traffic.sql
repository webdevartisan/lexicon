-- Insights > Traffic in the control panel: every blog's numbers side by side.
--
-- The blog dashboards always filter by blog_id first, which the existing keys
-- cover. The platform page asks for one day range across all blogs, so it
-- gets its own indexes instead of scanning the rollups end to end.

ALTER TABLE traffic_daily
    ADD INDEX idx_traffic_daily_platform (day, post_id);

ALTER TABLE traffic_daily_dimensions
    ADD INDEX idx_traffic_dimensions_platform (post_id, dimension, day);

-- Administrators pass by role. This lets another role be given the page alone.
INSERT IGNORE INTO permissions (permission_name, permission_slug, resource, action, description) VALUES
('View Platform Traffic', 'view_platform_traffic', 'traffic', 'read', 'See traffic across every blog in the control panel');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_slug = 'view_platform_traffic'
WHERE r.role_slug = 'administrator';
