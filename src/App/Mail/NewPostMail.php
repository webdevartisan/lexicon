<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent to blog subscribers when a new post goes live.
 */
class NewPostMail extends Mailable
{
    /** Goes to every subscriber at once, so throughput matters far more than latency. */
    protected string $tier = self::TIER_BULK;

    public function __construct(
        private string $toEmail,
        private string $blogName,
        private string $postTitle,
        private string $blogSlug,
        private string $postSlug,
        private string $unsubscribeToken
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('New on '.$this->blogName.': '.$this->postTitle)
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'post_title' => $this->postTitle,
                'post_url' => $this->postUrl(),
                'unsubscribe_url' => $this->unsubscribeUrl(),
            ]);
    }

    private function appUrl(): string
    {
        return rtrim((string) (env('APP_URL', 'http://localhost')), '/');
    }

    private function postUrl(): string
    {
        return $this->appUrl().'/blog/'.rawurlencode($this->blogSlug).'/'.rawurlencode($this->postSlug);
    }

    private function unsubscribeUrl(): string
    {
        return $this->appUrl().'/subscriptions/unsubscribe/'.rawurlencode($this->unsubscribeToken);
    }
}
