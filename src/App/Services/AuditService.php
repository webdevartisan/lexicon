<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Database;

/**
 * Audit logging service for tracking user actions and system events.
 *
 * Records significant actions to the activity_log table for accountability and
 * security monitoring. A failed write throws, because a missing audit entry
 * nobody noticed is worse than a visible error.
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
     * @param  array<mixed>  $details  Additional context (old/new values, metadata)
     * @param  string|null  $ipAddress  Client IP address for security tracking
     */
    public function log(
        ?int $userId,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        array $details = [],
        ?string $ipAddress = null
    ): void {
        $this->database->execute(
            'INSERT INTO activity_log
                (user_id, action, resource_type, resource_id, details, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $userId,
                $action,
                $resourceType,
                $resourceId,
                $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
                $ipAddress,
            ]
        );
    }
}
