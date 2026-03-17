<?php

declare(strict_types=1);

namespace App\Models;

/**
 * ReservedSlugModel
 *
 * Manages reserved usernames, slugs, and keywords that cannot be used
 * for user registration or content creation. Used for spam prevention
 * and system namespace protection.
 */
class ReservedSlugModel extends AppModel
{
    protected ?string $table = 'reserved_slugs';

    /**
     * Check if a normalized slug exists in reserved list.
     *
     * @param string $slug Normalized slug to check
     * @return bool True if reserved
     */
    public function isReserved(string $slug): bool
    {
        $sql = "SELECT 1 FROM {$this->getTable()} WHERE slug = :slug LIMIT 1";
        $stmt = $this->database->query($sql, [':slug' => $slug]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Get all reserved slugs.
     *
     * Returns normalized slugs for fuzzy matching validation.
     * Cached by service layer to avoid repeated queries.
     *
     * @return string[] List of reserved slugs
     */
    public function getAll(): array
    {
        $sql = "SELECT slug FROM {$this->getTable()}";
        $stmt = $this->database->query($sql);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Add a new reserved slug.
     *
     * Slug should already be normalized before insertion.
     *
     * @param string $slug Normalized slug to reserve
     * @return bool True if inserted, false if already exists
     */
    public function add(string $slug): bool
    {
        $sql = "INSERT IGNORE INTO {$this->getTable()} (slug) VALUES (:slug)";
        $affected = $this->database->execute($sql, [':slug' => $slug]);

        return $affected > 0;
    }

    /**
     * Remove a reserved slug.
     *
     * @param string $slug Slug to unreserve
     * @return bool True if removed, false if didn't exist
     */
    public function remove(string $slug): bool
    {
        $sql = "DELETE FROM {$this->getTable()} WHERE slug = :slug";
        $affected = $this->database->execute($sql, [':slug' => $slug]);

        return $affected > 0;
    }
}
