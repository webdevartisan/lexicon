<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent to the blog owner when an assigned reviewer lost reviewPost capability
 * (revoked, downgraded, etc.) and the post was reopened to all reviewers.
 */
class ReviewerStaleMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private int $postId,
        private string $postTitle,
        private string $formerReviewerUsername
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('Reviewer reset on: '.$this->postTitle)
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
        $former = htmlspecialchars($this->formerReviewerUsername);
        $url = htmlspecialchars($this->reviewUrl());

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>Reviewer reset</h2>
                <p><strong>{$former}</strong> can no longer review <strong>{$title}</strong> (role changed or revoked).
                   The post was reopened to all reviewers.</p>
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#2563EB;color:#fff;text-decoration:none;border-radius:5px;">Open post</a></p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "Reviewer reset on: {$this->postTitle}\n"
             ."{$this->formerReviewerUsername} can no longer review this post. Reopened to all reviewers.\n"
             ."Open: {$this->reviewUrl()}\n";
    }
}
