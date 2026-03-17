<?php

declare(strict_types=1);

namespace App\Models;

/**
 * BlogSettingsModel
 *
 * Manages blog-specific configuration including theme, locale, timezone,
 * SEO settings, and visual assets. Each blog has one settings record
 * with sensible defaults applied on creation.
 */
final class BlogSettingsModel extends AppModel
{
    protected ?string $table = 'blog_settings';

    /**
     * Find settings for a specific blog.
     *
     * @param int $blogId Blog identifier
     * @return array|null Blog settings or null if not found
     */
    public function findByBlogId(int $blogId): ?array
    {
        $sql = 'SELECT blog_id, theme, default_locale, timezone, 
                        meta_title, meta_description, indexable, 
                        banner_path, logo_path, favicon_path,
                        comments_enabled 
                FROM blog_settings WHERE blog_id = ? LIMIT 1';
        
        $stmt = $this->database->query($sql, [$blogId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Create default settings for a new blog.
     *
     * Applies sensible defaults for theme, locale, timezone, and features.
     * Comments are enabled by default unless explicitly disabled.
     *
     * @param int $blogId Blog identifier
     * @param array $data Optional overrides for default settings
     * @return bool True on success
     */
    public function createDefaultForBlog(int $blogId, array $data): bool
    {
        $sql = 'INSERT INTO blog_settings
                  (blog_id, theme, default_locale, timezone, meta_title, 
                  meta_description, indexable, banner_path, logo_path, 
                  favicon_path, comments_enabled)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        // Default: comments enabled unless explicitly disabled
        $commentsEnabled = array_key_exists('comments_enabled', $data)
            ? (int) (bool) $data['comments_enabled']
            : 1;

        $params = [
            $blogId,
            $data['theme'] ?? 'default',
            $data['default_locale'] ?? 'en',
            $data['timezone'] ?? 'UTC',
            $data['meta_title'] ?? '',
            $data['meta_description'] ?? '',
            !empty($data['indexable']) ? 1 : 0,
            $data['banner_path'] ?? null,
            $data['logo_path'] ?? null,
            $data['favicon_path'] ?? null,
            $commentsEnabled,
        ];

        $rowCount = $this->database->execute($sql, $params);
        return $rowCount > 0;
    }

    /**
     * Update blog settings with validation.
     *
     * Only allows whitelisted columns to prevent mass-assignment vulnerabilities.
     * Validates and normalizes boolean fields (indexable, comments_enabled).
     *
     * @param int $blogId Blog identifier
     * @param array $data Settings to update
     * @return bool True on success
     */
    public function updateForBlog(int $blogId, array $data): bool
    {
        // Whitelist columns to prevent mass-assignment vulnerabilities
        $allowed = [
            'theme',
            'default_locale',
            'timezone',
            'meta_title',
            'meta_description',
            'indexable',
            'banner_path',
            'logo_path',
            'favicon_path',
            'comments_enabled',
        ];

        $set = [];
        $params = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $set[] = "$col = ?";

                if ($col === 'indexable' || $col === 'comments_enabled') {
                    $params[] = (int) (bool) $data[$col];
                } else {
                    $params[] = $data[$col];
                }
            }
        }

        if (empty($set)) {
            return true; // Nothing to update
        }

        // append blog_id as last parameter for WHERE clause
        $params[] = $blogId;

        $sql = 'UPDATE blog_settings SET '.implode(', ', $set).' WHERE blog_id = ?';
        
        $rowCount = $this->database->execute($sql, $params);
        return $rowCount > 0;
    }

    /**
     * Find theme for a specific blog by username and blog slug.
     *
     * Used for route: /{username}/b/{blog_slug}
     * Joins users and blogs tables to resolve theme from URL path.
     *
     * @param string $username User's username
     * @param string $blogSlug Blog URL slug
     * @return string|null Theme name or null if not found
     */
    public function findThemeByUsernameAndBlogSlug(string $username, string $blogSlug): ?string
    {
        $sql = '
          SELECT bs.theme
          FROM blogs b
          JOIN users u ON u.id = b.owner_id
          LEFT JOIN blog_settings bs ON bs.blog_id = b.id
          WHERE u.username = ? AND b.blog_slug = ?
          LIMIT 1
        ';
        
        $stmt = $this->database->query($sql, [$username, $blogSlug]);
        $theme = $stmt->fetchColumn();

        return $theme !== false ? (string) $theme : null;
    }

    /**
     * Find theme for user's primary blog by username.
     *
     * Used for route: /{username}
     * Prioritizes blog marked as primary, falls back to oldest blog.
     *
     * @param string $username User's username
     * @return string|null Theme name or null if not found
     */
    public function findPrimaryThemeByUsername(string $username): ?string
    {
        $sql = '
          SELECT bs.theme
          FROM blogs b
          JOIN users u ON u.id = b.owner_id
          LEFT JOIN blog_settings bs ON bs.blog_id = b.id
          WHERE u.username = ?
          ORDER BY (bs.is_primary = 1) DESC, b.created_at ASC
          LIMIT 1
        ';
        
        $stmt = $this->database->query($sql, [$username]);
        $theme = $stmt->fetchColumn();

        return $theme !== false ? (string) $theme : null;
    }

    /**
     * Find theme by blog slug only.
     *
     * Utility method for direct blog slug lookups without username context.
     *
     * @param string $blogSlug Blog URL slug
     * @return string|null Theme name or null if not found
     */
    public function findThemeByBlogSlug(string $blogSlug): ?string
    {
        $sql = '
          SELECT bs.theme
          FROM blogs b
          LEFT JOIN blog_settings bs ON bs.blog_id = b.id
          WHERE b.blog_slug = ?
          LIMIT 1
        ';
        
        $stmt = $this->database->query($sql, [$blogSlug]);
        $theme = $stmt->fetchColumn();

        return $theme !== false ? (string) $theme : null;
    }

    /**
     * Delete settings for a blog.
     *
     * Called during blog deletion to clean up associated configuration.
     *
     * @param int $blogId Blog identifier
     * @return bool True on success
     */
    public function deleteByBlogId(int $blogId): bool
    {
        $sql = 'DELETE FROM blog_settings WHERE blog_id = ? LIMIT 1';
        
        $rowCount = $this->database->execute($sql, [$blogId]);
        return $rowCount > 0;
    }
}
