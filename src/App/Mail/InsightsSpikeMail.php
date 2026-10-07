<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a blog owner the blog is getting far more readers than usual today.
 */
class InsightsSpikeMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $blogName,
        private int $blogId,
        private int $views,
        private float $usual,
        private ?string $topSource,
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'views' => $this->number($this->views),
                'usual_views' => $this->number(round($this->usual)),
                // A dash rather than words when there is no source to name.
                'top_source' => $this->topSource ?? '–',
                'insights_url' => $this->url('/dashboard/blog/'.$this->blogId.'/insights?range=today'),
            ]);
    }
}
