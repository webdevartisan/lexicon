<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Templates\HtmlFragment;

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
            ->fromTemplate([
                'week_label' => $this->weekLabel,
                'sections' => $this->sections(),
            ]);
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

    /**
     * One section per blog, each part a block of its own linking to its page.
     *
     * The order and which parts appear are decided here; how a section heading
     * or a statistic looks belongs to the blocks, editable in the control panel.
     */
    private function sections(): HtmlFragment
    {
        $sections = [];

        foreach ($this->blogs as $blog) {
            $lines = self::lines($blog);

            $parts = [
                $this->component('section-heading', [
                    'section_title' => $blog['name'],
                    'section_url' => $this->appUrl().'/blog/'.rawurlencode($blog['slug']),
                ]),
                $this->stat($blog, 'overview', 'Overview', $lines['overview']),
            ];

            if ($blog['posts'] !== []) {
                $items = [HtmlFragment::fromText('Most read:')];

                foreach ($blog['posts'] as $post) {
                    $items[] = $this->component('list-item', ['item' => $post['title'].': '.number_format($post['views']).' views']);
                }

                $parts[] = $this->stat($blog, 'content', 'Content', HtmlFragment::join($items));
            }

            foreach (['acquisition' => 'Acquisition', 'goals' => 'Goals'] as $page => $title) {
                if (isset($lines[$page])) {
                    $parts[] = $this->stat($blog, $page, $title, $lines[$page]);
                }
            }

            $sections[] = HtmlFragment::join($parts);
        }

        return HtmlFragment::join($sections, "\n\n");
    }

    /**
     * @param  array{id: int, from: string, to: string}  $blog
     */
    private function stat(array $blog, string $page, string $label, string|HtmlFragment $text): HtmlFragment
    {
        return $this->component('stat-line', [
            'stat_label' => $label,
            'stat_text' => $text,
            'stat_url' => $this->pageUrl($blog, $page),
        ]);
    }
}
