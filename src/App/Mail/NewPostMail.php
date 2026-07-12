<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent to blog subscribers when a new post goes live.
 */
class NewPostMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $blogName,
        private string $postTitle,
        private string $blogSlug,
        private string $postSlug,
        private string $unsubscribeToken
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('New on '.$this->blogName.': '.$this->postTitle)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function appUrl(): string
    {
        return rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');
    }

    private function postUrl(): string
    {
        return $this->appUrl().'/blog/'.rawurlencode($this->blogSlug).'/'.rawurlencode($this->postSlug);
    }

    private function unsubscribeUrl(): string
    {
        return $this->appUrl().'/subscriptions/unsubscribe/'.rawurlencode($this->unsubscribeToken);
    }

    private function buildHtmlBody(): string
    {
        $blog = htmlspecialchars($this->blogName);
        $title = htmlspecialchars($this->postTitle);
        $url = htmlspecialchars($this->postUrl());
        $unsubscribe = htmlspecialchars($this->unsubscribeUrl());

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>{$blog} just published a new post</h2>
                <p><strong>{$title}</strong></p>
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#2563EB;color:#fff;text-decoration:none;border-radius:5px;">Read it</a></p>
                <p style="margin-top:32px;font-size:12px;color:#888;">
                    You are receiving this because you subscribed to {$blog}.
                    <a href="{$unsubscribe}" style="color:#888;">Unsubscribe</a>
                </p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return $this->blogName." just published a new post:\n\n"
            .$this->postTitle."\n"
            .$this->postUrl()."\n\n"
            .'Unsubscribe: '.$this->unsubscribeUrl();
    }
}
