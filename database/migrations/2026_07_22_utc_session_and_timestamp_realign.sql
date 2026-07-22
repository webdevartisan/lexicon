-- ----------------------------------------------------------------------------
-- Re-anchor the timestamps that were written as UTC strings.
--
-- TIMESTAMP columns convert on read and write using the session zone. The host
-- sits at GMT+3, so everything PHP wrote as a UTC string, plus everything
-- written with UTC_TIMESTAMP(), landed three hours behind the real instant.
-- Columns filled by NOW() or by a CURRENT_TIMESTAMP default landed correct.
-- Each convention was self consistent so nothing looked broken, but they
-- disagreed with each other. A user's last_login read three hours earlier than
-- their own created_at.
--
-- The connection now pins the session to UTC (see config/database.php), which
-- makes reads return the true stored instant. This realigns the rows that were
-- written under the old behaviour so they still mean what they meant before.
--
-- The scheduler tables are untouched on purpose. next_run_at and friends are
-- DATETIME, which never converted, so they were right all along.
--
-- Run once. A second run would shift everything again. The insert below is the
-- guard, filename is unique and the client stops on error.
-- ----------------------------------------------------------------------------

INSERT INTO migrations (filename)
VALUES ('2026_07_22_utc_session_and_timestamp_realign.sql');

-- Still on the host zone here, so what these read back is the UTC value that
-- was originally intended. Stash it as text before flipping the session over.
CREATE TEMPORARY TABLE tz_posts AS
SELECT id, DATE_FORMAT(published_at, '%Y-%m-%d %H:%i:%s') AS published_at
FROM posts
WHERE published_at IS NOT NULL;

CREATE TEMPORARY TABLE tz_blogs AS
SELECT id,
       DATE_FORMAT(published_at, '%Y-%m-%d %H:%i:%s') AS published_at,
       DATE_FORMAT(archived_at, '%Y-%m-%d %H:%i:%s') AS archived_at
FROM blogs
WHERE published_at IS NOT NULL OR archived_at IS NOT NULL;

CREATE TEMPORARY TABLE tz_users AS
SELECT id, DATE_FORMAT(last_login, '%Y-%m-%d %H:%i:%s') AS last_login
FROM users
WHERE last_login IS NOT NULL;

CREATE TEMPORARY TABLE tz_notifications AS
SELECT id, DATE_FORMAT(read_at, '%Y-%m-%d %H:%i:%s') AS read_at
FROM notifications
WHERE read_at IS NOT NULL;

CREATE TEMPORARY TABLE tz_invitations AS
SELECT id,
       DATE_FORMAT(expires_at, '%Y-%m-%d %H:%i:%s') AS expires_at,
       DATE_FORMAT(accepted_at, '%Y-%m-%d %H:%i:%s') AS accepted_at,
       DATE_FORMAT(declined_at, '%Y-%m-%d %H:%i:%s') AS declined_at
FROM blog_invitations
WHERE expires_at IS NOT NULL
   OR accepted_at IS NOT NULL
   OR declined_at IS NOT NULL;

CREATE TEMPORARY TABLE tz_post_reviewers AS
SELECT id, DATE_FORMAT(assigned_at, '%Y-%m-%d %H:%i:%s') AS assigned_at
FROM post_reviewers
WHERE assigned_at IS NOT NULL;

SET time_zone = '+00:00';

-- Writing the same strings back under UTC stores the instant they always meant.
-- updated_at is assigned to itself so ON UPDATE CURRENT_TIMESTAMP stays quiet,
-- otherwise this would stamp every row as edited today.
UPDATE posts p
JOIN tz_posts t ON t.id = p.id
SET p.published_at = t.published_at,
    p.updated_at = p.updated_at;

UPDATE blogs b
JOIN tz_blogs t ON t.id = b.id
SET b.published_at = t.published_at,
    b.archived_at = t.archived_at,
    b.updated_at = b.updated_at;

UPDATE users u
JOIN tz_users t ON t.id = u.id
SET u.last_login = t.last_login,
    u.updated_at = u.updated_at;

UPDATE notifications n
JOIN tz_notifications t ON t.id = n.id
SET n.read_at = t.read_at;

UPDATE blog_invitations i
JOIN tz_invitations t ON t.id = i.id
SET i.expires_at = t.expires_at,
    i.accepted_at = t.accepted_at,
    i.declined_at = t.declined_at;

UPDATE post_reviewers r
JOIN tz_post_reviewers t ON t.id = r.id
SET r.assigned_at = t.assigned_at;
