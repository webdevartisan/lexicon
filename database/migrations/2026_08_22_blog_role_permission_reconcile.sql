INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_slug IN ('create_posts', 'edit_own_posts')
WHERE r.role_slug = 'contributor';

-- Reviewers may self-assign to a post, so they hold assign_reviewers alongside
-- editors and owners.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_slug = 'assign_reviewers'
WHERE r.role_slug = 'reviewer';
