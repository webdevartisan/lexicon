-- A view reached from another part of Lexicon (Discover, the home page, another
-- blog) gets its own channel, so blog owners can see what the platform sends them.

ALTER TABLE traffic_hits
    MODIFY channel ENUM('direct','internal','lexicon','search','social','email','referral') NOT NULL COMMENT 'internal: from the same blog, or between platform pages. lexicon: from another part of Lexicon',
    MODIFY referrer_source VARCHAR(60) DEFAULT NULL COMMENT 'Friendly name for a known host, e.g. Google. On lexicon views, the kind of page or blog:{id} the reader came from';

ALTER TABLE traffic_daily_dimensions
    MODIFY dimension ENUM('channel','source','utm_source','utm_medium','utm_campaign','device','browser','os','country','locale','page','blog','lexicon') NOT NULL;
