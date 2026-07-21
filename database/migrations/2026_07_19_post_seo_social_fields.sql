-- Post-level SEO and social sharing fields. The post editor has collected
-- these since the SEO/Social panels were added, but there was nowhere to
-- store them, so the values were silently dropped on save. This adds the
-- storage; persistence lives in Dashboard\PostController and the meta tags
-- are rendered by the theme layouts.

ALTER TABLE posts
    ADD COLUMN focus_keyword VARCHAR(100) DEFAULT NULL COMMENT 'Primary keyword the author targets; editor guidance only' AFTER timezone,
    ADD COLUMN meta_title VARCHAR(70) DEFAULT NULL COMMENT 'SERP title override; falls back to post title' AFTER focus_keyword,
    ADD COLUMN meta_description VARCHAR(200) DEFAULT NULL COMMENT 'SERP description override; falls back to excerpt' AFTER meta_title,
    ADD COLUMN canonical_url VARCHAR(255) DEFAULT NULL COMMENT 'Canonical URL when the post is republished elsewhere' AFTER meta_description,
    ADD COLUMN meta_noindex TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ask search engines not to index this post' AFTER canonical_url,
    ADD COLUMN meta_nofollow TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ask search engines not to follow links on this post' AFTER meta_noindex,
    ADD COLUMN og_title VARCHAR(70) DEFAULT NULL COMMENT 'Social share title; falls back to meta/post title' AFTER meta_nofollow,
    ADD COLUMN og_description VARCHAR(100) DEFAULT NULL COMMENT 'Social share description; falls back to meta description' AFTER og_title,
    ADD COLUMN og_image VARCHAR(255) DEFAULT NULL COMMENT 'Social share image URL; falls back to featured image' AFTER og_description,
    ADD COLUMN twitter_card_type VARCHAR(30) NOT NULL DEFAULT 'summary_large_image' COMMENT 'summary or summary_large_image' AFTER og_image;
