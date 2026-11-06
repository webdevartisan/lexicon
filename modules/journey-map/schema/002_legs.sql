CREATE TABLE IF NOT EXISTS journey_legs (
    from_post_id INT NOT NULL,
    to_post_id INT NOT NULL,
    blog_id INT NOT NULL,
    status ENUM('waiting','road','none','failed') NOT NULL DEFAULT 'waiting',
    km DECIMAL(8,1) NULL,
    line MEDIUMTEXT NULL,
    from_hash CHAR(16) NOT NULL,
    to_hash CHAR(16) NOT NULL,
    attempts TINYINT NOT NULL DEFAULT 0,
    planned_at TIMESTAMP NULL,
    PRIMARY KEY (from_post_id, to_post_id),
    INDEX idx_blog (blog_id),
    INDEX idx_status (status),
    FOREIGN KEY (from_post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (to_post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
