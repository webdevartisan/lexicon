<?php

declare(strict_types=1);

namespace Framework;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Database Service
 *
 * Provides PDO connection management with transaction support, query logging,
 * and helper methods for common database operations.
 */
class Database
{
    private ?PDO $pdo = null;

    private array $queryLog = [];

    private readonly array $config;

    /**
     * Create a new Database instance.
     *
     * @param  array  $config  Database configuration array
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get the shared PDO connection.
     *
     * Lazy-initializes the connection to avoid overhead when not needed.
     *
     * @throws RuntimeException If connection fails
     */
    public function getConnection(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s;port=%d',
            $this->config['host'],
            $this->config['name'],
            $this->config['charset'],
            $this->config['port']
        );

        $options = $this->config['options'];

        // Add persistent connection if enabled
        if ($this->config['persistent'] ?? false) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['user'],
                $this->config['password'],
                $options
            );
        } catch (PDOException $e) {
            // Avoid leaking credentials in error messages
            throw new RuntimeException(
                sprintf('Database connection failed: %s', $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }

        return $this->pdo;
    }

    /**
     * Execute a SQL query with optional parameters.
     *
     * @param  string  $sql  SQL query string
     * @param  array  $params  Query parameters for prepared statement
     *
     * @throws RuntimeException If query fails
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $startTime = microtime(true);

        try {
            $stmt = $this->getConnection()->prepare($sql);

            // Use explicit type binding for better type handling
            if (!empty($params)) {
                $position = 1;
                foreach ($params as $value) {
                    $stmt->bindValue($position++, $value, $this->detectType($value));
                }
                $stmt->execute();
            } else {
                $stmt->execute();
            }

            $this->logQuery($sql, $params, $startTime);

            return $stmt;
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Query failed: %s | SQL: %s', $e->getMessage(), $sql),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Execute a non-SELECT query (INSERT, UPDATE, DELETE).
     *
     * @param  string  $sql  SQL query string
     * @param  array  $params  Query parameters
     * @return int Number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * Get the ID of the last inserted row.
     *
     * @return string Last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Begin a database transaction.
     *
     * @return bool True on success
     *
     * @throws RuntimeException If transaction cannot be started or already active
     */
    public function beginTransaction(): bool
    {
        if ($this->inTransaction()) {
            throw new RuntimeException('Transaction already active. Nested transactions are not supported.');
        }

        try {
            return $this->getConnection()->beginTransaction();
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Failed to begin transaction: %s', $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Commit the current transaction.
     *
     * @return bool True on success
     *
     * @throws RuntimeException If commit fails
     */
    public function commit(): bool
    {
        try {
            return $this->getConnection()->commit();
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Failed to commit transaction: %s', $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Rollback the current transaction.
     *
     * Use when an error occurs to maintain data integrity.
     *
     * @return bool True on success
     *
     * @throws RuntimeException If rollback fails
     */
    public function rollback(): bool
    {
        try {
            return $this->getConnection()->rollBack();
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Failed to rollback transaction: %s', $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Check if currently in a transaction.
     */
    public function inTransaction(): bool
    {
        return $this->pdo !== null && $this->pdo->inTransaction();
    }

    /**
     * Execute a callback within a database transaction.
     *
     * Automatically commits on success or rolls back on exception.
     * Prevents nested transactions by checking active transaction state.
     *
     * @param  callable  $callback  Function to execute within transaction
     * @return mixed Return value from callback
     *
     * @throws \Throwable If callback fails (after rollback)
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this->getConnection());
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Log a query for debugging purposes.
     *
     * Only logs when DB_LOG_QUERIES=true or query exceeds slow threshold.
     *
     * @param  string  $sql  SQL query
     * @param  array  $params  Query parameters
     * @param  float  $startTime  Query start time (microtime)
     */
    private function logQuery(string $sql, array $params, float $startTime): void
    {
        $executionTime = microtime(true) - $startTime;

        $logAllQueries = $this->config['log_queries'] ?? false;
        $logSlowQueries = $this->config['log_slow_queries'] ?? false;
        $slowThreshold = $this->config['slow_query_threshold'] ?? 1.0;

        $isSlow = $executionTime >= $slowThreshold;

        // Skip logging if neither condition is met
        if (!$logAllQueries && !($logSlowQueries && $isSlow)) {
            return;
        }

        $logEntry = [
            'sql' => $sql,
            'params' => $params,
            'time' => round($executionTime * 1000, 2), // milliseconds
            'timestamp' => date('Y-m-d H:i:s'),
            'slow' => $isSlow,
        ];

        $this->queryLog[] = $logEntry;

        // Write to log file
        $this->writeQueryLog($logEntry);
    }

    /**
     * Write query log entry to file.
     * TODO: Must revisit this to use a proper logging library for better performance and features.
     *
     * @param  array  $logEntry  Log entry data
     */
    private function writeQueryLog(array $logEntry): void
    {
        $logDir = ROOT_PATH.'/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir.'/queries.log';

        $message = sprintf(
            "[%s] %s | Time: %sms | SQL: %s | Params: %s\n",
            $logEntry['timestamp'],
            $logEntry['slow'] ? 'SLOW QUERY' : 'QUERY',
            $logEntry['time'],
            $logEntry['sql'],
            json_encode($logEntry['params'])
        );

        file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get all logged queries for current request.
     *
     * Useful for debugging and profiling.
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log.
     */
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Map a PHP value to the appropriate PDO parameter type.
     *
     * We use explicit type binding for booleans, integers, and nulls
     * to ensure MySQL receives the correct data types.
     *
     * @param  mixed  $value  Value to detect type for
     * @return int PDO::PARAM_* constant
     */
    private function detectType(mixed $value): int
    {
        return match (true) {
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
