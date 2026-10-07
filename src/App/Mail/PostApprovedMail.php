<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a post's author a reviewer approved it, so an editor can publish it.
 */
class PostApprovedMail extends Mailable
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
                'post_url' => $this->url('/dashboard/posts/'.$this->postId.'/review'),
            ]);
    }
}
