<?php

declare(strict_types=1);

namespace App\Mail;

class PostNeedsChangesMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private int $postId,
        private string $postTitle,
        private string $reviewerUsername,
        private string $feedback
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('Changes requested on: '.$this->postTitle)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function editUrl(): string
    {
        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');

        return $appUrl.'/dashboard/posts/'.$this->postId.'/edit';
    }

    private function buildHtmlBody(): string
    {
        $title = htmlspecialchars($this->postTitle);
        $reviewer = htmlspecialchars($this->reviewerUsername);
        $feedback = nl2br(htmlspecialchars($this->feedback));
        $url = htmlspecialchars($this->editUrl());

        $feedbackBlock = $feedback !== ''
            ? "<blockquote style=\"border-left:3px solid #F59E0B;margin:16px 0;padding:8px 16px;background:#FFFBEB;\">{$feedback}</blockquote>"
            : '';

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>Changes requested</h2>
                <p><strong>{$reviewer}</strong> reviewed <strong>{$title}</strong> and asked for changes:</p>
                {$feedbackBlock}
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#F59E0B;color:#fff;text-decoration:none;border-radius:5px;">Edit post</a></p>
                <p style="color:#666;font-size:13px;">Resubmit when you're ready — the same review gate kicks in again.</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        $fb = $this->feedback === '' ? '' : "\nFeedback:\n{$this->feedback}\n";

        return "Changes requested: {$this->postTitle}\n"
             ."{$this->reviewerUsername} reviewed this post and asked for changes.{$fb}\n"
             ."Edit: {$this->editUrl()}\n";
    }
}
