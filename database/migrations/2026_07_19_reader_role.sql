-- Reader: default account role for people who sign up from a blog front.
-- Grants no permissions; creator abilities arrive when they start a blog.
INSERT INTO roles (role_name, role_slug, description, scope, is_system, level)
SELECT 'Reader', 'reader', 'Default account role: can subscribe, save posts, and join discussions', 'system', 1, 10
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_slug = 'reader');
