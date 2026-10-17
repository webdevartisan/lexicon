<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Framework\Database;

/**
 * Puts mail_queue rows into the state a worker run leaves them in.
 */
final class MailQueueTestHelper
{
    /**
     * Claim one row the way claimBatch() does, so markSent()/markFailed()
     * accept it.
     *
     * @return string The claim token
     */
    public static function claim(Database $db, int $id): string
    {
        $token = bin2hex(random_bytes(16));

        $db->execute("UPDATE mail_queue SET status = 'sending', claim_token = ? WHERE id = ?", [$token, $id]);

        return $token;
    }
}
