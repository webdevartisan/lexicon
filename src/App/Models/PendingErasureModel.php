<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Self-requested erasures waiting out their grace period. See AccountErasureSchedulerService.
 */
class PendingErasureModel extends AppModel
{
    protected ?string $table = 'pending_erasures';

    public function schedule(int $userId, ?int $erasedBy, ?string $erasedByIp, string $scheduledFor): void
    {
        $this->database->execute(
            'INSERT INTO '.$this->getTable().' (user_id, erased_by, erased_by_ip, scheduled_for)
             VALUES (?, ?, ?, ?)',
            [$userId, $erasedBy, $erasedByIp, $scheduledFor]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function due(): array
    {
        return $this->database->query(
            'SELECT * FROM '.$this->getTable().' WHERE scheduled_for <= UTC_TIMESTAMP()'
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function cancel(int $userId): void
    {
        $this->database->execute('DELETE FROM '.$this->getTable().' WHERE user_id = ?', [$userId]);
    }
}
