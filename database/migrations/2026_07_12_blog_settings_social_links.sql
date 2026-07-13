-- Blog create/settings split support:
-- 1) social_links stores a JSON map of platform => profile URL, edited on the
--    blog settings page and rendered by themes (folio footer icons).
-- 2) founded_year widens so the about-section label can be a year or a short
--    word ("2026", "Est. MMXXVI", "Ongoing").

ALTER TABLE blog_settings
    ADD COLUMN social_links TEXT DEFAULT NULL
        COMMENT 'JSON map of social platform => profile URL, shown by themes'
        AFTER newsletter_text,
    MODIFY COLUMN founded_year VARCHAR(20) DEFAULT NULL
        COMMENT 'Short label beside the about section (a year or a short word); empty = current year';
