-- Optional data cleanup. Older code paths wrote blog-scoped roles into
-- user_roles, which the new model forbids (blog roles live in blog_users only).
-- These rows grant nothing now (permission reads are scope-filtered), but they
-- are architecturally invalid. This moves each affected account to the default
-- reader site role, then removes the stray blog-scoped assignments. Ownership
-- is structural (blogs.owner_id) and untouched. Idempotent; slug-based.

-- Ensure every account that currently holds only a blog-scoped role keeps a
-- system role (reader), so nobody is left with no site role.
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT DISTINCT ur.user_id, (SELECT id FROM roles WHERE role_slug = 'reader')
FROM user_roles ur
JOIN roles r ON r.id = ur.role_id
WHERE r.scope = 'blog';

-- Remove the invalid blog-scoped rows from user_roles.
DELETE ur FROM user_roles ur
JOIN roles r ON r.id = ur.role_id
WHERE r.scope = 'blog';
