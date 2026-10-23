<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Reads the guided setup progress that existing data already answers, one
 * query per audience.
 */
class GuidedSetupFactsModel extends AppModel
{
    protected ?string $table = 'blogs';

    /**
     * What the owner has already done on their blog.
     *
     * @return array{published_post: bool, has_look: bool, has_subscriber: bool, has_team: bool}
     */
    public function forOwner(int $blogId, int $ownerId): array
    {
        $sql = "SELECT
                EXISTS (SELECT 1 FROM posts WHERE blog_id = b.id AND status = 'published') AS published_post,
                (COALESCE(TRIM(b.description), '') <> ''
                    OR EXISTS (SELECT 1 FROM blog_settings
                               WHERE blog_id = b.id AND COALESCE(logo_path, '') <> '')) AS has_look,
                EXISTS (SELECT 1 FROM blog_subscribers WHERE blog_id = b.id) AS has_subscriber,
                (EXISTS (SELECT 1 FROM blog_invitations WHERE blog_id = b.id AND invited_by = ?)
                    OR EXISTS (SELECT 1 FROM blog_users WHERE blog_id = b.id AND is_active = 1)) AS has_team
            FROM blogs b
            WHERE b.id = ?";

        $row = $this->database->query($sql, [$ownerId, $blogId])->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'published_post' => (bool) ($row['published_post'] ?? false),
            'has_look' => (bool) ($row['has_look'] ?? false),
            'has_subscriber' => (bool) ($row['has_subscriber'] ?? false),
            'has_team' => (bool) ($row['has_team'] ?? false),
        ];
    }

    /**
     * What a collaborator has already done on someone else's blog.
     *
     * @return array{wrote_post: bool, submitted_post: bool, saved_draft: bool, has_profile: bool}
     */
    public function forMember(int $userId, int $blogId): array
    {
        $sql = "SELECT
                EXISTS (SELECT 1 FROM posts WHERE blog_id = ? AND author_id = ?
                        AND status IN ('pending', 'scheduled', 'published')) AS wrote_post,
                EXISTS (SELECT 1 FROM posts WHERE blog_id = ? AND author_id = ?
                        AND status IN ('pending', 'published')) AS submitted_post,
                EXISTS (SELECT 1 FROM posts WHERE blog_id = ? AND author_id = ?
                        AND status = 'draft') AS saved_draft,
                EXISTS (SELECT 1 FROM user_profiles WHERE user_id = ?
                        AND (COALESCE(TRIM(bio), '') <> '' OR COALESCE(avatar_url, '') <> '')) AS has_profile";

        $params = [$blogId, $userId, $blogId, $userId, $blogId, $userId, $userId];
        $row = $this->database->query($sql, $params)->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'wrote_post' => (bool) ($row['wrote_post'] ?? false),
            'submitted_post' => (bool) ($row['submitted_post'] ?? false),
            'saved_draft' => (bool) ($row['saved_draft'] ?? false),
            'has_profile' => (bool) ($row['has_profile'] ?? false),
        ];
    }
}
