<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Retention access to the impersonation_sessions audit table.
 *
 * ImpersonationService owns the writes; this model only trims stored IP addresses.
 */
class ImpersonationSessionModel extends AppModel
{
    protected ?string $table = 'impersonation_sessions';

    /**
     * Clear IP addresses past the retention period, keeping the rows as the audit record.
     *
     * @return int Rows cleared
     */
    public function clearIpsOlderThan(int $days): int
    {
        return $this->database->execute(
            'UPDATE impersonation_sessions SET ip_address = NULL
             WHERE ip_address IS NOT NULL AND started_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$days]
        );
    }
}
