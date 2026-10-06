<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells an author a post of theirs has passed a number of views.
 */
class InsightsMilestoneMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $postTitle,
        private int $threshold,
        private int $blogId,
        private int $postId,
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('"'.$this->postTitle.'" passed '.number_format($this->threshold).' views')
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'views' => number_format($this->threshold),
                'insights_url' => $this->analyticsUrl(),
            ]);
    }

    private function analyticsUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/')
            .'/dashboard/blog/'.$this->blogId.'/insights/posts/'.$this->postId.'?range=12m';
    }
}
