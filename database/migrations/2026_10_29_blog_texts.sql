CREATE TABLE IF NOT EXISTS blog_texts (
    blog_id INT NOT NULL,
    locale VARCHAR(5) NOT NULL,
    text_key VARCHAR(150) NOT NULL,
    value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (blog_id, locale, text_key),
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Per-language wording of a blog''s texts';
