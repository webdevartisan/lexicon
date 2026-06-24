<?php

declare(strict_types=1);

namespace App\Mail;

class PostApprovedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private int    $postId,
        private string $postTitle,
        private string $reviewerUsername
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('Your post was approved: '.$this->postTitle)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function postUrl(): string
    {
        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');

        return $appUrl.'/dashboard/posts/'.$this->postId.'/review';
    }

    private function buildHtmlBody(): string
    {
        $title    = htmlspecialchars($this->postTitle);
        $reviewer = htmlspecialchars($this->reviewerUsername);
        $url      = htmlspecialchars($this->postUrl());

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>Approved</h2>
                <p><strong>{$reviewer}</strong> approved <strong>{$title}</strong>. An editor can now publish it.</p>
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#10B981;color:#fff;text-decoration:none;border-radius:5px;">Open post</a></p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "Approved: {$this->postTitle}\n"
             ."{$this->reviewerUsername} approved this post. An editor can now publish it.\n"
             ."Open: {$this->postUrl()}\n";
    }
}
