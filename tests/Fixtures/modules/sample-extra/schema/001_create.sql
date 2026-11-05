CREATE TABLE IF NOT EXISTS sample_extra_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    post_id INT NULL,
    user_id INT NULL,
    note VARCHAR(255) NOT NULL,
    INDEX idx_blog (blog_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
