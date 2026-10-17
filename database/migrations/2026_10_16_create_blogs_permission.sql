-- ----------------------------------------------------------------------------
-- Starting a blog is now decided by the create_blogs permission on the
-- account's system roles instead of a hardcoded role list. Readers and content
-- managers get it so nobody loses the ability; the blog_owner grant goes,
-- because blog-scope roles never feed system permissions.

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_slug = 'create_blogs'
WHERE r.role_slug IN ('reader', 'content_manager');

DELETE rp FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE r.role_slug = 'blog_owner' AND p.permission_slug = 'create_blogs';
