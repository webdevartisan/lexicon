<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\PasswordResetEmail;
use App\Models\PasswordResetModel;
use RuntimeException;

/**
 * Issues a password reset link: stores the token hash and queues the email.
 *
 * Shared by the public forgot-password form and the admin "send reset link"
 * action, so both hand out exactly the same kind of link. The two callers
 * treat failure differently, which is why this throws instead of deciding:
 * the public form hides it to avoid revealing which emails exist, the admin
 * page shows it because the admin needs to know the link never went out.
 */
class PasswordResetIssuer
{
    public const LIFETIME_MINUTES = 60;

    public function __construct(
        private PasswordResetModel $passwordResets,
    ) {}

    /**
     * @param  array<string, mixed>  $user  Must carry id and email
     *
     * @throws RuntimeException When mail is switched off or the token could not be stored
     */
    public function issue(array $user): void
    {
        // Checked first so a switched-off mailer never leaves a live token behind
        // for a link nobody received.
        if (env('MAIL_ENABLED', false) === false) {
            throw new RuntimeException('Email sending is switched off on this site (MAIL_ENABLED), so no reset link was sent.');
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (self::LIFETIME_MINUTES * 60));

        if (!$this->passwordResets->replaceForEmail((string) $user['email'], hash('sha256', $token), $expiresAt)) {
            throw new RuntimeException('The reset token could not be saved, so no link was sent.');
        }

        mail_queue()->enqueue(new PasswordResetEmail($user, $token, self::LIFETIME_MINUTES), 'user', (int) $user['id']);
    }
}
