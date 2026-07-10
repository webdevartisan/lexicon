-- ----------------------------------------------------------------------------
-- Page thumbnails.
--
-- Guides on the getting-started index used a hardcoded set of stock images
-- (pic07/08/09.jpg) instead of anything editable. Admins can now upload a
-- thumbnail per page from the page editor; the getting-started listing falls
-- back to the old stock rotation when a guide has none set.
-- ----------------------------------------------------------------------------

ALTER TABLE pages
    ADD COLUMN thumbnail_path VARCHAR(255) DEFAULT NULL AFTER meta_description;
