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
        private string $authorHandle,
        private bool $unassigned
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('Review requested: '.$this->postTitle)
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'author_handle' => $this->authorHandle,
                'review_url' => $this->reviewUrl(),
                'unassigned_note' => $this->unassigned
                    ? 'No reviewer is assigned yet. Any reviewer on this blog can claim it.'
                    : '',
            ]);
    }

    private function reviewUrl(): string
    {
        $appUrl = rtrim((string) (env('APP_URL', 'http://localhost')), '/');

        return $appUrl.'/dashboard/posts/'.$this->postId.'/review';
    }
}
