<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Reads the posts the public is allowed to see: blog pages, post navigation,
 * Discover search, the home showcase, author profiles and the sitemap.
 *
 * Writes and their cache busting stay in PostModel, which forgets the
 * fragments these reads remember.
 */
class PublishedPostModel extends AppModel
{
    protected ?string $table = 'posts';

    /**
     * A post search engines are offered, over the aliases p (posts), b (blogs) and
     * s (blog_settings): published and public, not marked noindex, on a published
     * blog that allows indexing, with no canonical address pointing somewhere else.
     * The sitemap and the SEO checks both read it, so they never disagree.
     */
    public const INDEXABLE_SQL = "p.status = 'published' AND p.visibility = 'public' AND b.status = 'published'
        AND COALESCE(p.meta_noindex, 0) = 0 AND COALESCE(s.indexable, 1) = 1
        AND (p.canonical_url IS NULL OR p.canonical_url = '' OR p.canonical_url LIKE CONCAT('%/blog/', b.blog_slug, '/', p.slug))";

    /** Posts one sitemap lists, newest first. */
    public const SITEMAP_LIMIT = 1000;

    /**
     * Count published posts by author filtered by visibility.
     *
     * Companion to listByAuthorVisibility() so public pages can show a
     * truthful total instead of the denormalized users.posts_count, which
     * includes drafts and private posts.
     *
     * @param  int  $authorId  Author user ID
     * @param  string[]  $visibilities  Array of visibility values to include
     * @return int Number of matching posts
     */
    public function countByAuthorVisibility(int $authorId, array $visibilities): int
    {
        $params = [':author_id' => $authorId];
        $placeholders = [];

        foreach ($visibilities as $index => $visibility) {
            $key = ":visibility_{$index}";
            $placeholders[] = $key;
            $params[$key] = $visibility;
        }

        $inClause = implode(',', $placeholders);

        $sql = "SELECT COUNT(*)
                FROM posts
                WHERE author_id = :author_id
                AND status = 'published'
                AND EXISTS (SELECT 1 FROM blogs pb WHERE pb.id = posts.blog_id AND pb.status = 'published')
                AND visibility IN ($inClause)";

        $stmt = $this->database->query($sql, $params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count approved comments received on an author's public published posts.
     *
     * @param  int  $authorId  Author user ID
     * @return int Number of approved comments on publicly visible posts
     */
    public function countPublicCommentsReceived(int $authorId): int
    {
        $sql = "SELECT COUNT(c.id)
                FROM comments c
                JOIN posts p ON p.id = c.post_id
                WHERE p.author_id = ?
                AND p.status = 'published'
                AND EXISTS (SELECT 1 FROM blogs pb WHERE pb.id = p.blog_id AND pb.status = 'published')
                AND p.visibility = 'public'
                AND c.status = 'approved'
                AND c.hidden_at IS NULL";

        $stmt = $this->database->query($sql, [$authorId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * List published posts by author filtered by visibility.
     *
     * @param  int  $authorId  Author user ID
     * @param  string[]  $visibilities  Array of visibility values to include
     * @param  int  $limit  Maximum number of posts
     * @return array<int, array<string, mixed>> Array of post records
     */
    public function listByAuthorVisibility(int $authorId, array $visibilities, int $limit = 10): array
    {
        // Build IN list with named params (convert array to multiple params)
        $params = [':author_id' => $authorId];
        $placeholders = [];

        foreach ($visibilities as $index => $visibility) {
            $key = ":visibility_{$index}";
            $placeholders[] = $key;
            $params[$key] = $visibility;
        }

        $inClause = implode(',', $placeholders);
        $params[':limit'] = $limit;

        $sql = "SELECT id, blog_id, slug, title, excerpt, LEFT(content, 1000) AS content, featured_image, visibility, published_at
                FROM posts
                WHERE author_id = :author_id
                AND status = 'published'
                AND EXISTS (SELECT 1 FROM blogs pb WHERE pb.id = posts.blog_id AND pb.status = 'published')
                AND visibility IN ($inClause)
                ORDER BY published_at DESC
                LIMIT :limit";

        $stmt = $this->database->query($sql, $params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Find previous published post for a blog before a given UTC timestamp.
     *
     * @param  int  $blogId  Blog ID
     * @param  string  $publishedAtUtc  UTC timestamp
     * @return array<string, mixed>|null Post record with id, slug, title, published_at, or null if not found
     */
    public function findPreviousByBlogIdAndDate(int $blogId, string $publishedAtUtc): ?array
    {
        $cacheKey = 'post-nav:'.$blogId.':prev:'.$publishedAtUtc;

        $loadPrevious = function () use ($blogId, $publishedAtUtc): ?array {
            $sql = "SELECT id, slug, title, published_at
                    FROM {$this->getTable()}
                    WHERE blog_id = :blog_id
                    AND status = 'published' AND visibility = 'public'
                    AND published_at < :ts
                    ORDER BY published_at DESC
                    LIMIT 1";

            return $this->database->query($sql, [
                ':blog_id' => $blogId,
                ':ts' => $publishedAtUtc,
            ])->fetch() ?: null;
        };

        // Blog-scoped neighbour lookup, busted for the whole blog on any post write.
        return fragment()->rememberData($cacheKey, $loadPrevious, 3600, false);
    }

    /**
     * Find next published post for a blog after a given UTC timestamp.
     *
     * @param  int  $blogId  Blog ID
     * @param  string  $publishedAtUtc  UTC timestamp
     * @return array<string, mixed>|null Post record with id, slug, title, published_at, or null if not found
     */
    public function findNextByBlogIdAndDate(int $blogId, string $publishedAtUtc): ?array
    {
        $cacheKey = 'post-nav:'.$blogId.':next:'.$publishedAtUtc;

        $loadNext = function () use ($blogId, $publishedAtUtc): ?array {
            $sql = "SELECT id, slug, title, published_at
                    FROM {$this->getTable()}
                    WHERE blog_id = :blog_id
                    AND status = 'published' AND visibility = 'public'
                    AND published_at > :ts
                    ORDER BY published_at ASC
                    LIMIT 1";

            return $this->database->query($sql, [
                ':blog_id' => $blogId,
                ':ts' => $publishedAtUtc,
            ])->fetch() ?: null;
        };

        // Blog-scoped neighbour lookup, busted for the whole blog on any post write.
        return fragment()->rememberData($cacheKey, $loadNext, 3600, false);
    }

    /**
     * Find recent published posts by blog excluding a specific slug.
     *
     * @param  int  $blogId  Blog ID
     * @param  string  $excludeSlug  Slug to exclude from results
     * @param  int  $limit  Maximum number of posts
     * @return array<int, array<string, mixed>> Array of post records
     */
    public function findRecentByBlogIdExcludingSlug(int $blogId, string $excludeSlug, int $limit = 4): array
    {
        $cacheKey = 'post-related:'.$blogId.':'.$excludeSlug.':'.$limit;

        $loadRelated = function () use ($blogId, $excludeSlug, $limit): array {
            $sql = "SELECT id, slug, title, excerpt, LEFT(content, 1000) AS content, featured_image AS cover_url, published_at
                    FROM {$this->getTable()}
                    WHERE blog_id = :blog_id
                    AND status = 'published' AND visibility = 'public'
                    AND slug <> :slug
                    ORDER BY published_at DESC
                    LIMIT :limit";

            return $this->database->query($sql, [
                ':blog_id' => $blogId,
                ':slug' => $excludeSlug,
                ':limit' => $limit,
            ])->fetchAll() ?: [];
        };

        // "Related" list for the post page, busted for the whole blog on any post write.
        return fragment()->rememberData($cacheKey, $loadRelated, 3600, false);
    }

    /**
     * Get published posts by blog ID with pagination.
     *
     * @param  int  $blogId  Blog ID to filter by
     * @param  int  $page  Page number (starting from 1)
     * @param  int  $perPage  Number of posts per page
     * @return array{data: array<int, array<string, mixed>>, totalPages: int, currentPage: int, perPage: int, totalPosts: int}
     */
    public function findPublishedByBlogIdWithPagination(int $blogId, int $page = 1, int $perPage = 5): array
    {
        $cacheKey = 'blog-posts:'.$blogId.':'.$page.':'.$perPage;

        $loadPage = function () use ($blogId, $page, $perPage): array {
            $offset = ($page - 1) * $perPage;

            // Get total count of published posts for this blog
            $countSql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE blog_id = :blog_id AND status = 'published' AND visibility = 'public'";
            $totalPosts = (int) $this->database->query($countSql, [':blog_id' => $blogId])->fetchColumn();

            $totalPages = (int) ceil($totalPosts / $perPage);

            // Get paginated results
            $sql = "SELECT * FROM {$this->getTable()}
                    WHERE blog_id = :blog_id AND status = 'published' AND visibility = 'public'
                    ORDER BY published_at DESC
                    LIMIT :limit OFFSET :offset";

            $posts = $this->database->query($sql, [
                ':blog_id' => $blogId,
                ':limit' => $perPage,
                ':offset' => $offset,
            ])->fetchAll();

            return [
                'data' => $posts,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'perPage' => $perPage,
                'totalPosts' => $totalPosts,
            ];
        };

        // Blog-scoped published listing; busted on any post write for the blog.
        return fragment()->rememberData($cacheKey, $loadPage, 3600, false);
    }

    /**
     * Search published posts by title and body with pagination and optional category filter.
     *
     * Every word must match as a word prefix ("merc" finds "Mercury"). Backed by the
     * ft_title_content FULLTEXT index, so it is word-based, not substring-based.
     *
     * @param  string  $query  Search term
     * @param  int  $page  Page number
     * @param  int  $perPage  Posts per page
     * @param  string|null  $categoryName  Optional category name filter
     * @return array{data: array<int, array<string, mixed>>, totalPages: int, currentPage: int, perPage: int, totalPosts: int}
     */
    public function searchPublishedPosts(string $query, int $page = 1, int $perPage = 8, ?string $categoryName = null): array
    {
        $offset = ($page - 1) * $perPage;
        $booleanQuery = $this->toBooleanSearch($query);

        if ($booleanQuery === '') {
            return ['data' => [], 'totalPages' => 0, 'currentPage' => $page, 'perPage' => $perPage, 'totalPosts' => 0];
        }

        // Build filtering clause
        $categoryClause = '';
        $params = [':search' => $booleanQuery];

        if ($categoryName !== null && $categoryName !== '') {
            // Topic filter: match by category name across all blogs.
            $categoryClause = ' AND p.category_id IN (SELECT id FROM categories WHERE name = :categoryName) ';
            $params[':categoryName'] = $categoryName;
        }

        // Count query
        $countSql = "
            SELECT COUNT(*)
            FROM posts p
            JOIN blogs b ON p.blog_id = b.id AND b.status = 'published'
            WHERE p.status = 'published' AND p.visibility = 'public'
            AND MATCH(p.title, p.content) AGAINST (:search IN BOOLEAN MODE)
            {$categoryClause}
        ";

        $countStmt = $this->database->query($countSql, $params);
        $totalPosts = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($totalPosts / $perPage);

        // Data query
        $sql = "
            SELECT p.*, b.blog_name, b.blog_slug, c.name as category_name, c.slug as category_slug
            FROM posts p
            JOIN blogs b ON p.blog_id = b.id AND b.status = 'published'
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'published' AND p.visibility = 'public'
            AND MATCH(p.title, p.content) AGAINST (:search IN BOOLEAN MODE)
            {$categoryClause}
            ORDER BY p.published_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->database->query($sql, $params);
        $posts = $stmt->fetchAll();

        return [
            'data' => $posts,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPosts' => $totalPosts,
        ];
    }

    /**
     * Search one blog's published, public posts by title and body, newest first.
     *
     * @param  int  $blogId  Blog to search
     * @param  string  $query  Raw search text
     * @param  int  $limit  Most posts to return
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function searchInBlog(int $blogId, string $query, int $limit = 30): array
    {
        $booleanQuery = $this->toBooleanSearch($query);

        if ($booleanQuery === '') {
            return ['data' => [], 'total' => 0];
        }

        $where = "blog_id = :blog_id AND status = 'published' AND visibility = 'public'
            AND MATCH(title, content) AGAINST (:search IN BOOLEAN MODE)";
        $params = [':blog_id' => $blogId, ':search' => $booleanQuery];

        $total = (int) $this->database->query("SELECT COUNT(*) FROM {$this->getTable()} WHERE {$where}", $params)->fetchColumn();

        $posts = $this->database->query(
            "SELECT * FROM {$this->getTable()} WHERE {$where} ORDER BY published_at DESC LIMIT :limit",
            $params + [':limit' => $limit]
        )->fetchAll();

        return ['data' => $posts, 'total' => $total];
    }

    /**
     * Turn free text into a boolean-mode FULLTEXT expression of required word prefixes.
     *
     * Only letters and digits survive, so the operators (+ - " * ( ) @ < > ~) a visitor
     * types cannot change the query's meaning or raise a syntax error. Terms are capped
     * so one request cannot build an arbitrarily wide search.
     *
     * @param  string  $query  Raw search text
     * @return string Expression such as "+merc* +ris*", or '' when no usable word remains
     */
    private function toBooleanSearch(string $query): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];
        foreach (array_slice($words, 0, 8) as $word) {
            $terms[] = '+'.mb_substr($word, 0, 50).'*';
        }

        return implode(' ', $terms);
    }

    /**
     * Get recent published posts with pagination and optional category filter.
     *
     * @param  int  $page  Page number
     * @param  int  $perPage  Posts per page
     * @param  string|null  $categoryName  Optional category name filter
     * @return array{data: array<int, array<string, mixed>>, totalPages: int, currentPage: int, perPage: int, totalPosts: int}
     */
    public function getRecentPublishedWithPagination(int $page = 1, int $perPage = 8, ?string $categoryName = null): array
    {
        $offset = ($page - 1) * $perPage;

        $categoryClause = '';
        $params = [];

        if ($categoryName !== null && $categoryName !== '') {
            // Topic filter: match by category name across all blogs.
            $categoryClause = ' AND p.category_id IN (SELECT id FROM categories WHERE name = :categoryName) ';
            $params[':categoryName'] = $categoryName;
        }

        // Count total published posts with optional category filter
        $countSql = "SELECT COUNT(*) FROM posts p WHERE p.status = 'published' AND p.visibility = 'public' AND EXISTS (SELECT 1 FROM blogs pb WHERE pb.id = p.blog_id AND pb.status = 'published') {$categoryClause}";
        $countStmt = $this->database->query($countSql, $params);
        $totalPosts = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($totalPosts / $perPage);

        // Fetch posts query
        $sql = "
            SELECT p.*, b.blog_name, b.blog_slug, c.name AS category_name, c.slug AS category_slug
            FROM posts p
            JOIN blogs b ON p.blog_id = b.id AND b.status = 'published'
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'published' AND p.visibility = 'public'
        ";

        $sql .= $categoryClause;
        $sql .= ' ORDER BY p.published_at DESC LIMIT :limit OFFSET :offset';

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->database->query($sql, $params);
        $posts = $stmt->fetchAll();

        return [
            'data' => $posts,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPosts' => $totalPosts,
        ];
    }

    /**
     * The blog's featured headline: a featured, published post.
     *
     * Newest one takes priority if multiple entries are flagged,
     * acting as a safety net on top of the one‑per‑blog rule enforced by setFeatured().
     *
     * @return array<string, mixed>|null Featured post record, or null if none
     */
    public function findFeaturedByBlogId(int $blogId): ?array
    {
        $cacheKey = 'blog-featured:'.$blogId;

        $loadFeatured = function () use ($blogId): ?array {
            $sql = "SELECT * FROM posts
                    WHERE blog_id = ? AND is_featured = 1 AND status = 'published' AND visibility = 'public'
                    ORDER BY published_at DESC, id DESC
                    LIMIT 1";

            return $this->database->query($sql, [$blogId])->fetch(\PDO::FETCH_ASSOC) ?: null;
        };

        // Blog-scoped landing data; busted on any post write or a featured toggle.
        return fragment()->rememberData($cacheKey, $loadFeatured, 3600, false);
    }

    /**
     * Platform-wide count of published posts, for the front page stats strip.
     */
    public function countPublished(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE status = 'published' AND visibility = 'public'";

        return (int) $this->database->query($sql)->fetchColumn();
    }

    /**
     * Post URLs for the sitemap: the posts search engines are offered, newest first.
     *
     * @param  int  $limit  Safety cap on sitemap size
     * @return array<int, array<string, mixed>> Rows with slug, blog_slug, updated_at
     */
    public function findPublicForSitemap(int $limit = self::SITEMAP_LIMIT): array
    {
        // The locale columns come along so the sitemap can list each post at the
        // URLs it actually resolves at, rather than assuming English. Translated
        // locales arrive concatenated to keep this a single query.
        $sql = "SELECT p.slug, p.updated_at, b.blog_slug,
                       COALESCE(s.default_locale, 'en') AS default_locale,
                       COALESCE(s.translations_enabled, 0) AS translations_enabled,
                       GROUP_CONCAT(DISTINCT t.locale) AS translated_locales
                FROM posts p
                JOIN blogs b ON p.blog_id = b.id
                LEFT JOIN blog_settings s ON s.blog_id = b.id
                LEFT JOIN post_translations t ON t.post_id = p.id
                WHERE ".self::INDEXABLE_SQL.'
                GROUP BY p.id, p.slug, p.updated_at, p.published_at, b.blog_slug,
                         s.default_locale, s.translations_enabled
                ORDER BY p.published_at DESC
                LIMIT :limit';

        return $this->database->query($sql, [':limit' => $limit])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Admin picks for the site front page showcase, newest first.
     *
     * The front page is a platform owned surface, so only posts an
     * administrator explicitly flagged appear here. Returns an empty array
     * when nothing is picked; the view falls back to its own static cards.
     *
     * @param  int  $limit  Maximum number of showcase posts
     * @return array<int, array<string, mixed>> Post rows with blog name and slug
     */
    public function findHomeShowcase(int $limit = 6): array
    {
        $sql = "SELECT p.*, b.blog_name, b.blog_slug
                FROM posts p
                JOIN blogs b ON p.blog_id = b.id AND b.status = 'published'
                WHERE p.featured_on_home = 1
                  AND p.status = 'published'
                  AND p.visibility = 'public'
                ORDER BY p.published_at DESC, p.id DESC
                LIMIT :limit";

        return $this->database->query($sql, [':limit' => $limit])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Published posts for a blog, optionally within one category, newest first.
     *
     * Powers the folio landing's "index" grid and its AJAX category swap. Pass
     * $excludeId to drop the headline post so it never shows up twice.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findPublishedByBlogAndCategory(int $blogId, ?int $categoryId, int $limit = 6, ?int $excludeId = null): array
    {
        // null id/exclude become 'all'/'none' so the key stays readable and distinct.
        $cacheKey = 'blog-catposts:'.$blogId.':'.($categoryId ?? 'all').':'.$limit.':'.($excludeId ?? 'none');

        $loadCategoryPosts = function () use ($blogId, $categoryId, $limit, $excludeId): array {
            $sql = "SELECT * FROM posts WHERE blog_id = :blog_id AND status = 'published' AND visibility = 'public'";
            $params = [':blog_id' => $blogId];

            if ($categoryId !== null) {
                $sql .= ' AND category_id = :category_id';
                $params[':category_id'] = $categoryId;
            }

            if ($excludeId !== null) {
                $sql .= ' AND id <> :exclude_id';
                $params[':exclude_id'] = $excludeId;
            }

            $sql .= ' ORDER BY published_at DESC, id DESC LIMIT :limit';
            $params[':limit'] = $limit;

            return $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        };

        // Blog-scoped category grid; busted on any post write for the blog.
        return fragment()->rememberData($cacheKey, $loadCategoryPosts, 3600, false);
    }
}
