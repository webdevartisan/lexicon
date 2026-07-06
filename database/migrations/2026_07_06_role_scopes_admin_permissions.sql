ALTER TABLE roles
    ADD COLUMN scope ENUM('system', 'blog') NOT NULL DEFAULT 'blog' AFTER description,
    ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER scope;

UPDATE roles SET is_system = 1
WHERE role_slug IN ('administrator', 'content_manager', 'blog_owner', 'author', 'reviewer', 'contributor');

UPDATE roles SET scope = 'system'
WHERE role_slug IN ('administrator', 'content_manager');

UPDATE roles SET scope = 'blog'
WHERE role_slug IN ('blog_owner', 'author', 'reviewer', 'contributor');

INSERT IGNORE INTO permissions (permission_name, permission_slug, resource, action, description) VALUES
('Access Control Panel', 'access_control_panel', 'admin', 'read', 'Open the control panel dashboard'),
('Manage All Blogs', 'manage_all_blogs', 'blogs', 'manage', 'Full blog management in the control panel'),
('Moderate Comments', 'moderate_comments', 'comments', 'manage', 'Approve, unapprove, mark spam, and delete comments'),
('Manage Taxonomy', 'manage_taxonomy', 'taxonomy', 'manage', 'Manage categories and tags in the control panel'),
('Manage Roles', 'manage_roles', 'roles', 'manage', 'Create custom roles and edit role permissions'),
('View Audit Log', 'view_audit_log', 'audit', 'read', 'Read the audit trail'),
('View System Health', 'view_system_health', 'system', 'read', 'View system diagnostics'),
('Manage Cache', 'manage_cache', 'cache', 'manage', 'View cache statistics, prune and clear caches');
