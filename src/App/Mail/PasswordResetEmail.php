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
    /** Someone is locked out and holding a token that expires, so this cannot wait behind a fan-out. */
    protected string $tier = self::TIER_CRITICAL;

    /**
     * @param  array<string, mixed>  $user  User row (first_name, email)
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
        $firstName = (string) ($this->user['first_name'] ?? '') ?: 'there';

        $this->to($this->user['email'], $firstName)
            ->subject('Password Reset Request')
            ->fromTemplate([
                'first_name' => $firstName,
                'reset_url' => $this->resetUrl(),
                'expires_in_minutes' => $this->expiresInMinutes,
            ]);
    }

    private function resetUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/')
            .'/password/reset/'.urlencode($this->token);
    }
}
