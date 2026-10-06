<?php

declare(strict_types=1);

namespace App\Mail;

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
            ->subject($this->t('subjects.PostPublishedMail', ['post_title' => $this->postTitle]))
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'post_url' => $this->publicUrl(),
            ]);
    }

    private function publicUrl(): string
    {
        $appUrl = rtrim((string) (env('APP_URL', 'http://localhost')), '/');

        return $appUrl.'/blog/'.rawurlencode($this->blogSlug).'/'.rawurlencode($this->postSlug);
    }
}
