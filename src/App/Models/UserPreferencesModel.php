<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * UserPreferencesModel
 *
 * Manages user preferences including display name format, post visibility defaults,
 * timezone, notification settings, and default blog selection.
 */
class UserPreferencesModel extends AppModel
{
    /**
     * Find or create user preferences.
     *
     * Ensures every user has a preferences record, creating one if missing.
     *
     * @param  int  $userId  User ID
     * @return array<string, mixed> Preferences data
     */
    public function findOrCreate(int $userId): array
    {
        $stmt = $this->database->query('SELECT * FROM user_preferences WHERE user_id = ?', [$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if ($row) {
            return $row;
        }

        // create default preferences if none exist
        // INSERT IGNORE silently skips if record already exists (race condition safety)
        $this->database->execute('INSERT IGNORE INTO user_preferences (user_id) VALUES (?)', [$userId]);

        $stmt = $this->database->query('SELECT * FROM user_preferences WHERE user_id = ?', [$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Preference columns upsert() is allowed to write.
     *
     * Keys outside this list are ignored, so caller-supplied array keys can
     * never reach the SQL string.
     */
    private const UPSERT_COLUMNS = [
        'display_name_preference',
        'default_post_visibility',
        'timezone',
        'notify_comments',
        'notify_likes',
        'notify_post_status',
        'notify_role_changes',
        'notify_invites',
    ];

    /**
     * Upsert user preferences.
     *
     * Only the keys present in $data are written. Columns left out keep whatever
     * they already hold, so the notifications form cannot reset the profile form's
     * fields (and vice versa). A row that does not exist yet is created with the
     * schema defaults filling in everything $data does not mention.
     *
     * @param  int  $userId  User ID
     * @param  array<string, mixed>  $data  Preferences data
     */
    public function upsert(int $userId, array $data): void
    {
        $columns = [];
        $values = [];

        foreach (self::UPSERT_COLUMNS as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $columns[] = $column;
            $values[] = str_starts_with($column, 'notify_')
                ? (int) $data[$column]
                : $data[$column];
        }

        // nothing recognised to write, but the caller still expects a row to exist
        if (!$columns) {
            $this->database->execute('INSERT IGNORE INTO user_preferences (user_id) VALUES (?)', [$userId]);

            return;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $assignments = implode(', ', array_map(
            fn (string $column): string => "{$column} = VALUES({$column})",
            $columns
        ));

        $sql = 'INSERT INTO user_preferences (user_id, '.implode(', ', $columns).')
                VALUES (?, '.$placeholders.')
                ON DUPLICATE KEY UPDATE '.$assignments;

        $this->database->execute($sql, array_merge([$userId], $values));
    }

    /**
     * Update preferences by user ID.
     *
     * Updates specific preference fields for a user. Used by deletion service
     * to reset preferences during pseudonymization.
     *
     * @param  int  $userId  User ID
     * @param  array<string, mixed>  $data  Associative array of column => value pairs
     * @return bool True on success
     */
    public function updateByUserId(int $userId, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $sets = [];
        $params = [];

        foreach ($data as $k => $v) {
            // validate column names to prevent SQL injection via dynamic keys
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) {
                throw new \Exception("Invalid column name '{$k}' in updateByUserId.");
            }

            $sets[] = "{$k} = ?";
            $params[] = $v;
        }

        // append user_id as final parameter for WHERE clause
        $params[] = $userId;

        $sql = 'UPDATE user_preferences SET '.implode(', ', $sets).' WHERE user_id = ?';

        $rowCount = $this->database->execute($sql, $params);

        return $rowCount > 0;
    }

    /**
     * Set user's default blog ID.
     *
     * Store the user's preferred blog for quick access in dashboard navigation.
     * Invalidates navigation cache when blog preference changes.
     *
     * @param  int  $userId  User ID
     * @param  int  $blogId  Blog ID to set as default
     * @return bool True on success
     */
    public function setDefaultBlogId(int $userId, int $blogId): bool
    {
        $sql = 'INSERT INTO user_preferences (user_id, default_blog_id, updated_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    default_blog_id = VALUES(default_blog_id),
                    updated_at = NOW()';

        $rowCount = $this->database->execute($sql, [$userId, $blogId]);

        // invalidate navigation cache when blog preference changes
        if ($rowCount > 0) {
            cache()->deletePattern('*sidebar:nav-structure*');
        }

        return $rowCount > 0;
    }

    /**
     * Get user's default blog ID.
     *
     * Retrieve the user's preferred default blog for dashboard navigation.
     *
     * @param  int  $userId  User ID
     * @return int|null Blog ID or null if not set
     */
    public function getDefaultBlogId(int $userId): ?int
    {
        $sql = 'SELECT default_blog_id FROM user_preferences WHERE user_id = ? LIMIT 1';
        $stmt = $this->database->query($sql, [$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || empty($result['default_blog_id'])) {
            return null;
        }

        return (int) $result['default_blog_id'];
    }

    /**
     * Clear user's default blog preference.
     *
     * Used when a user deletes their last blog or needs to reset preference.
     *
     * @param  int  $userId  User ID
     * @return bool True on success
     */
    public function clearDefaultBlogId(int $userId): bool
    {
        $sql = 'UPDATE user_preferences 
                SET default_blog_id = NULL, updated_at = NOW()
                WHERE user_id = ?';

        $rowCount = $this->database->execute($sql, [$userId]);

        return $rowCount > 0;
    }

    /**
     * Check if blog is user's default.
     *
     * @param  int  $userId  User ID
     * @param  int  $blogId  Blog ID
     */
    public function isDefaultBlog(int $userId, int $blogId): bool
    {
        $currentDefaultBlogId = $this->getDefaultBlogId($userId);

        return $currentDefaultBlogId !== null && (int) $currentDefaultBlogId === $blogId;
    }

    /**
     * Notification preference keys gated by this accessor.
     *
     * Kept as an allowlist so unknown column names cannot be queried.
     */
    public const NOTIFY_KEYS = [
        'notify_comments',
        'notify_likes',
        'notify_post_status',
        'notify_role_changes',
        'notify_invites',
    ];

    /**
     * Read a single notification preference for a user.
     *
     * Returns true when no preference row exists yet — every notify column
     * defaults to TRUE at the schema level, so absence means opted-in.
     *
     * @param  int  $userId  User ID
     * @param  string  $key  One of self::NOTIFY_KEYS
     * @return bool Whether email delivery for this event family is enabled
     *
     * @throws \InvalidArgumentException If $key is not in NOTIFY_KEYS
     */
    public function notificationPreference(int $userId, string $key): bool
    {
        if (!in_array($key, self::NOTIFY_KEYS, true)) {
            throw new \InvalidArgumentException("Unknown notification preference key: {$key}");
        }

        $sql = "SELECT {$key} FROM user_preferences WHERE user_id = ? LIMIT 1";
        $row = $this->database->query($sql, [$userId])->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return true;
        }

        return (bool) $row[$key];
    }
}
