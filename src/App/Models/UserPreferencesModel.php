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
     * @return array Preferences data
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
     * Upsert user preferences.
     *
     * Insert or update all preference fields with provided values.
     *
     * @param  int  $userId  User ID
     * @param  array  $data  Preferences data
     */
    public function upsert(int $userId, array $data): void
    {
        $sql = 'INSERT INTO user_preferences
                (user_id, display_name_preference, default_post_visibility, timezone, notify_comments, notify_likes)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  display_name_preference = VALUES(display_name_preference),
                  default_post_visibility = VALUES(default_post_visibility),
                  timezone = VALUES(timezone),
                  notify_comments = VALUES(notify_comments),
                  notify_likes = VALUES(notify_likes)';

        $this->database->execute($sql, [
            $userId,
            $data['display_name_preference'] ?? 'username',
            $data['default_post_visibility'] ?? 'public',
            $data['timezone'] ?? null,
            (int) ($data['notify_comments'] ?? 1),
            (int) ($data['notify_likes'] ?? 1),
        ]);
    }

    /**
     * Update preferences by user ID.
     *
     * Updates specific preference fields for a user. Used by deletion service
     * to reset preferences during pseudonymization.
     *
     * @param  int  $userId  User ID
     * @param  array  $data  Associative array of column => value pairs
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
}
