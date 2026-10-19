<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Mailable;
use App\Mail\WelcomeEmail;
use App\Models\EmailVerificationModel;
use App\Models\UserModel;

/**
 * Proves a new account's address by mailing it a link.
 *
 * Until the link is followed the account can read but not take part, and
 * MailQueueService sends it nothing else. The welcome email is the carrier, so
 * a sign-up costs one message rather than two.
 */
class EmailVerificationService
{
    public const TOKEN_TTL_HOURS = 24;

    public function __construct(
        private EmailVerificationModel $links,
        private UserModel $users,
        private MailQueueService $mailQueue,
        private RecipientLocale $locales,
    ) {}

    /**
     * Mail a fresh link, replacing any earlier one.
     *
     * @param  int  $userId  Account to confirm
     * @return bool False when the account is missing or already confirmed
     */
    public function send(int $userId): bool
    {
        $user = $this->users->find($userId);
        if (!$user || !email_unverified($user)) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $this->links->replaceForUser(
            $userId,
            hash('sha256', $token),
            gmdate('Y-m-d H:i:s', time() + self::TOKEN_TTL_HOURS * 3600)
        );

        $welcome = Mailable::inLocale(
            $this->locales->forUser($userId, $this->locales->current()),
            fn (): WelcomeEmail => new WelcomeEmail($user, $token, self::TOKEN_TTL_HOURS)
        );
        $this->mailQueue->enqueue($welcome, 'user', $userId);

        return true;
    }

    /**
     * Mark the address confirmed if the token is live.
     *
     * @param  string  $token  Raw token from the link
     * @return int|null The confirmed user's id, or null for an unknown or expired token
     */
    public function confirm(string $token): ?int
    {
        $row = $this->links->findValidByTokenHash(hash('sha256', $token));
        if (!$row) {
            return null;
        }

        $userId = (int) $row['user_id'];
        $this->markVerified($userId);

        return $userId;
    }

    /**
     * Mark an address confirmed without a link, for routes that already proved it.
     */
    public function markVerified(int $userId): void
    {
        $this->users->updateById($userId, ['email_verified_at' => gmdate('Y-m-d H:i:s')]);
        $this->links->deleteForUser($userId);
    }
}
