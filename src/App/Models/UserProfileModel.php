<?php

declare(strict_types=1);

namespace App\Models;

use App\Resources\UserProfileResource;
use Exception;
use PDO;

/**
 * UserProfileModel
 *
 * Manages user profile data including bio, avatar, location, and public visibility.
 * Profiles are created on-demand; the public URL is keyed on users.handle.
 */
class UserProfileModel extends AppModel
{
    /**
     * Find or create a user profile.
     *
     * Ensures every user has a profile record, creating one if missing.
     *
     * @param  int  $userId  User ID
     * @return array<string, mixed> Profile data
     */
    public function findOrCreate(int $userId): array
    {
        $sql = 'SELECT * FROM user_profiles WHERE user_id = ?';
        $stmt = $this->database->query($sql, [$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if ($row) {
            return $row;
        }

        // create a default profile if none exists using INSERT IGNORE to handle race conditions
        $insertSql = 'INSERT IGNORE INTO user_profiles (user_id) VALUES (?)';
        $this->database->execute($insertSql, [$userId]);

        $stmt = $this->database->query($sql, [$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get user's avatar URL.
     *
     * @param  int  $userId  User ID
     * @return array<string, mixed> Avatar data or empty array
     */
    public function getProfileAvatar(int $userId): array
    {
        $sql = 'SELECT avatar_url FROM user_profiles WHERE user_id = ? LIMIT 1';
        $stmt = $this->database->query($sql, [$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return $row;
    }

    /**
     * Upsert user profile data.
     *
     * Insert or update profile fields dynamically based on provided data.
     * Column names are validated to prevent SQL injection.
     *
     * @param  int  $userId  User ID
     * @param  array<string, mixed>  $data  Associative array of column => value pairs
     *
     * @throws Exception If invalid column name provided
     */
    public function upsert(int $userId, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = [];
        $placeholders = [];
        $updates = [];
        $params = [$userId]; // start with user_id as first parameter

        foreach ($data as $k => $v) {
            // validate column names to prevent SQL injection via dynamic keys
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) {
                throw new Exception("Invalid column name '{$k}' in upsert.");
            }

            $columns[] = $k;
            $placeholders[] = '?';
            $updates[] = "{$k} = VALUES({$k})";
            $params[] = $v;
        }

        $sql = 'INSERT INTO user_profiles (user_id, '.implode(', ', $columns).')
            VALUES (?'.str_repeat(', ?', count($columns)).')
            ON DUPLICATE KEY UPDATE '.implode(', ', $updates);

        $this->database->execute($sql, $params);
    }

    /**
     * Update profile by user ID.
     *
     * Updates specific profile fields for a user. Used by deletion service
     * to clear PII during pseudonymization.
     *
     * @param  int  $userId  User ID
     * @param  array<string, mixed>  $data  Associative array of column => value pairs
     * @return bool True on success
     *
     * @throws Exception If invalid column name provided
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
                throw new Exception("Invalid column name '{$k}' in updateByUserId.");
            }

            $sets[] = "{$k} = ?";
            $params[] = $v;
        }

        // add user_id as the final parameter
        $params[] = $userId;

        $sql = 'UPDATE user_profiles SET '.implode(', ', $sets).' WHERE user_id = ?';

        $rowCount = $this->database->execute($sql, $params);

        return $rowCount > 0;
    }

    /**
     * Find a public profile by handle.
     *
     * Joins user, profile, and preferences data for public display.
     * Returns null if profile not found.
     *
     * @param  string  $handle  Profile handle
     * @return UserProfileResource|null Profile resource or null
     */
    public function findByHandle(string $handle): ?UserProfileResource
    {
        $sql = '
            SELECT
                u.id                    AS user_id,
                u.handle,
                u.display_name_cached,
                u.posts_count,
                u.comments_received_count,
                u.created_at            AS user_created_at,
                up.bio,
                up.avatar_url,
                up.location,
                up.occupation,
                up.is_public,
                up.created_at           AS profile_created_at,
                up.updated_at           AS profile_updated_at,
                pref.display_name_preference,
                pref.default_post_visibility,
                pref.timezone
            FROM user_profiles up
            INNER JOIN users u
                ON u.id = up.user_id
            LEFT JOIN user_preferences pref
                ON pref.user_id = u.id
            -- A suspended or deleted account has no public page, whatever its
            -- is_public flag says; the profile would otherwise outlive the ban.
            WHERE u.handle = ?
              AND u.is_active = 1
              AND u.deleted_at IS NULL
            LIMIT 1
        ';

        $stmt = $this->database->query($sql, [$handle]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        // wrap raw data in resource for standardized access
        return new UserProfileResource($row);
    }

    /**
     * The handle a profile is reachable at, or null when it is not public.
     *
     * Callers treat null as "render plain text, not a link" without learning
     * whether a private profile exists.
     *
     * @param  int  $userId  User ID to look up
     * @return string|null Handle when publicly reachable, null otherwise
     */
    public function publicHandleFor(int $userId): ?string
    {
        $sql = '
            SELECT u.handle
            FROM users u
            INNER JOIN user_profiles up ON up.user_id = u.id
            WHERE u.id = ? AND up.is_public = 1
              AND u.is_active = 1 AND u.deleted_at IS NULL
            LIMIT 1
        ';

        $handle = $this->database->query($sql, [$userId])->fetchColumn();

        return is_string($handle) && $handle !== '' ? $handle : null;
    }

    /**
     * Update a user's basic profile fields.
     *
     * Simple persistence layer for profile updates from account settings.
     *
     * @param  int  $userId  User ID
     * @param  array<string, mixed>  $data  Profile data (bio, avatar_url, is_public)
     * @return bool True on success
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $sql = '
            UPDATE user_profiles
            SET bio = ?,
                avatar_url = ?,
                is_public = ?
            WHERE user_id = ?
        ';

        $params = [
            $data['bio'] ?? null,
            $data['avatar_url'] ?? null,
            !empty($data['is_public']) ? 1 : 0,
            $userId,
        ];

        $rowCount = $this->database->execute($sql, $params);

        return $rowCount > 0;
    }
}
