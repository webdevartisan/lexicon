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
            ->fromTemplate([
                'new_email' => $this->newEmail,
                'confirm_url' => $this->url('/account/email/confirm/'.urlencode($this->token)),
                'expires_minutes' => $this->expiresInMinutes,
            ]);
    }
}
