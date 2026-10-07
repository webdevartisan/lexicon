<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Templates\HtmlFragment;

/**
 * A visitor's message from the public contact form, sent to the site admin.
 *
 * Reply-to is set to the visitor so the admin can answer directly from
 * their mail client.
 */
class ContactMessageMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $senderName,
        private string $senderEmail,
        private string $messageSubject,
        private string $messageBody
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->replyTo($this->senderEmail, $this->senderName)
            ->fromTemplate([
                'sender_name' => $this->senderName,
                'sender_email' => $this->senderEmail,
                'message_subject' => $this->messageSubject,
                'message_body' => HtmlFragment::fromText($this->messageBody),
            ]);
    }
}
