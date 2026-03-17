<?php

declare(strict_types=1);

namespace Framework;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use InvalidArgumentException;

/**
 * Base Model class providing CRUD operations and database interaction.
 *
 * Provides common database operations for all models including insert, update,
 * delete, find, and transaction management. Table names are auto-derived from
 * class names using pluralization. All queries use the Database wrapper for
 * proper logging, error handling, and prepared statement execution.
 */
abstract class Model
{
    /**
     * Explicit table name; if null, derived from class name.
     */
    protected ?string $table = null;

    protected Database $database;

    private Inflector $inflector;

    /**
     * Initialize the model with a database connection.
     *
     * @param  Database  $database  Database service instance
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
        // Build inflector once to avoid overhead in repeated table name resolution
        $this->inflector = InflectorFactory::create()->build();
    }

    /**
     * Get the last auto-increment ID generated on this connection.
     *
     * @return int Last insert ID
     */
    public function getInsertID(): int
    {
        return (int) $this->database->lastInsertId();
    }

    /**
     * Resolve the table name from explicit property or class name.
     *
     * Converts class names to pluralized snake_case.
     * Example: App\Models\BlogPost to "blog_posts"
     *
     * @return string Table name
     */
    protected function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        $parts = explode('\\', static::class);
        $className = array_pop($parts);

        $snake = strtolower(
            preg_replace('/(?<!^)[A-Z]/', '_$0', (string) $className)
        );

        return $this->inflector->pluralize($snake);
    }

    /**
     * Fetch all rows from the table.
     *
     * @return array<int, array<string, mixed>> All rows
     */
    public function findAll(): array
    {
        $sql = 'SELECT * FROM '.$this->getTable();
        $stmt = $this->database->query($sql);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single row by primary key.
     *
     * @param  string|int  $id  Primary key value
     * @return array|null Row data or null if not found
     */
    public function find(string|int $id): ?array
    {
        $sql = 'SELECT * FROM '.$this->getTable().' WHERE id = ?';
        $stmt = $this->database->query($sql, [(int) $id]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Fetch rows matching a column/value pair.
     *
     * @param  string  $column  Column name to filter by
     * @param  mixed  $value  Value to match
     * @return array<int, array<string, mixed>> Matching rows
     *
     * @throws InvalidArgumentException If column name fails validation
     */
    public function findBy(string $column, mixed $value): array
    {
        // Validate column name to prevent SQL injection since it's interpolated into query
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException("Invalid column name '{$column}'.");
        }

        $sql = 'SELECT * FROM '.$this->getTable()." WHERE {$column} = ?";
        $stmt = $this->database->query($sql, [$value]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Insert a new row into the table.
     *
     * @param  array<string, mixed>  $data  Column => value pairs to insert
     * @return bool|int Last insert ID on success, false on failure
     *
     * @throws InvalidArgumentException If data array is empty
     */
    public function insert(array $data): bool|int
    {
        if ($data === []) {
            throw new InvalidArgumentException('Cannot insert an empty data set.');
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->getTable(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $rowCount = $this->database->execute($sql, array_values($data));

        if ($rowCount > 0) {
            return (int) $this->getInsertID();
        }

        return false;
    }

    /**
     * Update an existing row by primary key.
     *
     * @param  int|string  $id  Primary key value
     * @param  array<string, mixed>  $data  Column => value pairs to update
     * @return bool True on success
     */
    public function update(int|string $id, array $data): bool
    {
        // Remove ID from data to prevent accidental primary key mutation
        if (array_key_exists('id', $data)) {
            unset($data['id']);
        }

        if ($data === []) {
            return true;
        }

        $columns = array_keys($data);
        $assignments = array_map(
            static fn (string $col): string => "{$col} = ?",
            $columns
        );

        $sql = sprintf(
            'UPDATE %s SET %s WHERE id = ?',
            $this->getTable(),
            implode(', ', $assignments)
        );

        // Append ID as the last parameter
        $params = array_merge(array_values($data), [(int) $id]);
        $rowCount = $this->database->execute($sql, $params);

        return $rowCount > 0;
    }

    /**
     * Delete a row by primary key.
     *
     * @param  int|string  $id  Primary key value
     * @return bool True on success
     */
    public function delete(int|string $id): bool
    {
        $sql = 'DELETE FROM '.$this->getTable().' WHERE id = ?';
        $rowCount = $this->database->execute($sql, [(int) $id]);

        return $rowCount > 0;
    }

    /**
     * Count total rows in the table.
     *
     * @return int Total row count
     */
    public function count(): int
    {
        $sql = 'SELECT COUNT(*) FROM '.$this->getTable();
        $stmt = $this->database->query($sql);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Fetch the most recent rows ordered by creation time.
     *
     * @param  int  $limit  Maximum number of rows to return
     * @return array<int, array<string, mixed>> Latest rows
     */
    public function latest(int $limit = 5): array
    {
        $sql = 'SELECT * FROM '.$this->getTable().' ORDER BY created_at DESC LIMIT ?';
        $stmt = $this->database->query($sql, [$limit]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Execute a callback within a database transaction.
     *
     * Automatically commits on success or rolls back on exception.
     *
     * @param  callable  $callback  Function to execute within transaction
     * @return mixed Return value from callback
     *
     * @throws \Throwable If callback fails
     */
    public function transaction(callable $callback): mixed
    {
        return $this->database->transaction($callback);
    }

    /**
     * Begin a database transaction manually.
     *
     * @return bool True on success
     */
    protected function beginTransaction(): bool
    {
        return $this->database->beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return bool True on success
     */
    protected function commit(): bool
    {
        return $this->database->commit();
    }

    /**
     * Rollback the current transaction.
     *
     * @return bool True on success
     */
    protected function rollback(): bool
    {
        return $this->database->rollback();
    }

    /**
     * Check if currently within an active transaction.
     *
     * @return bool True if in transaction
     */
    protected function inTransaction(): bool
    {
        return $this->database->inTransaction();
    }
}
