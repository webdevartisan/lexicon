<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent to the NEW address when someone asks to move an account onto it.
 *
 * The address does not change until this link is followed, so the token is
 * what proves the requester actually controls the inbox. The previous address
 * is told separately by EmailChangedMail once the move completes.
 */
class EmailChangeVerificationMail extends Mailable
{
    /** The recipient is waiting on a link that expires, so it cannot queue behind a fan-out. */
    protected string $tier = self::TIER_CRITICAL;

    public function __construct(
        private string $newEmail,
        private string $token,
        private int $expiresInMinutes = 60
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->newEmail)
            ->subject($this->t('subjects.EmailChangeVerificationMail', ['app_name' => $this->appName()]))
            ->fromTemplate([
                'new_email' => $this->newEmail,
                'confirm_url' => $this->confirmUrl(),
                'expires_in' => $this->t('phrases.minutes', ['count' => $this->expiresInMinutes]),
                'expires_in_minutes' => $this->expiresInMinutes,
            ]);
    }

    private function appName(): string
    {
        return (string) env('APP_NAME', 'Lexicon');
    }

    /**
     * The confirmation link, carrying the raw token that only this message holds.
     */
    private function confirmUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/')
            .'/account/email/confirm/'.urlencode($this->token);
    }
}
