<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Email sent to a reviewer (assigned or all-reviewers fan-out) when an author
 * submits a post for review.
 */
class PostSubmittedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private int $postId,
        private string $postTitle,
        private string $authorUsername,
        private bool $unassigned
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('Review requested: '.$this->postTitle)
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
        $appName = htmlspecialchars((string) ($_ENV['APP_NAME'] ?? 'Blog Platform'));
        $title = htmlspecialchars($this->postTitle);
        $author = htmlspecialchars($this->authorUsername);
        $url = htmlspecialchars($this->reviewUrl());
        $intro = $this->unassigned
            ? '<p><em>No reviewer is assigned yet. Any reviewer on this blog can claim it.</em></p>'
            : '';

        return <<<HTML
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2>Review requested</h2>
                <p><strong>{$author}</strong> has submitted <strong>{$title}</strong> for review.</p>
                {$intro}
                <p>
                    <a href="{$url}" style="display:inline-block;padding:12px 24px;background:#2563EB;color:#fff;text-decoration:none;border-radius:5px;">
                        Open review page
                    </a>
                </p>
                <p style="font-size:12px;color:#666;">&copy; 2026 {$appName}</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        $unassigned = $this->unassigned
            ? "\nNo reviewer is assigned yet. Any reviewer on this blog can claim it.\n"
            : '';

        return "Review requested: {$this->postTitle}\n"
             ."{$this->authorUsername} has submitted this post for review."
             ."{$unassigned}\n"
             ."Open the review page: {$this->reviewUrl()}\n";
    }
}
