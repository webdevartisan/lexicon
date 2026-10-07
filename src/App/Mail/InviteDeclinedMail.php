<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent to the blog owner when an invitee declines.
 */
class InviteDeclinedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $blogName,
        private string $declinedEmail
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'declined_email' => $this->declinedEmail,
                'dashboard_url' => $this->url('/dashboard'),
            ]);
    }
}
