-- Replaces the first design of the email tables (blocks, templates and
-- per-email wording) with layouts and per-language content. Databases created
-- after the change already have the new tables from 2026_10_06; for those this
-- only drops tables that do not exist. The old tables only ever held edits
-- made during development, so nothing is carried over.

DROP TABLE IF EXISTS mailable_template_bindings;
DROP TABLE IF EXISTS email_template_components;
DROP TABLE IF EXISTS email_templates;
DROP TABLE IF EXISTS email_components;

-- ----------------------------------------------------------------------------
-- Email Tables
-- ----------------------------------------------------------------------------
-- An email is its words in a layout. A layout is a whole HTML document (the
-- wrapper, header and footer) with {{ content }} where each email goes; an
-- email is a subject, preheader, body and footer note per language.
--
-- The defaults ship as files: resources/mail/layouts/*.html and one English
-- resources/mail/emails/{Mailable}.html per email. These tables only hold what
-- was changed or added in the control panel. A layout row whose slug matches a
-- shipped file overrides it, and an English content row overrides the shipped
-- English; deleting the row is "reset to default". Other languages exist only
-- as rows. A fresh install has empty tables and still sends every email.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_layouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL COMMENT 'Stable name emails refer to; matches a shipped layout file to override it',
    name VARCHAR(100) NOT NULL,
    html MEDIUMTEXT NOT NULL COMMENT 'Whole HTML document with {{ content }} where the email goes; checked for scripts and handlers on save',
    primary_color VARCHAR(7) NOT NULL DEFAULT '#4F46E5' COMMENT '{{ primary_color }} in the layout and every email using it',
    background_color VARCHAR(7) NOT NULL DEFAULT '#F3F4F6' COMMENT '{{ background_color }}',
    support_email VARCHAR(255) NOT NULL DEFAULT '' COMMENT '{{ support_email }}; empty uses the From address',
    company_address VARCHAR(500) NOT NULL DEFAULT '' COMMENT '{{ company_address }}',
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_layouts_slug (slug),
    CONSTRAINT fk_email_layouts_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email layouts added or customized in the control panel';

CREATE TABLE IF NOT EXISTS email_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mailable_class VARCHAR(191) NOT NULL COMMENT 'Fully qualified Mailable class, e.g. App\\Mail\\NewPostMail',
    layout_slug VARCHAR(64) NOT NULL COMMENT 'Layout the email is sent in, in every language',
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_settings_class (mailable_class),
    INDEX idx_email_settings_layout (layout_slug),
    CONSTRAINT fk_email_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Which layout an email uses, where changed from the one its file names';

CREATE TABLE IF NOT EXISTS email_contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mailable_class VARCHAR(191) NOT NULL,
    locale VARCHAR(5) NOT NULL COMMENT 'Language of this version, e.g. en, el, ar',
    subject VARCHAR(255) NOT NULL,
    preheader VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'The preview line inbox lists show after the subject',
    body MEDIUMTEXT NOT NULL COMMENT 'HTML rows that go into the layout at {{ content }}',
    footer_note TEXT NOT NULL COMMENT 'Why the reader got this email, shown in the layout footer',
    repeat_html MEDIUMTEXT DEFAULT NULL COMMENT 'Section the email repeats, e.g. once per blog in the weekly digest; NULL for emails that repeat nothing',
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_contents_class_locale (mailable_class, locale),
    CONSTRAINT fk_email_contents_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Each email per language, where written or changed in the control panel';

UPDATE permissions SET description = 'Edit email layouts and the wording of every email the site sends, in every language' WHERE permission_slug = 'manage_email_templates';
