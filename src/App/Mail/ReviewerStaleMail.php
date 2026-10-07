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
        private string $formerReviewerHandle
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'former_reviewer_handle' => $this->formerReviewerHandle,
                'review_url' => $this->url('/dashboard/posts/'.$this->postId.'/review'),
            ]);
    }
}
