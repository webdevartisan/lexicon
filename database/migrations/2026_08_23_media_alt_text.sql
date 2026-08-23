-- Alt text for accessibility, surfaced in the media editor and reused when
-- inserting an image so screen readers get a description.
ALTER TABLE media
    ADD COLUMN alt_text VARCHAR(255) DEFAULT NULL AFTER original_name;
