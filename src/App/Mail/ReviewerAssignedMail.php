<?php

declare(strict_types=1);

namespace App\Mail;

class ReviewerAssignedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private int $postId,
        private string $postTitle,
        private string $assignedByUsername
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('You were assigned to review: '.$this->postTitle)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function reviewUrl(): string
    {
        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');

        return $appUrl.'/dashboard/posts/'.$this->postId.'/review';
    }

    private function buildHtmlBody(): string
    {
        $title = htmlspecialchars($this->postTitle);
        $by = htmlspecialchars($this->assignedByUsername);
        $url = htmlspecialchars($this->reviewUrl());

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>You're up for review</h2>
                <p><strong>{$by}</strong> assigned you to review <strong>{$title}</strong>.</p>
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#2563EB;color:#fff;text-decoration:none;border-radius:5px;">Open review page</a></p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "Assigned to review: {$this->postTitle}\n"
             ."{$this->assignedByUsername} assigned you.\n"
             ."Open: {$this->reviewUrl()}\n";
    }
}
