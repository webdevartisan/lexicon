-- ----------------------------------------------------------------------------
-- Drop seeded permissions that no code checks: each either repeats a check
-- another permission already makes (manage_all_users, manage_all_posts,
-- manage_all_blogs, manage_team, reject_posts) or belongs to a submissions
-- feature that does not exist. The role editor then offers only switches that
-- do something. role_permissions rows go with them through ON DELETE CASCADE.

DELETE FROM permissions
WHERE permission_slug IN (
    'assign_authors', 'create_users', 'delete_all_posts', 'delete_users',
    'edit_all_posts', 'edit_users', 'provide_feedback', 'publish_all_posts',
    'review_submissions', 'submit_ideas', 'view_all_blogs', 'view_own_submissions'
);
