<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Templates\HtmlFragment;

/**
 * Tells a post's author a reviewer asked for changes, with the reviewer's words.
 */
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
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'reviewer_handle' => $this->reviewerHandle,
                'feedback' => HtmlFragment::fromText($this->feedback),
                'edit_url' => $this->url('/dashboard/posts/'.$this->postId.'/edit'),
            ]);
    }
}
