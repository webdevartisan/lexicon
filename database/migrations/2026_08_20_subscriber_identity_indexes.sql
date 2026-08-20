-- Index blog_subscribers for the reader's Subscriptions page.
--
-- The list matches on either identity, `WHERE (user_id = ? OR email = ?)`, so a
-- guest who subscribed before signing up still sees and can manage those rows.
--
-- user_id was already indexed: InnoDB built one for the foreign key and named it
-- after the column. The rename only makes schema.sql tell the truth and match
-- how every other table here names its indexes. The email index is the one that
-- earns its keep, because email is the second column of uq_subscriber_blog_email
-- and so was unusable on its own. With both in place the optimiser resolves the
-- OR as an index merge union instead of scanning the table.

ALTER TABLE blog_subscribers
    RENAME INDEX user_id TO idx_subscriber_user;

CREATE INDEX idx_subscriber_email ON blog_subscribers (email);
