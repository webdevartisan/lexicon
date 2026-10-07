<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Password reset email with secure token link.
 *
 * We send this when a user requests a password reset. The email contains
 * a time-limited token link for security.
 */
class PasswordResetEmail extends Mailable
{
    protected string $tier = self::TIER_CRITICAL;

    /**
     * @param  array<string, mixed>  $user  The account: email, handle and first_name
     */
    public function __construct(
        private array $user,
        private string $token,
        private int $expiresInMinutes = 60
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $name = WelcomeEmail::nameOf($this->user);

        $this->to((string) $this->user['email'], $name)
            ->fromTemplate([
                'name' => $name,
                'reset_url' => $this->url('/password/reset/'.urlencode($this->token)),
                'expires_minutes' => $this->expiresInMinutes,
            ]);
    }
}
