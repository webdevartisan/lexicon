<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a post's author it is live.
 */
class PostPublishedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $postTitle,
        private string $blogSlug,
        private string $postSlug
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'post_url' => $this->url('/blog/'.rawurlencode($this->blogSlug).'/'.rawurlencode($this->postSlug)),
            ]);
    }
}
