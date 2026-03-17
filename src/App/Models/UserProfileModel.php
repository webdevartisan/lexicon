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
 * Profiles are created on-demand and support public slug-based URLs.
 */
class UserProfileModel extends AppModel
{
    /**
     * Find or create a user profile.
     *
     * Ensures every user has a profile record, creating one if missing.
     *
     * @param int $userId User ID
     * @return array Profile data
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
     * @param int $userId User ID
     * @return array Avatar data or empty array
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
     * @param int $userId User ID
     * @param array $data Associative array of column => value pairs
     * @return void
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
     * @param int $userId User ID
     * @param array $data Associative array of column => value pairs
     * @return bool True on success
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
     * Find a public profile by slug.
     *
     * Joins user, profile, and preferences data for public display.
     * Returns null if profile not found.
     *
     * @param string $slug Profile slug
     * @return UserProfileResource|null Profile resource or null
     */
    public function findBySlug(string $slug): ?UserProfileResource
    {
        $sql = '
            SELECT
                u.id                    AS user_id,
                u.username,
                u.display_name_cached,
                u.posts_count,
                u.comments_received_count,
                up.slug,
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
            WHERE up.slug = ?
            LIMIT 1
        ';

        $stmt = $this->database->query($sql, [$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row === false) {
            return null;
        }

        // wrap raw data in resource for standardized access
        return new UserProfileResource($row);
    }

    /**
     * Check whether a slug is available for assignment.
     *
     * Validates against reserved slugs table and existing user profiles.
     * Used during account settings validation.
     *
     * @param string $slug Slug to check
     * @param int|null $ignoreUserId User ID whose current slug should be ignored
     * @return bool True if available
     */
    public function isSlugAvailable(string $slug, ?int $ignoreUserId = null): bool
    {
        // check reserved slugs first to fail fast on known reserved values
        $sqlReserved = 'SELECT 1 FROM reserved_slugs WHERE slug = ? LIMIT 1';
        $stmtReserved = $this->database->query($sqlReserved, [$slug]);

        if ($stmtReserved->fetchColumn()) {
            return false;
        }

        // check existing user profiles, optionally excluding current user's slug
        $sql = '
            SELECT 1
            FROM user_profiles
            WHERE slug = ?
            '.($ignoreUserId !== null ? 'AND user_id <> ?' : '').'
            LIMIT 1
        ';

        $params = [$slug];
        
        if ($ignoreUserId !== null) {
            $params[] = $ignoreUserId;
        }
        
        $stmt = $this->database->query($sql, $params);

        return $stmt->fetchColumn() === false;
    }

    /**
     * Update a user's profile slug and basic profile fields.
     *
     * Simple persistence layer for profile updates from account settings.
     *
     * @param int $userId User ID
     * @param array $data Profile data (slug, bio, avatar_url, is_public)
     * @return bool True on success
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $sql = '
            UPDATE user_profiles
            SET slug = ?,
                bio = ?,
                avatar_url = ?,
                is_public = ?
            WHERE user_id = ?
        ';

        $params = [
            $data['slug'],
            $data['bio'] ?? null,
            $data['avatar_url'] ?? null,
            !empty($data['is_public']) ? 1 : 0,
            $userId
        ];

        $rowCount = $this->database->execute($sql, $params);
        return $rowCount > 0;
    }
}
