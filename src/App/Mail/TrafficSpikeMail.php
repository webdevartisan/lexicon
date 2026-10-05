<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a blog owner the blog is getting far more readers than usual today.
 */
class TrafficSpikeMail extends Mailable
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
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function trafficUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/').'/dashboard/blog/'.$this->blogId.'/analytics/traffic?range=today';
    }

    private function summary(): string
    {
        $line = number_format($this->views).' views so far today, against about '.number_format($this->usual).' on a usual day.';

        return $this->topSource === null ? $line : $line.' Most of them came from '.$this->topSource.'.';
    }

    private function buildHtmlBody(): string
    {
        $blog = htmlspecialchars($this->blogName);
        $summary = htmlspecialchars($this->summary());
        $url = htmlspecialchars($this->trafficUrl());

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>{$blog} is busy today</h2>
                <p>{$summary}</p>
                <p><a href="{$url}" style="display:inline-block;padding:10px 18px;background:#1f2937;color:#fff;text-decoration:none;border-radius:4px;">See where readers are coming from</a></p>
                <p style="font-size:12px;color:#777;">You get this at most once a day per blog. Switch it off in your notification settings.</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "{$this->blogName} is busy today.\n\n{$this->summary()}\n\nSee where readers are coming from: {$this->trafficUrl()}\n";
    }
}
