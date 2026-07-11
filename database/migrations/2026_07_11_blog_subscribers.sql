-- Blog subscriptions: readers leave an email per blog and get notified when
-- a new post is published. Token drives one-click unsubscribe links.

CREATE TABLE IF NOT EXISTS blog_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    user_id INT DEFAULT NULL COMMENT 'Set when a logged-in user subscribes',
    email VARCHAR(255) NOT NULL,
    token CHAR(64) NOT NULL COMMENT 'Unsubscribe token',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscriber_blog_email (blog_id, email),
    UNIQUE KEY uq_subscriber_token (token),
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_subscriber_blog (blog_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Per-blog email subscribers for new post notifications';

ALTER TABLE posts
    ADD COLUMN subscribers_notified_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When subscriber emails went out; prevents re-sends on republish';
