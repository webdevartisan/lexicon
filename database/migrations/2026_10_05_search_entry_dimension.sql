-- Insights SEO page: the first page of visits that came from a search engine,
-- kept per blog and for the platform's pages so it lasts past raw retention.
-- Older days fill in on the next full rebuild of the raw window.

ALTER TABLE analytics_daily_dimensions
    MODIFY dimension ENUM('channel','source','utm_source','utm_medium','utm_campaign','device','browser','os','country','locale',
                          'page','blog','lexicon','entry','exit','next','hour','category','tag','author','search_entry') NOT NULL
        COMMENT 'search_entry: the first page of visits that came from a search engine';
