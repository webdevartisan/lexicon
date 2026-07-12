-- Editable front-page texts for blog themes. Until now themes fell back to
-- hardcoded copy ("A Journal of Slow Reading" etc.) because there was nowhere
-- to store per-blog wording. Owners can now edit these on the blog settings page.

ALTER TABLE blog_settings
    ADD COLUMN tagline VARCHAR(160) DEFAULT NULL COMMENT 'Short hero tagline shown in the theme header area' AFTER meta_description,
    ADD COLUMN subtitle VARCHAR(255) DEFAULT NULL COMMENT 'Introduction line shown under the blog name' AFTER tagline,
    ADD COLUMN about_text VARCHAR(500) DEFAULT NULL COMMENT 'About-this-blog statement (folio colophon section)' AFTER subtitle,
    ADD COLUMN founded_year VARCHAR(4) DEFAULT NULL COMMENT 'Year shown beside the about section; empty = current year' AFTER about_text,
    ADD COLUMN newsletter_heading VARCHAR(255) DEFAULT NULL COMMENT 'Subscribe section heading; empty = theme default' AFTER founded_year,
    ADD COLUMN newsletter_text VARCHAR(255) DEFAULT NULL COMMENT 'Subscribe section supporting line; empty = theme default' AFTER newsletter_heading;
