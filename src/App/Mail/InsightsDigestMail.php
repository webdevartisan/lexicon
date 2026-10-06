<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * The weekly Insights summary for an owner, one section per blog, told in the
 * order of the Insights pages, each part linking to its page for that week.
 */
class InsightsDigestMail extends Mailable
{
    /** One per owner, all sent the same morning, so throughput matters more than speed. */
    protected string $tier = self::TIER_BULK;

    /** Goal => [one, many], in the order the Goals page lists them. */
    private const GOALS = [
        'subscribe' => ['subscription', 'subscriptions'],
        'comment' => ['comment', 'comments'],
        'like' => ['like', 'likes'],
        'save' => ['save', 'saves'],
        'share' => ['share', 'shares'],
        'signup' => ['sign-up', 'sign-ups'],
    ];

    /**
     * @param  list<array{name: string, id: int, slug: string, from: string, to: string, views: int, previous: int,
     *     visitors: int, source: ?string, posts: list<array{title: string, views: int}>, goals: array<string, int>}>  $blogs
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
     * @param  array{id: int, from: string, to: string}  $blog
     */
    private function pageUrl(array $blog, string $page): string
    {
        return $this->appUrl().'/dashboard/blog/'.$blog['id'].'/insights/'.$page.'?'
            .http_build_query(['range' => 'custom', 'from' => $blog['from'], 'to' => $blog['to']]);
    }

    /**
     * What each page has to say about the week, page => line. Pages with nothing to say are left out.
     *
     * @param  array{views: int, previous: int, visitors: int, source: ?string, goals: array<string, int>}  $blog
     * @return array<string, string>
     */
    private static function lines(array $blog): array
    {
        $lines = [
            'overview' => number_format($blog['views']).' views from '.number_format($blog['visitors'])
                .' daily visitors, '.self::change($blog).'.',
        ];

        if ($blog['source'] !== null) {
            $lines['acquisition'] = "Top source: {$blog['source']}.";
        }

        $goals = [];
        foreach (self::GOALS as $goal => [$one, $many]) {
            $count = $blog['goals'][$goal] ?? 0;
            if ($count > 0) {
                $goals[] = number_format($count).' '.($count === 1 ? $one : $many);
            }
        }

        if ($goals !== []) {
            $lines['goals'] = ucfirst(implode(', ', $goals)).'.';
        }

        return $lines;
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
            $lines = self::lines($blog);
            $blogUrl = e($this->appUrl().'/blog/'.rawurlencode($blog['slug']));
            $sections .= '<h3 style="margin-bottom:4px;"><a href="'.$blogUrl.'" style="color:#1f2937;">'.e($blog['name']).'</a></h3>';
            $sections .= $this->htmlPart($blog, 'overview', 'Overview', e($lines['overview']));

            if ($blog['posts'] !== []) {
                $posts = '';
                foreach ($blog['posts'] as $post) {
                    $posts .= '<li>'.e($post['title']).': '.number_format($post['views']).' views</li>';
                }
                $sections .= $this->htmlPart($blog, 'content', 'Content', 'Most read:<ul style="margin:4px 0;">'.$posts.'</ul>');
            }

            foreach (['acquisition' => 'Acquisition', 'goals' => 'Goals'] as $page => $title) {
                if (isset($lines[$page])) {
                    $sections .= $this->htmlPart($blog, $page, $title, e($lines[$page]));
                }
            }
        }

        $week = e($this->weekLabel);

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

    /**
     * @param  array{id: int, from: string, to: string}  $blog
     * @param  string  $body  Already escaped
     */
    private function htmlPart(array $blog, string $page, string $title, string $body): string
    {
        $url = e($this->pageUrl($blog, $page));

        return '<div style="margin:8px 0;"><strong>'.$title.'</strong> · <a href="'.$url.'">Open</a><br>'.$body.'</div>';
    }

    private function buildTextBody(): string
    {
        $text = "Your week: {$this->weekLabel}\n";

        foreach ($this->blogs as $blog) {
            $lines = self::lines($blog);
            $text .= "\n{$blog['name']}\n\nOverview: {$lines['overview']}\n".$this->pageUrl($blog, 'overview')."\n";

            if ($blog['posts'] !== []) {
                $text .= "\nContent, most read:\n";
                foreach ($blog['posts'] as $post) {
                    $text .= "- {$post['title']}: ".number_format($post['views'])." views\n";
                }
                $text .= $this->pageUrl($blog, 'content')."\n";
            }

            foreach (['acquisition' => 'Acquisition', 'goals' => 'Goals'] as $page => $title) {
                if (isset($lines[$page])) {
                    $text .= "\n{$title}: {$lines[$page]}\n".$this->pageUrl($blog, $page)."\n";
                }
            }
        }

        return $text;
    }
}
