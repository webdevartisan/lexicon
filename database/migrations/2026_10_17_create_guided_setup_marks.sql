-- ----------------------------------------------------------------------------
-- The "Your next steps" card derives most progress from existing data. This
-- table keeps only what can't be derived, per person and per blog.

CREATE TABLE IF NOT EXISTS guided_setup_marks (
    user_id INT NOT NULL,
    blog_id INT NOT NULL,
    mark VARCHAR(32) NOT NULL COMMENT 'hidden, dismissed, link_copied, look_chosen, subscribers_seen, profile_saved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, blog_id, mark),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    INDEX idx_guided_setup_blog (blog_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Guided setup state that cannot be derived from other tables';
