<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Database;
use RuntimeException;

/**
 * Gathers everything the site holds about one person, for them to download.
 *
 * Secrets are left out: the password hash and the tokens behind unsubscribe and
 * confirmation links.
 */
class PersonalDataExportService
{
    public function __construct(private Database $database) {}

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException If the account does not exist
     */
    public function export(int $userId): array
    {
        $account = $this->row(
            'SELECT handle, email, first_name, last_name, display_name_cached AS display_name,
                    is_active, age_confirmed_at, created_at, updated_at, last_login
             FROM users WHERE id = ?',
            [$userId]
        );

        if ($account === null) {
            throw new RuntimeException("Account {$userId} does not exist.");
        }

        return [
            'exported_at' => gmdate('c'),
            'account' => $account,
            'profile' => $this->row(
                'SELECT bio, occupation, location, avatar_url, avatar_source_url, is_public, created_at, updated_at
                 FROM user_profiles WHERE user_id = ?',
                [$userId]
            ),
            'social_links' => $this->rows('SELECT network, url, created_at FROM user_social_links WHERE user_id = ?', [$userId]),
            'preferences' => $this->row(
                'SELECT default_blog_id, default_post_visibility, display_name_preference, locale, timezone,
                        notify_comment_replies, notify_comments_authored, notify_comments_blog,
                        notify_comments_moderation, notify_invites, notify_post_status,
                        notify_review_requests, notify_role_changes, updated_at
                 FROM user_preferences WHERE user_id = ?',
                [$userId]
            ),
            'site_roles' => $this->rows(
                'SELECT r.role_name AS role, ur.assigned_at
                 FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = ?',
                [$userId]
            ),
            'pending_email_change' => $this->row(
                'SELECT new_email, created_at, expires_at FROM pending_email_changes WHERE user_id = ?',
                [$userId]
            ),
            'blogs_owned' => $this->rows(
                'SELECT id, blog_name, blog_slug, description, status, created_at FROM blogs WHERE owner_id = ?',
                [$userId]
            ),
            'blog_memberships' => $this->rows(
                'SELECT b.blog_name, bu.role, bu.is_active, bu.assigned_at, bu.revoked_at
                 FROM blog_users bu JOIN blogs b ON b.id = bu.blog_id
                 WHERE bu.user_id = ?',
                [$userId]
            ),
            'posts' => $this->rows(
                'SELECT p.id, b.blog_name, p.title, p.slug, p.status, p.excerpt, p.content,
                        p.featured_image, p.created_at, p.updated_at, p.published_at
                 FROM posts p JOIN blogs b ON b.id = p.blog_id
                 WHERE p.author_id = ?
                 ORDER BY p.created_at',
                [$userId]
            ),
            'comments' => $this->rows(
                'SELECT c.id, p.title AS post_title, c.content, c.status, c.created_at, c.updated_at
                 FROM comments c JOIN posts p ON p.id = c.post_id
                 WHERE c.user_id = ?
                 ORDER BY c.created_at',
                [$userId]
            ),
            'post_votes' => $this->rows(
                'SELECT p.title AS post_title, v.value, v.created_at
                 FROM post_votes v JOIN posts p ON p.id = v.post_id
                 WHERE v.user_id = ?',
                [$userId]
            ),
            'comment_votes' => $this->rows(
                'SELECT v.comment_id, v.value, v.created_at FROM comment_votes v WHERE v.user_id = ?',
                [$userId]
            ),
            'bookmarks' => $this->rows(
                'SELECT p.title AS post_title, bm.created_at
                 FROM post_bookmarks bm JOIN posts p ON p.id = bm.post_id
                 WHERE bm.user_id = ?',
                [$userId]
            ),
            'blog_subscriptions' => $this->rows(
                'SELECT b.blog_name, s.email, s.created_at
                 FROM blog_subscribers s JOIN blogs b ON b.id = s.blog_id
                 WHERE s.user_id = ? OR s.email = ?',
                [$userId, $account['email']]
            ),
            'reports_filed' => $this->rows(
                'SELECT subject_type, subject_id, category, details, outcome, created_at
                 FROM content_reports WHERE reporter_id = ? ORDER BY created_at',
                [$userId]
            ),
            'notifications' => $this->rows(
                'SELECT type, data, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at',
                [$userId]
            ),
            'activity_log' => $this->rows(
                'SELECT action, resource_type, resource_id, details, ip_address, created_at
                 FROM activity_log WHERE user_id = ? ORDER BY created_at',
                [$userId]
            ),
        ];
    }

    /**
     * @param  list<mixed>  $params
     * @return array<string, mixed>|null
     */
    private function row(string $sql, array $params): ?array
    {
        $row = $this->database->query($sql, $params)->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param  list<mixed>  $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params): array
    {
        return $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
