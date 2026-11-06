ALTER TABLE journey_stops
    ADD COLUMN id INT NOT NULL AUTO_INCREMENT FIRST,
    ADD COLUMN position TINYINT NOT NULL DEFAULT 1 AFTER post_id,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id),
    ADD INDEX idx_post (post_id, position);

DROP TABLE IF EXISTS journey_legs;

CREATE TABLE journey_legs (
    blog_id INT NOT NULL,
    from_hash CHAR(16) NOT NULL,
    to_hash CHAR(16) NOT NULL,
    from_lat DECIMAL(9,6) NOT NULL,
    from_lng DECIMAL(9,6) NOT NULL,
    to_lat DECIMAL(9,6) NOT NULL,
    to_lng DECIMAL(9,6) NOT NULL,
    status ENUM('waiting','road','none','failed') NOT NULL DEFAULT 'waiting',
    km DECIMAL(8,1) NULL,
    line MEDIUMTEXT NULL,
    attempts TINYINT NOT NULL DEFAULT 0,
    planned_at TIMESTAMP NULL,
    PRIMARY KEY (blog_id, from_hash, to_hash),
    INDEX idx_status (status),
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
