<?php

declare(strict_types=1);

namespace App\Mail;

class NewCommentMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $postTitle,
        private string $blogSlug,
        private string $postSlug,
        private string $commenterName,
        private string $commentExcerpt,
        private bool $awaitingModeration
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('New comment on: '.$this->postTitle)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function postUrl(): string
    {
        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');

        return $appUrl.'/blog/'.rawurlencode($this->blogSlug).'/'.rawurlencode($this->postSlug);
    }

    private function buildHtmlBody(): string
    {
        $title = htmlspecialchars($this->postTitle);
        $name = htmlspecialchars($this->commenterName);
        $excerpt = htmlspecialchars($this->commentExcerpt);
        $url = htmlspecialchars($this->postUrl());
        $note = $this->awaitingModeration
            ? '<p style="color:#92400E;">This comment is awaiting moderation before it appears publicly.</p>'
            : '';

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>New comment</h2>
                <p><strong>{$name}</strong> commented on <strong>{$title}</strong>:</p>
                <blockquote style="margin:0;padding:12px 16px;background:#F8FAFC;border-left:3px solid #2563EB;">{$excerpt}</blockquote>
                {$note}
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#2563EB;color:#fff;text-decoration:none;border-radius:5px;">View the post</a></p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        $note = $this->awaitingModeration ? "Awaiting moderation.\n" : '';

        return "{$this->commenterName} commented on: {$this->postTitle}\n\n\"{$this->commentExcerpt}\"\n{$note}View: {$this->postUrl()}\n";
    }
}
