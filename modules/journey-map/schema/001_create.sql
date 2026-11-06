CREATE TABLE IF NOT EXISTS journey_stops (
    post_id INT NOT NULL PRIMARY KEY,
    blog_id INT NOT NULL,
    place_name VARCHAR(160) NOT NULL,
    lat DECIMAL(9,6) NOT NULL,
    lng DECIMAL(9,6) NOT NULL,
    place_precision ENUM('exact','area') NOT NULL DEFAULT 'exact',
    stop_number INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_blog (blog_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
