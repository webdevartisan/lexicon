INSERT IGNORE INTO permissions (permission_name, permission_slug, resource, action, description) VALUES
('Manage Team', 'manage_team', 'blogs', 'manage_team', 'Manage collaborators and invitations on blogs you manage'),
('Assign Reviewers', 'assign_reviewers', 'posts', 'assign_reviewers', 'Assign reviewers to posts in blogs you manage');

-- Editor is the strongest collaborator role the code checks for, yet it never had
-- a row. Full control of the blog's content and review pipeline, but not the team
-- roster or the blog's existence (those stay owner-only).
INSERT INTO roles (role_name, role_slug, description, scope, is_system, level)
SELECT 'Editor', 'editor', 'Manages all content and the review pipeline within a blog, but not its team or settings deletion', 'blog', 1, 50
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_slug = 'editor');

-- Editor permissions.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_slug IN (
    'edit_own_blog', 'create_posts', 'edit_blog_posts', 'delete_blog_posts',
    'publish_blog_posts', 'view_all_posts', 'review_posts', 'approve_posts',
    'reject_posts', 'provide_feedback', 'review_submissions', 'assign_reviewers'
)
WHERE r.role_slug = 'editor';

-- Owner bundle (blog_owner): the permission set a structural blog owner implicitly
-- holds. Backfill the two new capabilities so owners keep full control.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_slug IN ('manage_team', 'assign_reviewers')
WHERE r.role_slug = 'blog_owner';
