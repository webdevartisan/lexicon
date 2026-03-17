<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Database;

/**
 * Audit logging service for tracking user actions and system events.
 *
 * Records all significant actions to the activity_log table for compliance,
 * security monitoring, and debugging purposes. Failures are logged but do
 * not interrupt normal operations.
 */
class AuditService
{
    public function __construct(
        private readonly Database $database
    ) {}

    /**
     * Log an action to the audit trail.
     *
     * @param  int|null  $userId  User performing the action (null for system actions)
     * @param  string  $action  Action performed (e.g., 'user.deleted', 'post.published')
     * @param  string  $resourceType  Resource type (e.g., 'user', 'post', 'comment')
     * @param  int|null  $resourceId  ID of affected resource
     * @param  array  $details  Additional context (old/new values, metadata)
     * @param  string|null  $ipAddress  Client IP address for security tracking
     * @return bool True on success, false on failure
     */
    public function log(
        ?int $userId,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        array $details = [],
        ?string $ipAddress = null
    ): bool {
        try {
            $sql = 'INSERT INTO activity_log
                    (user_id, action, resource_type, resource_id, details, ip_address, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())';

            // Serialize details as JSON for flexible storage of arbitrary metadata
            $detailsJson = !empty($details) ? json_encode($details) : null;

            $rowCount = $this->database->execute($sql, [
                $userId,
                $action,
                $resourceType,
                $resourceId,
                $detailsJson,
                $ipAddress,
            ]);

            return $rowCount > 0;
        } catch (\Throwable $e) {
            // Swallow exceptions to prevent audit failures from breaking operations
            error_log('Audit log failed: '.$e->getMessage());

            return false;
        }
    }
}
