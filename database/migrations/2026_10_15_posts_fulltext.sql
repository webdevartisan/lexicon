-- Full-text index behind the /discover post search, which used to scan every
-- post body with LIKE '%term%'.

ALTER TABLE posts ADD FULLTEXT INDEX ft_title_content (title, content);
