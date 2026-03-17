<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Framework\Database;

/**
 * UserRelationsHelper
 *
 * We provide utilities for managing user-related data across multiple tables
 * in integration tests. Simplifies setup of profiles, social links, and
 * preferences for testing UserDeletionService and similar components.
 */
class UserRelationsHelper
{
    /**
     * Insert user profile data.
     *
     * @param Database $db Database connection
     * @param int $userId User ID
     * @param array $data Profile data (slug, bio, occupation, location, avatar_url)
     * @return int Profile ID (user_id)
     */
    public static function createUserProfile(Database $db, int $userId, array $data = []): int
    {
        $defaults = [
            'slug' => $data['slug'] ?? "user_{$userId}",
            'bio' => $data['bio'] ?? 'Test bio',
            'occupation' => $data['occupation'] ?? 'Software Developer',
            'location' => $data['location'] ?? 'Cyprus',
            'avatar_url' => $data['avatar_url'] ?? '/uploads/avatar.jpg',
            'is_public' => $data['is_public'] ?? true,
        ];

        $profileData = array_merge($defaults, $data);

        $db->query("
            INSERT INTO user_profiles (user_id, slug, bio, occupation, location, avatar_url, is_public)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [
            $userId,
            $profileData['slug'],
            $profileData['bio'],
            $profileData['occupation'],
            $profileData['location'],
            $profileData['avatar_url'],
            $profileData['is_public'],
        ]);

        return $userId;
    }

    /**
     * Insert user social links.
     *
     * @param Database $db Database connection
     * @param int $userId User ID
     * @param array $links Array of ['network' => 'url'] pairs
     * @return array Array of inserted link IDs
     */
    public static function createUserSocialLinks(Database $db, int $userId, array $links = []): array
    {
        // Default social links if none provided
        if (empty($links)) {
            $links = [
                'twitter' => 'https://twitter.com/testuser',
                'github' => 'https://github.com/testuser',
                'linkedin' => 'https://linkedin.com/in/testuser',
            ];
        }

        $ids = [];

        foreach ($links as $network => $url) {
            $db->query("
                INSERT INTO user_social_links (user_id, network, url)
                VALUES (?, ?, ?)
            ", [$userId, $network, $url]);
            
            $ids[] = (int) $db->lastInsertId();
        }

        return $ids;
    }

    /**
     * Insert user preferences.
     *
     * @param Database $db Database connection
     * @param int $userId User ID
     * @param array $preferences Preference data
     * @return int Preferences ID (user_id)
     */
    public static function createUserPreferences(Database $db, int $userId, array $preferences = []): int
    {
        $defaults = [
            'timezone' => $preferences['timezone'] ?? 'Europe/Nicosia',
            'notify_comments' => $preferences['notify_comments'] ?? 1,
            'notify_likes' => $preferences['notify_likes'] ?? 1,
            'display_name_preference' => $preferences['display_name_preference'] ?? 'username',
            'default_post_visibility' => $preferences['default_post_visibility'] ?? 'public',
        ];

        $prefData = array_merge($defaults, $preferences);

        $db->query("
            INSERT INTO user_preferences (
                user_id, 
                timezone, 
                notify_comments, 
                notify_likes,
                display_name_preference,
                default_post_visibility
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ", [
            $userId,
            $prefData['timezone'],
            $prefData['notify_comments'],
            $prefData['notify_likes'],
            $prefData['display_name_preference'],
            $prefData['default_post_visibility'],
        ]);

        return $userId;
    }

    /**
     * Create complete user setup with all related data.
     *
     * Convenience method that creates profile, social links, and preferences
     * for a user in one call.
     *
     * @param Database $db Database connection
     * @param int $userId User ID (from UserFactory)
     * @param array $profileData Optional profile overrides
     * @param array $socialLinks Optional social links
     * @param array $preferences Optional preferences overrides
     * @return array{profileId: int, linkIds: array, preferencesId: int}
     */
    public static function createCompleteUserData(
        Database $db,
        int $userId,
        array $profileData = [],
        array $socialLinks = [],
        array $preferences = []
    ): array {
        return [
            'profileId' => self::createUserProfile($db, $userId, $profileData),
            'linkIds' => self::createUserSocialLinks($db, $userId, $socialLinks),
            'preferencesId' => self::createUserPreferences($db, $userId, $preferences),
        ];
    }

    /**
     * Assert user data has been anonymized.
     *
     * Verifies that PII has been replaced with anonymous values across
     * all user-related tables.
     *
     * @param Database $db Database connection
     * @param int $userId User ID
     * @return void
     */
    public static function assertUserAnonymized(Database $db, int $userId): void
    {
        // Check core user record
        $stmt = $db->query("SELECT email, username, first_name, last_name, password FROM users WHERE id = ?", [$userId]);
        $user = $stmt->fetch();

        expect($user['email'])->toStartWith('deleted_user_');
        expect($user['username'])->toBe("deleted_user_{$userId}");
        expect($user['first_name'])->toBe('Deleted');
        expect($user['last_name'])->toBe('User');
        expect($user['password'])->toBe('');

        // Check profile anonymization
        $stmt = $db->query("SELECT slug, bio, occupation, location, avatar_url FROM user_profiles WHERE user_id = ?", [$userId]);
        $profile = $stmt->fetch();

        expect($profile['slug'])->toBe("deleted_{$userId}");
        expect($profile['bio'])->toBeNull();
        expect($profile['occupation'])->toBeNull();
        expect($profile['location'])->toBeNull();
        expect($profile['avatar_url'])->toBeNull();

        // Check social links deleted
        $stmt = $db->query("SELECT COUNT(*) as count FROM user_social_links WHERE user_id = ?", [$userId]);
        $count = $stmt->fetch()['count'];
        expect($count)->toBe(0);

        // Check preferences reset to defaults
        $stmt = $db->query("SELECT timezone, notify_comments, notify_likes FROM user_preferences WHERE user_id = ?", [$userId]);
        $prefs = $stmt->fetch();

        expect($prefs['timezone'])->toBe('UTC');
        expect($prefs['notify_comments'])->toBe(0);
        expect($prefs['notify_likes'])->toBe(0);
    }

    /**
     * Get social link count for a user.
     *
     * @param Database $db Database connection
     * @param int $userId User ID
     * @return int Number of social links
     */
    public static function getSocialLinkCount(Database $db, int $userId): int
    {
        $stmt = $db->query("SELECT COUNT(*) as count FROM user_social_links WHERE user_id = ?", [$userId]);
        return (int) $stmt->fetch()['count'];
    }
}
