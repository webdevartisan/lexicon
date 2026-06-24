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
            ->subject('Invite to '.$this->blogName.' was declined')
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function buildHtmlBody(): string
    {
        $blog    = htmlspecialchars($this->blogName);
        $email   = htmlspecialchars($this->declinedEmail);

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>Invite declined</h2>
                <p><strong>{$email}</strong> declined your invitation to <strong>{$blog}</strong>.</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "{$this->declinedEmail} declined your invitation to {$this->blogName}.\n";
    }
}
