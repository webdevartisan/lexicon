<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use Framework\Database;

/**
 * Everything the control panel knows about one account, gathered for the
 * admin detail page and the edit form.
 *
 * Strictly read-only. The profile and preference models offer findOrCreate(),
 * which inserts a default row when none exists; that must never run here,
 * because looking at an account must not change it. Missing rows come back
 * as null and the pages say so.
 */
class UserDossierService
{
    private const RECENT_LIMIT = 20;

    public function __construct(
        private Database $database,
        private BlogModel $blogs,
        private UserSuspensionService $suspensions,
        private ImpersonationService $impersonation,
    ) {}

    /**
     * @param  array<string, mixed>  $user  The users row
     * @return array<string, mixed>
     */
    public function build(array $user): array
    {
        $userId = (int) $user['id'];

        return [
            'profile' => $this->profile($userId),
            'preferences' => $this->preferences($userId),
            'socialLinks' => $this->socialLinks($userId),
            'siteRoles' => $this->siteRoles($userId),
            'blogs' => $this->blogs->getAccessibleBlogs($userId),
            'pendingEmail' => $this->pendingEmail($userId),
            'pendingErasure' => $this->pendingErasure($userId),
            'counts' => $this->counts($userId),
            'recentPosts' => $this->recentPosts($userId),
            'recentComments' => $this->recentComments($userId),
            'suspension' => $this->suspensions->current($userId),
            'suspensionHistory' => $this->suspensions->history($userId),
            'impersonations' => $this->impersonation->historyFor($userId),
            'activityByUser' => $this->activityBy($userId),
            'activityOnUser' => $this->activity("a.resource_type = 'user' AND a.resource_id = ?", [$userId]),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profile(int $userId): ?array
    {
        return $this->row('SELECT * FROM user_profiles WHERE user_id = ?', [$userId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function preferences(int $userId): ?array
    {
        return $this->row('SELECT * FROM user_preferences WHERE user_id = ?', [$userId]);
    }

    /**
     * @return array<string, string> network => url
     */
    public function socialLinks(int $userId): array
    {
        $rows = $this->database
            ->query('SELECT network, url FROM user_social_links WHERE user_id = ? ORDER BY network', [$userId])
            ->fetchAll();

        return array_column($rows, 'url', 'network');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function siteRoles(int $userId): array
    {
        return $this->database
            ->query(
                "SELECT r.role_slug, r.role_name, ur.assigned_at, a.handle AS assigned_by_handle
                   FROM user_roles ur
                   JOIN roles r ON r.id = ur.role_id AND r.scope = 'system'
                   LEFT JOIN users a ON a.id = ur.assigned_by
                  WHERE ur.user_id = ?
                  ORDER BY r.level DESC",
                [$userId]
            )
            ->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingEmail(int $userId): ?array
    {
        return $this->row(
            'SELECT new_email, expires_at, expires_at <= UTC_TIMESTAMP() AS is_expired
               FROM pending_email_changes WHERE user_id = ?',
            [$userId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingErasure(int $userId): ?array
    {
        return $this->row('SELECT * FROM pending_erasures WHERE user_id = ?', [$userId]);
    }

    /**
     * @return array<string, int>
     */
    private function counts(int $userId): array
    {
        $row = $this->row(
            "SELECT
                (SELECT COUNT(*) FROM posts WHERE author_id = ?) AS posts,
                (SELECT COUNT(*) FROM posts WHERE author_id = ? AND status = 'published') AS published_posts,
                (SELECT COUNT(*) FROM comments WHERE user_id = ?) AS comments,
                (SELECT COUNT(*) FROM comments WHERE user_id = ? AND hidden_at IS NOT NULL) AS hidden_comments,
                (SELECT COUNT(*) FROM comment_reports cr JOIN comments c ON c.id = cr.comment_id WHERE c.user_id = ?) AS reports_on_comments,
                (SELECT COUNT(*) FROM post_reports pr JOIN posts p ON p.id = pr.post_id WHERE p.author_id = ?) AS reports_on_posts,
                (SELECT COUNT(*) FROM comment_reports WHERE user_id = ?) + (SELECT COUNT(*) FROM post_reports WHERE user_id = ?) AS reports_filed",
            array_fill(0, 8, $userId)
        ) ?? [];

        return array_map('intval', $row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentPosts(int $userId): array
    {
        return $this->database
            ->query(
                'SELECT p.id, p.title, p.status, p.created_at, p.published_at, b.blog_name
                   FROM posts p
                   LEFT JOIN blogs b ON b.id = p.blog_id
                  WHERE p.author_id = ?
                  ORDER BY p.created_at DESC
                  LIMIT '.self::RECENT_LIMIT,
                [$userId]
            )
            ->fetchAll();
    }

    /**
     * Includes hidden and unapproved comments on purpose: this page exists to
     * investigate, so it has to show what the public no longer can.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentComments(int $userId): array
    {
        return $this->database
            ->query(
                'SELECT c.id, c.content, c.status, c.hidden_at, c.hidden_reason, c.deleted_at,
                        c.reports_count, c.created_at, p.title AS post_title
                   FROM comments c
                   JOIN posts p ON p.id = c.post_id
                  WHERE c.user_id = ?
                  ORDER BY c.created_at DESC
                  LIMIT '.self::RECENT_LIMIT,
                [$userId]
            )
            ->fetchAll();
    }

    /**
     * What the account did, including anything an administrator did while
     * signed in as it. Those rows are filed under the administrator, with
     * acting_as naming this account, so both people's pages show them.
     *
     * The admin_id subquery keeps this on the user_id index: only the rows of
     * administrators who ever impersonated this account are pattern-matched.
     * AuditService appends acting_as last, so the pattern anchors on the "}".
     *
     * @return array<int, array<string, mixed>>
     */
    private function activityBy(int $userId): array
    {
        return $this->activity(
            'a.user_id = ? OR (
                a.user_id IN (SELECT admin_id FROM impersonation_sessions WHERE target_user_id = ?)
                AND a.details LIKE ?
            )',
            [$userId, $userId, '%"acting_as":'.$userId.'}']
        );
    }

    /**
     * @param  string  $where  A fixed condition; values only ever arrive through $params
     * @param  array<int, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    private function activity(string $where, array $params): array
    {
        return $this->database
            ->query(
                "SELECT a.id, a.action, a.resource_type, a.resource_id, a.details, a.ip_address, a.created_at,
                        u.handle AS actor_handle
                   FROM activity_log a
                   LEFT JOIN users u ON u.id = a.user_id
                  WHERE {$where}
                  ORDER BY a.created_at DESC, a.id DESC
                  LIMIT 50",
                $params
            )
            ->fetchAll();
    }

    /**
     * @param  array<int, mixed>  $params
     * @return array<string, mixed>|null
     */
    private function row(string $sql, array $params): ?array
    {
        $row = $this->database->query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }
}
