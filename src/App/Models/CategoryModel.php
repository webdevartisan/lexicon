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
     * Update a category and invalidate related cache.
     *
     * Invalidates blog listings since categories appear in sidebars,
     * filters, and navigation menus.
     *
     * @param  int|string  $id  Category identifier
     * @param  array  $data  Fields to update
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
     * @return array|null Category data or null if not found
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE slug = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$slug]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

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
     * @return array List of published posts, newest first
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
     * @return array List of all categories
     */
    public function getCategories(): array
    {
        $sql = 'SELECT * FROM categories ORDER BY name ASC';
        $stmt = $this->database->query($sql);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a new category.
     *
     * @param  array  $data  Category data (name, slug, description, etc.)
     * @return bool|int Inserted category ID on success, false on failure
     */
    public function create(array $data): bool|int
    {
        return parent::insert($data);
    }

    /**
     * Categories belonging to a blog, with how many posts use each.
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
     * Look up a category by slug within a blog.
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
