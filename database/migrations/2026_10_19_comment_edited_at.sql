ALTER TABLE comments
    ADD COLUMN edited_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time the author changed the text; shows as "edited"' AFTER pinned_by;
