<?php

declare(strict_types=1);

namespace App\Mail;

class CollaboratorRemovedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $blogName,
        private string $removedByUsername
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('You were removed from '.$this->blogName)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function buildHtmlBody(): string
    {
        $blog = htmlspecialchars($this->blogName);
        $by = htmlspecialchars($this->removedByUsername);

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>Access removed</h2>
                <p>Your access to <strong>{$blog}</strong> was revoked by {$by}.</p>
                <p>If you think this was a mistake, contact the blog owner.</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "Your access to {$this->blogName} was revoked by {$this->removedByUsername}.\n";
    }
}
