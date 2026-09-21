<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\EmailChangeVerificationMail;
use App\Models\PendingEmailChangeModel;

/**
 * Starts an email change: records the pending address and mails a
 * confirmation link to it. users.email is not touched until that link is
 * followed by the account holder, signed in as themselves.
 *
 * Shared by the account's own settings page and the admin user editor, so an
 * address typed by an administrator has to prove itself exactly the way one
 * typed by the owner does. Without that, a typo in the admin form would hand
 * account recovery to whoever owns the mistyped inbox.
 */
class EmailChangeIssuer
{
    public const TOKEN_TTL_MINUTES = 60;

    public function __construct(
        private PendingEmailChangeModel $pending,
        private MailQueueService $mailQueue,
    ) {}

    public function issue(int $userId, string $newEmail): void
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::TOKEN_TTL_MINUTES * 60);

        $this->pending->replaceForUser($userId, $newEmail, hash('sha256', $token), $expiresAt);

        $this->mailQueue->enqueue(
            new EmailChangeVerificationMail($newEmail, $token, self::TOKEN_TTL_MINUTES),
            'account',
            $userId
        );
    }
}
