<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent to the author when the blog owner disabled the workflow mid-flight
 * and their in-review post was reset to draft.
 */
class WorkflowDisabledMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private int $postId,
        private string $postTitle,
        private string $blogName
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('Your post was reset to draft: '.$this->postTitle)
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
        $blog = htmlspecialchars($this->blogName);
        $url = htmlspecialchars($this->editUrl());

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>Workflow disabled</h2>
                <p>The owner of <strong>{$blog}</strong> turned off the review workflow.
                   Your post <strong>{$title}</strong> was reset to draft — nothing was published automatically.</p>
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#2563EB;color:#fff;text-decoration:none;border-radius:5px;">Edit post</a></p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "Workflow disabled on {$this->blogName}.\n"
             ."Your post '{$this->postTitle}' was reset to draft.\n"
             ."Edit: {$this->editUrl()}\n";
    }
}
