<?php

namespace Framework\Validation;

use Framework\Database;

/**
 * Extended validator with database access for unique/exists checks.
 *
 * inject the Database dependency to validate against real data.
 * This keeps the base Validator lightweight while allowing database rules.
 */
class DatabaseValidator extends Validator
{
    /**
     * Create a new database validator instance.
     *
     * @param array $data The data to validate
     * @param Database $database The database connection for validation queries
     */
    public function __construct(
        array $data,
        protected Database $database
    ) {
        parent::__construct($data);
    }

    /**
     * Validate unique constraint in database.
     *
     * Parameter format: "table,column" or "table,column,exceptId"
     *
     * Examples:
     * - 'email' => 'unique:users,email'        // Must be unique in users.email
     * - 'email' => 'unique:users,email,5'      // Must be unique except for id=5
     *
     * @param mixed $value The value to check for uniqueness
     * @param string|null $param The rule parameter (table,column,exceptId)
     * @param string $field The field name being validated
     * @return bool True if unique (or value is empty), false if duplicate exists
     * @throws \InvalidArgumentException If table or column names contain invalid characters
     */
    protected function validateUnique(mixed $value, ?string $param, string $field): bool
    {
        if (!is_string($value) || trim($value) === '' || !$param) {
            return true;
        }

        $parts = explode(',', $param);
        $table = trim($parts[0]);
        $column = trim($parts[1] ?? $field);
        $exceptId = isset($parts[2]) ? (int) trim($parts[2]) : null;

        // Validate table/column names to prevent SQL injection via rule parameters
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) ||
            !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException('Invalid table or column name in unique rule.');
        }

        // Build query with positional parameters for Database wrapper
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
        $params = [$value];

        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }

        $stmt = $this->database->query($sql, $params);
        $count = (int) $stmt->fetchColumn();

        return $count === 0;
    }

    /**
     * Validate that value exists in database.
     *
     * Parameter format: "table,column"
     *
     * Example: 'category_id' => 'exists:categories,id'
     *
     * @param mixed $value The value to check for existence
     * @param string|null $param The rule parameter (table,column)
     * @param string $field The field name being validated
     * @return bool True if exists (or value is empty), false if not found
     * @throws \InvalidArgumentException If table or column names contain invalid characters
     */
    protected function validateExists(mixed $value, ?string $param, string $field): bool
    {
        if ($value === null || $value === '' || !$param) {
            return true;
        }

        $parts = explode(',', $param);
        $table = trim($parts[0]);
        $column = trim($parts[1] ?? 'id');

        // Validate table/column names to prevent SQL injection via rule parameters
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) ||
            !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException('Invalid table or column name in exists rule.');
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
        $stmt = $this->database->query($sql, [$value]);

        $count = (int) $stmt->fetchColumn();

        return $count > 0;
    }
}
