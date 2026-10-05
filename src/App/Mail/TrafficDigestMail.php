<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * The weekly traffic summary for an owner, one section per blog.
 */
class TrafficDigestMail extends Mailable
{
    /** One per owner, all sent the same morning, so throughput matters more than speed. */
    protected string $tier = self::TIER_BULK;

    /**
     * @param  list<array{name: string, id: int, slug: string, views: int, previous: int, visitors: int,
     *     source: ?string, posts: list<array{title: string, views: int}>}>  $blogs
     */
    public function __construct(
        private string $toEmail,
        private string $weekLabel,
        private array $blogs,
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('Your week on Lexicon: '.$this->weekLabel)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function appUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/');
    }

    /**
     * @param  array{views: int, previous: int}  $blog
     */
    private static function change(array $blog): string
    {
        if ($blog['previous'] === 0) {
            return 'nothing to compare with yet';
        }

        $percent = (int) round(($blog['views'] - $blog['previous']) / $blog['previous'] * 100);

        return match (true) {
            $percent > 0 => "up {$percent}% on the week before",
            $percent < 0 => 'down '.abs($percent).'% on the week before',
            default => 'the same as the week before',
        };
    }

    private function buildHtmlBody(): string
    {
        $sections = '';

        foreach ($this->blogs as $blog) {
            $name = htmlspecialchars($blog['name']);
            $blogUrl = htmlspecialchars($this->appUrl().'/blog/'.rawurlencode($blog['slug']));
            $trafficUrl = htmlspecialchars($this->appUrl().'/dashboard/blog/'.$blog['id'].'/analytics/traffic?range=7d');
            $views = number_format($blog['views']);
            $visitors = number_format($blog['visitors']);
            $change = htmlspecialchars(self::change($blog));
            $source = $blog['source'] === null ? '' : '<p>Most readers came from '.htmlspecialchars($blog['source']).'.</p>';
            $posts = '';

            foreach ($blog['posts'] as $post) {
                $posts .= '<li>'.htmlspecialchars($post['title']).': '.number_format($post['views']).' views</li>';
            }

            $posts = $posts === '' ? '' : "<p>Most read:</p><ul>{$posts}</ul>";

            $sections .= <<<HTML
                <h3 style="margin-bottom:4px;"><a href="{$blogUrl}" style="color:#1f2937;">{$name}</a></h3>
                <p style="margin-top:0;">{$views} views from {$visitors} daily visitors, {$change}.</p>
                {$source}
                {$posts}
                <p><a href="{$trafficUrl}">Open the Traffic page</a></p>
            HTML;
        }

        $week = htmlspecialchars($this->weekLabel);

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>Your week: {$week}</h2>
                {$sections}
                <p style="font-size:12px;color:#777;">Sent on Mondays. Switch it off in your notification settings.</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        $text = "Your week: {$this->weekLabel}\n";

        foreach ($this->blogs as $blog) {
            $text .= "\n{$blog['name']}\n".number_format($blog['views']).' views from '.number_format($blog['visitors'])
                .' daily visitors, '.self::change($blog).".\n";

            if ($blog['source'] !== null) {
                $text .= "Most readers came from {$blog['source']}.\n";
            }

            foreach ($blog['posts'] as $post) {
                $text .= "- {$post['title']}: ".number_format($post['views'])." views\n";
            }

            $text .= 'Traffic: '.$this->appUrl().'/dashboard/blog/'.$blog['id']."/analytics/traffic?range=7d\n";
        }

        return $text;
    }
}
