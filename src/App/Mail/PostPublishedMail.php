<?php

declare(strict_types=1);

namespace App\Mail;

class PostPublishedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $postTitle,
        private string $blogSlug,
        private string $postSlug
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('Your post is live: '.$this->postTitle)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function publicUrl(): string
    {
        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');

        return $appUrl.'/blog/'.rawurlencode($this->blogSlug).'/'.rawurlencode($this->postSlug);
    }

    private function buildHtmlBody(): string
    {
        $title = htmlspecialchars($this->postTitle);
        $url = htmlspecialchars($this->publicUrl());

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>Published</h2>
                <p><strong>{$title}</strong> is now live.</p>
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#2563EB;color:#fff;text-decoration:none;border-radius:5px;">View on site</a></p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "Published: {$this->postTitle}\nView: {$this->publicUrl()}\n";
    }
}
