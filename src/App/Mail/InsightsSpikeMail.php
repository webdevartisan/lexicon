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
            ->subject($this->t('subjects.InsightsSpikeMail', ['blog_name' => $this->blogName]))
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'views' => $this->number($this->views),
                'usual_views' => $this->number(round($this->usual)),
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
        $line = $this->t('phrases.spike_summary', ['count' => $this->views, 'usual' => $this->number(round($this->usual))]);

        return $this->topSource === null ? $line : $line.' '.$this->t('phrases.spike_source', ['source' => $this->topSource]);
    }
}
