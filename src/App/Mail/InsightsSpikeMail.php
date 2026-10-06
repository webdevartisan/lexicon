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
            ->subject($this->blogName.' has far more readers than usual today')
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'views' => number_format($this->views),
                'usual_views' => number_format($this->usual),
                'top_source' => (string) $this->topSource,
                'summary' => $this->summary(),
                'insights_url' => $this->analyticsUrl(),
            ]);
    }

    private function analyticsUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/').'/dashboard/blog/'.$this->blogId.'/insights?range=today';
    }

    private function summary(): string
    {
        $line = number_format($this->views).' views so far today, against about '.number_format($this->usual).' on a usual day.';

        return $this->topSource === null ? $line : $line.' Most of them came from '.$this->topSource.'.';
    }
}
