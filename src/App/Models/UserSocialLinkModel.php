<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * UserSocialLinkModel
 *
 * Manages user social media links and external profiles.
 * Supports dynamic network types with ordered display priority.
 */
class UserSocialLinkModel extends AppModel
{
    /**
     * List all social links for a user.
     *
     * Returns links ordered by priority (website, github, twitter, etc.)
     * with custom networks sorted alphabetically at the end.
     *
     * @param int $userId User ID
     * @return array Array of social links
     */
    public function listByUser(int $userId): array
    {
        $sql = 'SELECT network, url
        FROM user_social_links
        WHERE user_id = ?
          AND url IS NOT NULL
          AND url <> ""
        ORDER BY
          CASE network
            WHEN "website" THEN 1
            WHEN "github"  THEN 2
            WHEN "twitter" THEN 3
            WHEN "x"       THEN 4
            WHEN "linkedin" THEN 5
            WHEN "instagram" THEN 6
            WHEN "youtube" THEN 7
            ELSE 100
          END ASC,
          network ASC';
        
        $stmt = $this->database->query($sql, [$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get social links as associative array.
     *
     * Returns network => url mapping for easy template access.
     *
     * @param int $userId User ID
     * @return array Associative array of network => url
     */
    public function getKeyValueArrayLinks(int $userId): array
    {
        $links = [];
        $rows = $this->listByUser($userId);

        foreach ($rows as $row) {
            $links[$row['network']] = $row['url'];
        }

        return $links;
    }

    /**
     * Upsert a social link for a user.
     *
     * Insert or update a social link. Deletes the link if URL is empty.
     *
     * @param int $userId User ID
     * @param string $network Network name (e.g., 'github', 'twitter')
     * @param string|null $url URL to the profile (null to delete)
     * @return void
     */
    public function upsertLink(int $userId, string $network, ?string $url): void
    {
        // delete the link if URL is empty to keep the table clean
        if ($url === null || $url === '') {
            $deleteSql = 'DELETE FROM user_social_links WHERE user_id = ? AND network = ?';
            $this->database->execute($deleteSql, [$userId, $network]);

            return;
        }
        
        // insert or update link using ON DUPLICATE KEY to handle race conditions
        $sql = 'INSERT INTO user_social_links (user_id, network, url)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE url = VALUES(url)';
        $this->database->execute($sql, [$userId, $network, $url]);
    }

    /**
     * Delete all social links for a user.
     *
     * Used during account deletion to remove PII.
     *
     * @param int $userId User ID
     * @return bool True on success
     */
    public function deleteByUserId(int $userId): bool
    {
        $sql = 'DELETE FROM user_social_links WHERE user_id = ?';
        $rowCount = $this->database->execute($sql, [$userId]);

        return $rowCount > 0;
    }
}
