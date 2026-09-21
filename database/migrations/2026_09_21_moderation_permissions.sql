-- ----------------------------------------------------------------------------
-- Who may work the reports queue
-- ----------------------------------------------------------------------------
-- handle_reports opens /admin/reports: reading cases, dismissing, upholding,
-- hiding content and warning authors. Suspending still needs manage_all_users,
-- and changing the moderation rules is for administrators only, so neither is
-- part of this permission.
-- ----------------------------------------------------------------------------

INSERT INTO permissions (permission_name, permission_slug, resource, action, description)
VALUES ('Handle Reports', 'handle_reports', 'moderation', 'manage',
        'Work the reports queue: review cases, dismiss or uphold them, hide content and warn authors');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
  FROM roles r
  JOIN permissions p ON p.permission_slug = 'handle_reports'
 WHERE r.role_slug IN ('administrator', 'content_manager');
