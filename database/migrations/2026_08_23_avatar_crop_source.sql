-- Keep the uploaded avatar original so it can be re-cropped without re-uploading,
-- and remember the last crop rectangle to preload the cropper.
ALTER TABLE user_profiles
    ADD COLUMN avatar_source_url VARCHAR(512) DEFAULT NULL AFTER avatar_url,
    ADD COLUMN avatar_crop VARCHAR(64) DEFAULT NULL COMMENT 'Last crop rect as x,y,w,h in source pixels' AFTER avatar_source_url;
