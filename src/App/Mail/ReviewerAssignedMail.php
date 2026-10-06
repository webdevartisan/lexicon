<?php

declare(strict_types=1);

namespace App\Mail;

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
            ->subject('You were assigned to review: '.$this->postTitle)
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'actor_handle' => $this->actorHandle,
                'review_url' => $this->reviewUrl(),
            ]);
    }

    private function reviewUrl(): string
    {
        $appUrl = rtrim((string) (env('APP_URL', 'http://localhost')), '/');

        return $appUrl.'/dashboard/posts/'.$this->postId.'/review';
    }
}
