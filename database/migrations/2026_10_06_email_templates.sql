-- Email templates built from blocks, editable in the control panel.
-- See the matching section of schema.sql for what each table holds.

-- ----------------------------------------------------------------------------
-- Email Template Tables
-- ----------------------------------------------------------------------------
-- Emails are built from blocks (email_components) arranged into templates
-- (email_templates), and each Mailable class is bound to one template with
-- its own wording for the template's placeholders (mailable_template_bindings).
--
-- The defaults ship in code (resources/mail/catalog.php). These tables only
-- hold what was changed or added in the control panel: a row whose slug (or
-- Mailable class) matches a built-in one overrides it, and deleting that row
-- is "reset to default". So a fresh install has empty tables and still sends
-- every email.
--
-- Templates refer to blocks by slug rather than id because a block may be a
-- built-in one with no row at all. The application refuses to delete a block
-- a template uses, or a template an email uses.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL COMMENT 'Stable name templates refer to; matches a built-in block to override it',
    label VARCHAR(100) NOT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'content' COMMENT 'Library grouping: layout, content, callout, action, data',
    description VARCHAR(255) NOT NULL DEFAULT '',
    html_template MEDIUMTEXT NOT NULL COMMENT 'Markup with {{ placeholders }}; checked for scripts and handlers on save',
    text_template TEXT DEFAULT NULL COMMENT 'Plain-text part; NULL = worked out from the HTML, empty = left out',
    css TEXT DEFAULT NULL COMMENT 'Gathered into the email head once per block used',
    preview_data JSON DEFAULT NULL COMMENT 'Sample value per placeholder for previews',
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_components_slug (slug),
    INDEX idx_email_components_category (category),
    CONSTRAINT fk_email_components_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email blocks added or customized in the control panel';

CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL COMMENT 'Stable name bindings refer to; matches a built-in template to override it',
    label VARCHAR(100) NOT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'transactional' COMMENT 'transactional, alert, digest, promotional',
    description VARCHAR(255) NOT NULL DEFAULT '',
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_templates_slug (slug),
    INDEX idx_email_templates_category (category),
    CONSTRAINT fk_email_templates_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email templates added or customized in the control panel';

CREATE TABLE IF NOT EXISTS email_template_components (
    template_id INT NOT NULL,
    position SMALLINT UNSIGNED NOT NULL COMMENT 'Render order, from 0',
    component_slug VARCHAR(64) NOT NULL,
    PRIMARY KEY (template_id, position),
    INDEX idx_email_template_components_slug (component_slug),
    CONSTRAINT fk_email_template_components_template FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Blocks of each stored template, in order; a block may appear more than once';

CREATE TABLE IF NOT EXISTS mailable_template_bindings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mailable_class VARCHAR(191) NOT NULL COMMENT 'Fully qualified Mailable class, e.g. App\\Mail\\NewPostMail',
    template_slug VARCHAR(64) NOT NULL,
    subject_template VARCHAR(255) DEFAULT NULL COMMENT 'NULL keeps the subject set in code',
    placeholder_mapping JSON NOT NULL COMMENT 'Placeholder => wording, HTML that may use the email''s data',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 sends the built-in version while keeping these edits',
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mailable_template_bindings_class (mailable_class),
    INDEX idx_mailable_template_bindings_template (template_slug),
    CONSTRAINT fk_mailable_template_bindings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Which template each email uses and its wording, where changed in the control panel';

INSERT INTO permissions (permission_name, permission_slug, resource, action, description)
SELECT 'Manage Email Templates', 'manage_email_templates', 'mail', 'manage', 'Edit email blocks, templates and the wording of every email the site sends'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_slug = 'manage_email_templates');

-- Administrators hold every permission.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_slug = 'manage_email_templates'
WHERE r.role_slug = 'administrator';
