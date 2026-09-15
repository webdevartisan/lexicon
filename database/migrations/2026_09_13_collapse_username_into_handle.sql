-- One handle per person. The editable profile slug moves onto users as `handle`
-- and user_profiles.slug goes away, so the two can no longer drift apart.

-- Slug wins where the two disagree: it is the one the person chose and the one
-- already in their public URL. The unique index rejects a colliding move rather
-- than quietly skipping it.
UPDATE users u
    JOIN user_profiles p ON p.user_id = u.id
   SET u.username = p.slug
 WHERE p.slug IS NOT NULL
   AND p.slug <> ''
   AND p.slug <> u.username;

ALTER TABLE users
    CHANGE COLUMN username handle VARCHAR(100) NOT NULL COMMENT 'Public @tag, editable on the profile page';

-- idx_username duplicated the unique index on the same column.
ALTER TABLE users DROP INDEX idx_username;
ALTER TABLE users RENAME INDEX username TO uq_users_handle;

ALTER TABLE user_profiles DROP COLUMN slug;

-- The enum value is renamed in three steps because rows still hold 'username'.
ALTER TABLE user_preferences
    MODIFY COLUMN display_name_preference ENUM('name','username','handle') NOT NULL DEFAULT 'username';

UPDATE user_preferences SET display_name_preference = 'handle' WHERE display_name_preference = 'username';

ALTER TABLE user_preferences
    MODIFY COLUMN display_name_preference ENUM('name','handle') NOT NULL DEFAULT 'handle';

-- Stored notification payloads carry the old key names. buildMailable() only
-- ever sees a live payload, but the in-app list reads these rows back, so they
-- move with the code. The values are left alone: a notification records the
-- handle as it stood when the event happened.
UPDATE notifications
   SET data = CAST(
           REPLACE(
           REPLACE(
           REPLACE(
           REPLACE(
           REPLACE(
           REPLACE(CAST(data AS CHAR),
               '"author_username"', '"author_handle"'),
               '"reviewer_username"', '"reviewer_handle"'),
               '"former_reviewer_username"', '"former_reviewer_handle"'),
               '"assigned_by_username"', '"actor_handle"'),
               '"changed_by_username"', '"actor_handle"'),
               '"removed_by_username"', '"actor_handle"')
           AS JSON)
 WHERE data LIKE '%_username%';
