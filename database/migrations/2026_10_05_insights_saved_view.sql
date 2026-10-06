-- Insights opens where each user left it: the last page and range they looked at.

ALTER TABLE user_preferences
    ADD COLUMN insights_page VARCHAR(20) DEFAULT NULL COMMENT 'Last Insights page opened, where the Insights link resumes' AFTER locale,
    ADD COLUMN insights_range VARCHAR(12) DEFAULT NULL COMMENT 'Last preset range picked on Insights; custom dates are not kept' AFTER insights_page;
