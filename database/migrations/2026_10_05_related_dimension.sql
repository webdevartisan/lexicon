-- Insights Engagement and post pages: posts readers opened from another post's
-- related links, kept per blog and per post (the post they left).

ALTER TABLE analytics_daily_dimensions
    MODIFY dimension ENUM('channel','source','utm_source','utm_medium','utm_campaign','device','browser','os','country','locale',
                          'page','blog','lexicon','entry','exit','next','hour','category','tag','author','search_entry','related') NOT NULL
        COMMENT 'search_entry: the first page of visits that came from a search engine. related: posts opened from a related link';
