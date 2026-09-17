<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Who an erased account used to be, kept only long enough to answer a late
 * abuse report or a legal request. Admin-only; never shown publicly.
 */
class AccountErasureRecordModel extends AppModel
{
    protected ?string $table = 'account_erasure_records';

    /**
     * @param  list<int>  $postIds  Posts this account authored at the moment of erasure
     * @param  list<int>  $commentIds  Comments this account authored at the moment of erasure
     */
    public function record(
        int $originalUserId,
        string $handle,
        string $email,
        array $postIds,
        array $commentIds,
        ?int $erasedBy,
        ?string $erasedByIp
    ): void {
        $this->database->execute(
            'INSERT INTO '.$this->getTable().'
             (original_user_id, handle, email, post_ids, comment_ids, erased_by, erased_by_ip)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $originalUserId,
                $handle,
                $email,
                json_encode($postIds, JSON_THROW_ON_ERROR),
                json_encode($commentIds, JSON_THROW_ON_ERROR),
                $erasedBy,
                $erasedByIp,
            ]
        );
    }

    public function pruneOlderThan(int $days): int
    {
        return $this->database->execute(
            'DELETE FROM '.$this->getTable().' WHERE created_at < NOW() - INTERVAL ? DAY',
            [$days]
        );
    }
}
