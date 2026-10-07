<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent to the PREVIOUS email address when an account's email is changed.
 *
 * OWASP's guidance on changing a registered address is to notify the old
 * address as well as the new one, so an account takeover is visible to the
 * person losing the account.
 *
 * Queued like every other message, so a mail outage cannot fail the save.
 */
class EmailChangedMail extends Mailable
{
    public function __construct(
        private string $oldEmail,
        private string $newEmail,
        private string $changedAt
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->oldEmail)
            ->fromTemplate([
                'new_email' => $this->newEmail,
                'changed_at' => $this->when(),
                'password_url' => $this->url('/password/forgot'),
            ]);
    }

    /**
     * The time of the change as the reader's language writes it, in UTC.
     */
    private function when(): string
    {
        try {
            return $this->date((new \DateTimeImmutable($this->changedAt))->setTimezone(new \DateTimeZone('UTC')), 'yMMMdjm');
        } catch (\Exception) {
            return $this->changedAt;
        }
    }
}
