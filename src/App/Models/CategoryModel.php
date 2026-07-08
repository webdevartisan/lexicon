<?php

declare(strict_types=1);

namespace App\Models;

/**
 * CategoryModel
 *
 * Manages blog post categories. Categories organize content and appear
 * in navigation menus, sidebars, and post listings. Cache invalidation
 * ensures category changes immediately reflect across the application.
 */
class CategoryModel extends AppModel
{
    protected ?string $table = 'categories';

    /**
     * All categories with their blog name, for the admin listing.
     *
     * @return array<int, array<string, mixed>> Rows with blog_name joined in
     */
    public function allWithBlog(): array
    {
        $sql = "SELECT c.*, b.blog_name
                FROM {$this->getTable()} c
                LEFT JOIN blogs b ON b.id = c.blog_id
                ORDER BY b.blog_name, c.name";

        return $this->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Paginated admin listing with optional name/slug/blog search.
     *
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int|bool>} Same shape as UserModel::findAllForAdmin()
     */
    public function findAllForAdmin(int $page = 1, int $perPage = 20, string $q = ''): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $where = '';
        $params = [];

        if ($q !== '') {
            $where = 'WHERE (c.name LIKE :q_name OR c.slug LIKE :q_slug OR b.blog_name LIKE :q_blog)';
            $term = '%'.$q.'%';
            $params[':q_name'] = $term;
            $params[':q_slug'] = $term;
            $params[':q_blog'] = $term;
        }

        $total = (int) $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()} c LEFT JOIN blogs b ON b.id = c.blog_id {$where}",
            $params
        )->fetchColumn();

        $sql = "SELECT c.*, b.blog_name
                FROM {$this->getTable()} c
                LEFT JOIN blogs b ON b.id = c.blog_id
                {$where}
                ORDER BY b.blog_name, c.name
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = ($page - 1) * $perPage;

        $rows = $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $totalPages = (int) ceil($total / $perPage);

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $total,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    /**
     * Update a category and invalidate related cache.
     *
     * Invalidates blog listings since categories appear in sidebars,
     * filters, and navigation menus.
     *
     * @param  int|string  $id  Category identifier
     * @param  array<string, mixed>  $data  Fields to update
     * @return bool True on success
     */
    public function update(int|string $id, array $data): bool
    {
        $result = parent::update($id, $data);

        if ($result) {
            // Invalidate all blog listings (categories shown in sidebars, filters, etc.)
            cache()->deletePattern('*:GET:/blogs*');
        }

        return $result;
    }

    /**
     * Delete a category and invalidate related cache.
     *
     * Invalidates all blog cache since posts in this category need updates.
     *
     * @param  int|string  $id  Category identifier
     * @return bool True on success
     */
    public function delete(int|string $id): bool
    {
        $result = parent::delete($id);

        if ($result) {
            // Invalidate all blog cache (posts in this category need to update)
            cache()->deletePattern('*:GET:/blog*');
        }

        return $result;
    }

    /**
     * Find a category by slug.
     *
     * @param  string  $slug  Category URL slug
     * @return array<string, mixed>|null Category data or null if not found
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE slug = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$slug]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * @return array<string, mixed>|null Category data or null if not found
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE id = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$id]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Get all published posts in this category.
     *
     * Cross-table query is pragmatic here since categories and posts
     * are tightly coupled in the domain model.
     *
     * @param  int  $categoryId  Category identifier
     * @return array<int, array<string, mixed>> List of published posts, newest first
     */
    public function posts(int $categoryId): array
    {
        $sql = "SELECT * FROM posts 
                WHERE category_id = ? 
                AND status = 'published'
                ORDER BY created_at DESC";

        $stmt = $this->database->query($sql, [$categoryId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all categories ordered alphabetically.
     *
     * @return array<int, array<string, mixed>> List of all categories
     */
    public function getCategories(): array
    {
        $sql = 'SELECT * FROM categories ORDER BY name ASC';
        $stmt = $this->database->query($sql);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Distinct category names that have published posts, with a combined count.
     *
     * Categories are per-blog, so the same name can exist in several blogs. On
     * the cross-blog discovery page we treat a category as a topic: one entry
     * per name, spanning every blog that uses it.
     *
     * @return array<int, array<string, mixed>> Rows with name and post_count
     */
    public function getPublishedTopics(): array
    {
        $sql = "SELECT c.name, COUNT(p.id) AS post_count
                FROM categories c
                JOIN posts p ON p.category_id = c.id AND p.status = 'published'
                GROUP BY c.name
                ORDER BY c.name ASC";

        return $this->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a new category.
     *
     * @param  array<string, mixed>  $data  Category data (name, slug, description, etc.)
     * @return bool|int Inserted category ID on success, false on failure
     */
    public function create(array $data): bool|int
    {
        return parent::insert($data);
    }

    /**
     * Categories belonging to a blog, with how many posts use each.
     *
     * @return array<int, array<string, mixed>> Category rows with post_count
     */
    public function getByBlogId(int $blogId): array
    {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM posts WHERE category_id = c.id) AS post_count
                FROM categories c
                WHERE c.blog_id = ?
                ORDER BY c.name ASC';
        $stmt = $this->database->query($sql, [$blogId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Categories in a blog that have at least one published post, with that count.
     *
     * Used for the public pill filters, where a category with no published posts
     * would just be a dead end.
     *
     * @return array<int, array<string, mixed>> Category rows with post_count
     */
    public function getPublishedByBlogId(int $blogId): array
    {
        $sql = "SELECT c.*, COUNT(p.id) AS post_count
                FROM categories c
                JOIN posts p ON p.category_id = c.id AND p.status = 'published'
                WHERE c.blog_id = ?
                GROUP BY c.id
                ORDER BY c.name ASC";
        $stmt = $this->database->query($sql, [$blogId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Look up a category by slug within a blog.
     *
     * @return array<string, mixed>|null Category data or null if not found
     */
    public function findBySlugInBlog(int $blogId, string $slug): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE blog_id = ? AND slug = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$blogId, $slug]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Look up a category by id, but only if it belongs to the given blog.
     *
     * Used to authorize edits/assignment so a category from another blog
     * can't be touched.
     *
     * @return array<string, mixed>|null Category data or null if not found
     */
    public function findForBlog(int $id, int $blogId): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE id = ? AND blog_id = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$id, $blogId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find a category by name in a blog, creating it if it doesn't exist.
     *
     * Returns the category id, or null if the name is blank.
     */
    public function findOrCreateForBlog(int $blogId, string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $slug = slugify($name) ?: 'category';

        $existing = $this->findBySlugInBlog($blogId, $slug);
        if ($existing) {
            return (int) $existing['id'];
        }

        $this->create(['blog_id' => $blogId, 'name' => $name, 'slug' => $slug]);

        return $this->getInsertID();
    }

    /**
     * Rename a category (and re-slug it) within its blog.
     */
    public function renameForBlog(int $id, int $blogId, string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $slug = slugify($name) ?: 'category';

        // Don't collide with another category in the same blog.
        $clash = $this->findBySlugInBlog($blogId, $slug);
        if ($clash && (int) $clash['id'] !== $id) {
            return false;
        }

        return $this->update($id, ['name' => $name, 'slug' => $slug]);
    }
}
