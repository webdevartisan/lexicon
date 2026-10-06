<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells an author a post of theirs has passed a number of views.
 */
class InsightsMilestoneMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $postTitle,
        private int $threshold,
        private int $blogId,
        private int $postId,
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('"'.$this->postTitle.'" passed '.number_format($this->threshold).' views')
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function analyticsUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/')
            .'/dashboard/blog/'.$this->blogId.'/insights/posts/'.$this->postId.'?range=12m';
    }

    private function buildHtmlBody(): string
    {
        $title = htmlspecialchars($this->postTitle);
        $count = number_format($this->threshold);
        $url = htmlspecialchars($this->analyticsUrl());

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>{$count} views</h2>
                <p>Your post <strong>{$title}</strong> has now been read more than {$count} times.</p>
                <p><a href="{$url}" style="display:inline-block;padding:10px 18px;background:#1f2937;color:#fff;text-decoration:none;border-radius:4px;">See how it got there</a></p>
                <p style="font-size:12px;color:#777;">Switch these off in your notification settings.</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        $count = number_format($this->threshold);

        return "Your post \"{$this->postTitle}\" has now been read more than {$count} times.\n\nSee how it got there: {$this->analyticsUrl()}\n";
    }
}
