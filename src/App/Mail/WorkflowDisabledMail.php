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
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'blog_name' => $this->blogName,
                'edit_url' => $this->url('/dashboard/posts/'.$this->postId.'/edit'),
            ]);
    }
}
