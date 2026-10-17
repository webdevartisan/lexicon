-- ----------------------------------------------------------------------------
-- view_users now opens the control panel's user list and account pages
-- read-only. It was seeded for content managers when it did nothing; from now
-- on only administrators hold it until someone grants it on purpose.

DELETE rp FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE r.role_slug = 'content_manager' AND p.permission_slug = 'view_users';
