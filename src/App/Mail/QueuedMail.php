<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * A queue row presented back to MailService as a Mailable.
 *
 * The content was already rendered when the mail was queued, so this only
 * re-wraps stored columns. It exists so the worker can hand rows to the same
 * MailService::send() every other caller uses, instead of the queue growing a
 * second, subtly different delivery path.
 */
class QueuedMail extends Mailable
{
    /**
     * @param  array<string, mixed>  $row  A mail_queue row
     */
    public function __construct(private array $row)
    {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to(
            (string) $this->row['to_email'],
            (string) ($this->row['to_name'] ?? '')
        )->subject((string) $this->row['subject'])
            ->html((string) $this->row['body_html']);

        if (!empty($this->row['body_text'])) {
            $this->textAlternative((string) $this->row['body_text']);
        }
    }
}
