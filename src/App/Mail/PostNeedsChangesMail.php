<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Templates\HtmlFragment;

class PostNeedsChangesMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private int $postId,
        private string $postTitle,
        private string $reviewerHandle,
        private string $feedback
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject($this->t('subjects.PostNeedsChangesMail', ['post_title' => $this->postTitle]))
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'reviewer_handle' => $this->reviewerHandle,
                // Empty feedback leaves its box out of the email.
                'feedback' => HtmlFragment::fromText($this->feedback),
                'edit_url' => $this->editUrl(),
            ]);
    }

    private function editUrl(): string
    {
        $appUrl = rtrim((string) (env('APP_URL', 'http://localhost')), '/');

        return $appUrl.'/dashboard/posts/'.$this->postId.'/edit';
    }
}
