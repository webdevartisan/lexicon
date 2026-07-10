-- Post engagement: likes, bookmarks, threaded replies, per-blog reply moderation toggle.

CREATE TABLE IF NOT EXISTS post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_like_post_user (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_like_post (post_id),
    INDEX idx_like_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One row per user per liked post';

CREATE TABLE IF NOT EXISTS post_bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bookmark_post_user (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bookmark_post (post_id),
    INDEX idx_bookmark_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One row per user per saved post';

ALTER TABLE comments
    ADD COLUMN parent_comment_id INT DEFAULT NULL COMMENT 'Threaded replies; NULL = top-level' AFTER user_id,
    ADD CONSTRAINT fk_comment_parent FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    ADD INDEX idx_comment_parent (parent_comment_id);

ALTER TABLE blog_settings
    ADD COLUMN replies_auto_publish TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'When on, replies publish instantly; owner moderates retroactively' AFTER comments_enabled;
