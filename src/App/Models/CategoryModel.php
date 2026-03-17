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
}
