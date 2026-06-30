-- Per-blog media library. One row per file on disk so we can browse,
-- search and clean up uploads instead of write-and-forget storage.
--
-- `disk_path` is relative to /public so the URL is just '/' + disk_path,
-- which is also what posts.featured_image / branding fields already store.
-- `source` records where the row came from so we can tell deliberate
-- library uploads from images that flowed in through the post editor or
-- the branding screens, and from one-off backfilled rows.

CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    disk_path VARCHAR(500) NOT NULL,
    url VARCHAR(500) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) DEFAULT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    extension VARCHAR(10) DEFAULT NULL,
    size_bytes INT NOT NULL DEFAULT 0,
    width INT DEFAULT NULL,
    height INT DEFAULT NULL,
    source ENUM('upload','post_image','branding','backfill') NOT NULL DEFAULT 'upload',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_blog_path (blog_id, disk_path),
    INDEX idx_blog_created (blog_id, created_at),
    INDEX idx_blog_source (blog_id, source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Per-blog index of uploaded media files';
