<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use Framework\Database;

/**
 * Base class for the dev-data seeders.
 *
 * We write directly through the Database wrapper rather than the model layer:
 * a seeder inserts tens of thousands of rows, so batched multi-row inserts are
 * the difference between seconds and minutes, and the models only expose
 * single-row inserts. Every statement still uses prepared placeholders.
 */
abstract class Seeder
{
    public function __construct(protected Database $db) {}

    /**
     * Insert a single row and return its auto-increment id.
     *
     * @param  array<string, mixed>  $data  Column => value pairs
     * @return int New row id
     */
    protected function insertOne(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            $placeholders
        );

        $this->db->execute($sql, array_values($data));

        return (int) $this->db->lastInsertId();
    }

    /**
     * Insert many rows in one statement, chunked to stay well under the MySQL
     * placeholder ceiling. All rows must share the same set of columns; the
     * first row defines the column order.
     *
     * @param  array<int, array<string, mixed>>  $rows  Uniform column => value rows
     * @param  int  $chunkSize  Rows per INSERT statement
     */
    protected function insertMany(string $table, array $rows, int $chunkSize = 500): void
    {
        if ($rows === []) {
            return;
        }

        $columns = array_keys($rows[0]);
        $columnList = implode(', ', $columns);
        $rowPlaceholder = '('.implode(', ', array_fill(0, count($columns), '?')).')';

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $values = [];
            foreach ($chunk as $row) {
                foreach ($columns as $column) {
                    $values[] = $row[$column];
                }
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $table,
                $columnList,
                implode(', ', array_fill(0, count($chunk), $rowPlaceholder))
            );

            $this->db->execute($sql, $values);
        }
    }

    /**
     * Pick a random distinct subset of the given values.
     *
     * We keep the count within the pool size so unique-key targets (a user can
     * like a post once) never receive a duplicate.
     *
     * @param  array<int, mixed>  $pool  Values to choose from
     * @param  int  $min  Minimum items to return
     * @param  int  $max  Maximum items to return
     * @return array<int, mixed> Distinct random selection
     */
    protected function randomSubset(array $pool, int $min, int $max): array
    {
        $poolSize = count($pool);
        if ($poolSize === 0) {
            return [];
        }

        $take = min($poolSize, random_int($min, max($min, $max)));
        if ($take <= 0) {
            return [];
        }

        $keys = array_rand($pool, $take);

        // array_rand returns a bare key when asked for one item, an array otherwise
        $keys = is_array($keys) ? $keys : [$keys];

        return array_map(static fn ($key) => $pool[$key], $keys);
    }
}
