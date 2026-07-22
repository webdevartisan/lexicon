-- Two things the post editor promised but could not deliver.
--
-- 1. Scheduling. The editor told authors to "set a future date and select
--    Published", but every public query filters on status = 'published' with
--    no check against published_at, so a scheduled post went live instantly.
--    A distinct 'scheduled' status fixes this by construction: the public
--    queries already exclude anything that is not 'published', and
--    `php cli posts:publish-due` promotes posts once their time arrives.
--
-- 2. Social overrides. Facebook, LinkedIn, WhatsApp, Slack and Discord all
--    read the same og: tags, so per-platform text is impossible for them.
--    X is the exception: it reads its own twitter: namespace. These columns
--    hold that one override. NULL means inherit the og: value, which is the
--    behaviour every existing post already has.

ALTER TABLE posts
    MODIFY COLUMN status ENUM('draft','pending','scheduled','published','archived') NOT NULL DEFAULT 'draft',
    ADD COLUMN og_image_alt VARCHAR(255) DEFAULT NULL COMMENT 'Alt text for the social share image; improves accessibility and LinkedIn rendering' AFTER og_image,
    ADD COLUMN twitter_title VARCHAR(70) DEFAULT NULL COMMENT 'X-only title override; falls back to og_title' AFTER twitter_card_type,
    ADD COLUMN twitter_description VARCHAR(200) DEFAULT NULL COMMENT 'X-only description override; falls back to og_description' AFTER twitter_title,
    ADD COLUMN twitter_image VARCHAR(255) DEFAULT NULL COMMENT 'X-only image override; falls back to og_image' AFTER twitter_description;

-- No new index needed: idx_status_published (status, published_at) already
-- covers the due-post sweep.
