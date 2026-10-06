-- Sign-ups, with where the visit that led to them came from, for the admin
-- Sign-ups page. Each row holds the day and the visit's sources.

CREATE TABLE IF NOT EXISTS traffic_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event ENUM('signup') NOT NULL,
    day DATE NOT NULL COMMENT 'UTC',
    channel ENUM('direct','internal','search','social','email','referral') NOT NULL COMMENT 'How the visit began',
    referrer_source VARCHAR(60) DEFAULT NULL COMMENT 'Friendly name or host of the site the visit began on',
    utm_source VARCHAR(100) DEFAULT NULL,
    utm_medium VARCHAR(100) DEFAULT NULL,
    utm_campaign VARCHAR(100) DEFAULT NULL,
    came_from VARCHAR(60) DEFAULT NULL COMMENT 'The last page read before signing up: a platform page type or blog:{id}',
    PRIMARY KEY (id),
    INDEX idx_traffic_events_day (event, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sign-ups from counted visits, with the visit''s sources and no visitor or account id';
