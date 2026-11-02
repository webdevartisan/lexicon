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
     * What has already been done on the blog, plus whether the person viewing
     * the card has filled in their profile.
     *
     * Readers count as proof the blog was shared, since people often copy the
     * link from the address bar rather than the card: a confirmed subscriber
     * other than the owner, or a comment from someone outside the team.
     *
     * @return array{published_post: bool, has_look: bool, has_profile: bool, has_readers: bool}
     */
    public function forOwner(int $blogId, int $userId): array
    {
        $sql = "SELECT
                EXISTS (SELECT 1 FROM posts WHERE blog_id = b.id AND status = 'published') AS published_post,
                (COALESCE(TRIM(b.description), '') <> ''
                    OR EXISTS (SELECT 1 FROM blog_settings
                               WHERE blog_id = b.id AND COALESCE(logo_path, '') <> '')) AS has_look,
                {$this->hasProfileSql()} AS has_profile,
                (EXISTS (SELECT 1 FROM blog_subscribers s
                         WHERE s.blog_id = b.id AND s.confirmed_at IS NOT NULL
                           AND (s.user_id IS NULL OR s.user_id <> b.owner_id))
                    OR EXISTS (SELECT 1 FROM comments c JOIN posts p ON p.id = c.post_id
                               WHERE p.blog_id = b.id AND c.status = 'approved' AND c.user_id <> b.owner_id
                                 AND NOT EXISTS (SELECT 1 FROM blog_users bu
                                                 WHERE bu.blog_id = b.id AND bu.user_id = c.user_id))) AS has_readers
            FROM blogs b
            WHERE b.id = ?";

        $row = $this->database->query($sql, [$userId, $blogId])->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'published_post' => (bool) ($row['published_post'] ?? false),
            'has_look' => (bool) ($row['has_look'] ?? false),
            'has_profile' => (bool) ($row['has_profile'] ?? false),
            'has_readers' => (bool) ($row['has_readers'] ?? false),
        ];
    }

    /**
     * Whether the owner started another blog before this one.
     */
    public function ownsOlderBlog(int $ownerId, int $blogId): bool
    {
        $sql = 'SELECT EXISTS (SELECT 1 FROM blogs WHERE owner_id = ? AND id < ?)';

        return (bool) $this->database->query($sql, [$ownerId, $blogId])->fetchColumn();
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
                {$this->hasProfileSql()} AS has_profile";

        $params = [$blogId, $userId, $blogId, $userId, $blogId, $userId, $userId];
        $row = $this->database->query($sql, $params)->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'wrote_post' => (bool) ($row['wrote_post'] ?? false),
            'submitted_post' => (bool) ($row['submitted_post'] ?? false),
            'saved_draft' => (bool) ($row['saved_draft'] ?? false),
            'has_profile' => (bool) ($row['has_profile'] ?? false),
        ];
    }

    /**
     * A profile counts once it has a bio or a photo. Takes the user id as one placeholder.
     */
    private function hasProfileSql(): string
    {
        return "EXISTS (SELECT 1 FROM user_profiles WHERE user_id = ?
                        AND (COALESCE(TRIM(bio), '') <> '' OR COALESCE(avatar_url, '') <> ''))";
    }
}
