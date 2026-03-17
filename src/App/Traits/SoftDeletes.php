<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * SoftDeletes Trait
 *
 * Provides soft delete functionality with idempotent behavior.
 * Models using this trait can soft delete records without permanently
 * removing them, allowing for potential restoration.
 */
trait SoftDeletes
{
    /**
     * Soft delete a record by ID.
     *
     * Implements idempotent behavior: deleting an already-deleted record
     * returns true since the desired state (deleted) is achieved. This prevents
     * failures in retry scenarios, batch operations, and duplicate requests.
     *
     * @param  int  $id  Record ID to soft delete
     * @return bool True if deleted (now or already was), false if record doesn't exist
     *
     * @throws \InvalidArgumentException If ID is invalid
     */
    public function softDelete(int $id): bool
    {
        // Validate input to prevent invalid queries
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID must be a positive integer');
        }

        // Check current state to implement idempotency without extra UPDATEs
        $checkSql = 'SELECT id, deleted_at FROM '.$this->getTable().' WHERE id = ?';
        $stmt = $this->database->query($checkSql, [$id]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            return false;
        }

        if ($record['deleted_at'] !== null) {
            // Already deleted - return true to maintain idempotent behavior
            return true;
        }

        // Perform soft delete on active record only
        $sql = 'UPDATE '.$this->getTable().' 
                SET deleted_at = NOW() 
                WHERE id = ? AND deleted_at IS NULL';

        $rowCount = $this->database->execute($sql, [$id]);

        return $rowCount > 0;
    }

    /**
     * Restore a soft-deleted record by ID.
     *
     * @param  int  $id  Record ID to restore
     * @return bool True if restored, false if record doesn't exist or not deleted
     *
     * @throws \InvalidArgumentException If ID is invalid
     */
    public function restore(int $id): bool
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID must be a positive integer');
        }

        $sql = 'UPDATE '.$this->getTable().' 
                SET deleted_at = NULL 
                WHERE id = ? AND deleted_at IS NOT NULL';

        $rowCount = $this->database->execute($sql, [$id]);

        return $rowCount > 0;
    }

    /**
     * Check if a record is soft deleted.
     *
     * @param  int  $id  Record ID to check
     * @return bool True if soft deleted, false otherwise
     */
    public function isTrashed(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $sql = 'SELECT deleted_at FROM '.$this->getTable().' WHERE id = ?';
        $stmt = $this->database->query($sql, [$id]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $record && $record['deleted_at'] !== null;
    }

    /**
     * Fetch all soft-deleted records.
     *
     * @return array<int, array<string, mixed>> Array of soft-deleted records
     */
    public function onlyTrashed(): array
    {
        $sql = 'SELECT * FROM '.$this->getTable().' WHERE deleted_at IS NOT NULL';
        $stmt = $this->database->query($sql);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all records including soft-deleted ones.
     *
     * @return array<int, array<string, mixed>> All records
     */
    public function withTrashed(): array
    {
        $sql = 'SELECT * FROM '.$this->getTable();
        $stmt = $this->database->query($sql);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single record by ID including soft-deleted ones.
     *
     * Useful for checking if a record exists regardless of deletion status.
     *
     * @param  int  $id  Record ID to find
     * @return array|null Record data or null if not found
     */
    public function findWithTrashed(int $id): ?array
    {
        $sql = 'SELECT * FROM '.$this->getTable().' WHERE id = ?';
        $stmt = $this->database->query($sql, [$id]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Force delete a record permanently.
     *
     * Bypasses soft delete and removes the record from database entirely.
     * Use with caution - this operation cannot be undone and may break
     * referential integrity if foreign key constraints are not enforced.
     *
     * @param  int  $id  Record ID to permanently delete
     * @return bool True if deleted, false otherwise
     *
     * @throws \InvalidArgumentException If ID is invalid
     */
    public function forceDelete(int $id): bool
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID must be a positive integer');
        }

        $sql = 'DELETE FROM '.$this->getTable().' WHERE id = ?';
        $rowCount = $this->database->execute($sql, [$id]);

        return $rowCount > 0;
    }
}
