<?php

declare(strict_types=1);

namespace App\Mail;

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
            ->subject($this->t('subjects.PostApprovedMail', ['post_title' => $this->postTitle]))
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'reviewer_handle' => $this->reviewerHandle,
                'post_url' => $this->postUrl(),
            ]);
    }

    private function postUrl(): string
    {
        $appUrl = rtrim((string) (env('APP_URL', 'http://localhost')), '/');

        return $appUrl.'/dashboard/posts/'.$this->postId.'/review';
    }
}
