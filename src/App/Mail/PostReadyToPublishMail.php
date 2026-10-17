<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells the people who can publish on a blog that a reviewer approved a post
 * and it is waiting for one of them to publish it.
 */
class PostReadyToPublishMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private int $postId,
        private string $postTitle,
        private string $reviewerHandle
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'reviewer_handle' => $this->reviewerHandle,
                'post_url' => $this->url('/dashboard/post/'.$this->postId.'/review'),
            ]);
    }
}
