<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The latest things that happened on one blog, merged from the tables that record them.
 *
 * There is no event log per blog, so each kind reads its own table and the
 * newest rows across all of them are kept. The caller names the kinds so a
 * person is only shown what their permissions already open to them.
 */
class BlogActivityModel extends AppModel
{
    public const COMMENT = 'comment';

    public const PUBLISHED = 'published';

    public const SUBSCRIBER = 'subscriber';

    public const MEMBER = 'member';

    protected ?string $table = 'blogs';

    /**
     * @param  list<string>  $kinds  Kinds to include, any of the constants above
     * @param  int  $limit  Rows to return, newest first
     * @return list<array{
     *     kind: string, happened_at: string, post_id: int|null,
     *     subject: string|null, actor: string|null, detail: string|null
     * }>
     */
    public function recent(int $blogId, array $kinds, int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        $parts = array_intersect_key($this->selects($limit), array_flip($kinds));

        if ($parts === []) {
            return [];
        }

        $sql = '('.implode(') UNION ALL (', $parts).") ORDER BY happened_at DESC LIMIT {$limit}";
        $rows = $this->database->query($sql, array_fill(0, count($parts), $blogId))->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            return [
                'kind' => (string) $row['kind'],
                'happened_at' => (string) $row['happened_at'],
                'post_id' => $row['post_id'] === null ? null : (int) $row['post_id'],
                'subject' => $row['subject'],
                'actor' => $row['actor'],
                'detail' => $row['detail'],
            ];
        }, $rows);
    }

    /**
     * One query per kind, all with the same six named columns and a single blog id placeholder.
     * Each is capped on its own, so one noisy kind cannot starve the others of a slot. Every part
     * names its columns because the union takes its names from whichever part comes first.
     *
     * @return array<string, string>
     */
    private function selects(int $limit): array
    {
        return [
            self::COMMENT => "SELECT 'comment' AS kind, c.created_at AS happened_at, p.id AS post_id,
                                     p.title AS subject, u.handle AS actor, c.status AS detail
                              FROM comments c
                              JOIN posts p ON p.id = c.post_id
                              LEFT JOIN users u ON u.id = c.user_id
                              WHERE p.blog_id = ? AND c.status <> 'spam'
                                AND c.deleted_at IS NULL AND c.hidden_at IS NULL
                              ORDER BY c.created_at DESC LIMIT {$limit}",
            self::PUBLISHED => "SELECT 'published' AS kind, p.published_at AS happened_at, p.id AS post_id,
                                       p.title AS subject, u.handle AS actor, NULL AS detail
                                FROM posts p
                                JOIN users u ON u.id = p.author_id
                                WHERE p.blog_id = ? AND p.status = 'published' AND p.published_at <= NOW()
                                ORDER BY p.published_at DESC LIMIT {$limit}",
            self::SUBSCRIBER => "SELECT 'subscriber' AS kind, s.confirmed_at AS happened_at, NULL AS post_id,
                                        NULL AS subject, NULL AS actor, NULL AS detail
                                 FROM blog_subscribers s
                                 WHERE s.blog_id = ? AND s.confirmed_at IS NOT NULL
                                 ORDER BY s.confirmed_at DESC LIMIT {$limit}",
            // The owner's own row is left out: they did not join their blog, they made it
            self::MEMBER => "SELECT 'member' AS kind, bu.assigned_at AS happened_at, NULL AS post_id,
                                    NULL AS subject, u.handle AS actor, bu.role AS detail
                             FROM blog_users bu
                             JOIN blogs b ON b.id = bu.blog_id
                             JOIN users u ON u.id = bu.user_id
                             WHERE bu.blog_id = ? AND bu.is_active = 1 AND bu.revoked_at IS NULL
                               AND bu.user_id <> b.owner_id
                             ORDER BY bu.assigned_at DESC LIMIT {$limit}",
        ];
    }
}
