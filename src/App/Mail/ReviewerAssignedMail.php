<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a reviewer they were assigned to review a post, and by whom.
 */
class ReviewerAssignedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private int $postId,
        private string $postTitle,
        private string $actorHandle
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'actor_handle' => $this->actorHandle,
                'review_url' => $this->url('/dashboard/posts/'.$this->postId.'/review'),
            ]);
    }
}
