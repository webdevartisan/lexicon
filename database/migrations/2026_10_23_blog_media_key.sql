ALTER TABLE blogs
    ADD COLUMN media_key CHAR(16) NULL COMMENT 'Random folder name for the blog uploads, so image URLs carry no ids; set by BlogModel::insert' AFTER owner_id;

UPDATE blogs SET media_key = LOWER(HEX(RANDOM_BYTES(8))) WHERE media_key IS NULL;

ALTER TABLE blogs
    MODIFY COLUMN media_key CHAR(16) NOT NULL COMMENT 'Random folder name for the blog uploads, so image URLs carry no ids; set by BlogModel::insert',
    ADD UNIQUE KEY uq_media_key (media_key);
