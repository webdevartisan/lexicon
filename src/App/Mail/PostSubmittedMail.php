<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Email sent to a post's assigned reviewers when its author submits it for
 * review. When nobody is assigned yet, PostSubmittedUnassignedMail goes to
 * everyone who could pick it up instead.
 */
class PostSubmittedMail extends Mailable
{
    public function __construct(
        protected string $toEmail,
        protected int $postId,
        protected string $postTitle,
        protected string $authorHandle
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'author_handle' => $this->authorHandle,
                'review_url' => $this->url('/dashboard/posts/'.$this->postId.'/review'),
            ]);
    }
}
