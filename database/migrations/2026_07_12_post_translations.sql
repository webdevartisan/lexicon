-- Localized posts: per-blog opt-in. The posts row stays the default-language
-- content; translations are per-locale overlays of the readable fields and
-- fall back to the base post when a locale has no row. Slugs are shared
-- across locales so public routing stays untouched.

ALTER TABLE blog_settings
    ADD COLUMN translations_enabled BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'When true, post edit pages offer per-locale translation tabs' AFTER workflow_enabled;

CREATE TABLE IF NOT EXISTS post_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    locale VARCHAR(5) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT DEFAULT NULL,
    excerpt TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_post_locale (post_id, locale),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    INDEX idx_translation_locale (locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Per-locale overlays of post content; base posts row is the default language';
