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

    /** Goals in the order the Goals page lists them; each has a counted phrase under digest.goals. */
    private const GOALS = ['subscribe', 'comment', 'like', 'save', 'share', 'signup'];

    /**
     * @param  list<array{name: string, id: int, slug: string, from: string, to: string, views: int, previous: int,
     *     visitors: int, source: ?string, posts: list<array{title: string, views: int}>, goals: array<string, int>}>  $blogs
     */
    public function __construct(
        private string $toEmail,
        private string $weekStart,
        private string $weekEnd,
        private array $blogs,
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject($this->t('subjects.InsightsDigestMail', ['app_name' => (string) env('APP_NAME', 'Lexicon'), 'week' => $this->weekLabel()]))
            ->fromTemplate([
                'week_label' => $this->weekLabel(),
                'sections' => $this->sections(),
            ]);
    }

    /**
     * "Sep 28 to Oct 4, 2026", as the reader's language writes dates. Dates
     * that do not parse are shown as given.
     */
    private function weekLabel(): string
    {
        try {
            $utc = new \DateTimeZone('UTC');

            return $this->t('digest.week', [
                'from' => $this->date(new \DateTimeImmutable($this->weekStart, $utc), 'MMMd'),
                'to' => $this->date(new \DateTimeImmutable($this->weekEnd, $utc), 'yMMMd'),
            ]);
        } catch (\Exception) {
            return $this->weekStart.' – '.$this->weekEnd;
        }
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
    private function lines(array $blog): array
    {
        $lines = [
            'overview' => $this->t('digest.overview', ['views' => $blog['views'], 'visitors' => $blog['visitors'], 'change' => $this->change($blog)]),
        ];

        if ($blog['source'] !== null) {
            $lines['acquisition'] = $this->t('digest.source', ['source' => $blog['source']]);
        }

        $goals = [];
        foreach (self::GOALS as $goal) {
            $count = $blog['goals'][$goal] ?? 0;
            if ($count > 0) {
                $goals[] = $this->t('digest.goals.'.$goal, ['count' => $count]);
            }
        }

        if ($goals !== []) {
            $lines['goals'] = $this->t('digest.goals_line', ['goals' => implode($this->t('digest.list_separator'), $goals)]);
        }

        return $lines;
    }

    /**
     * @param  array{views: int, previous: int}  $blog
     */
    private function change(array $blog): string
    {
        if ($blog['previous'] === 0) {
            return $this->t('digest.change_none');
        }

        $percent = (int) round(($blog['views'] - $blog['previous']) / $blog['previous'] * 100);
        $formatted = (string) (new \NumberFormatter($this->getLocale(), \NumberFormatter::PERCENT))->format(abs($percent) / 100);

        return match (true) {
            $percent > 0 => $this->t('digest.change_up', ['percent' => $formatted]),
            $percent < 0 => $this->t('digest.change_down', ['percent' => $formatted]),
            default => $this->t('digest.change_same'),
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
            $lines = $this->lines($blog);

            $parts = [
                $this->component('section-heading', [
                    'section_title' => $blog['name'],
                    'section_url' => $this->appUrl().'/blog/'.rawurlencode($blog['slug']),
                ]),
                $this->stat($blog, 'overview', $this->t('digest.labels.overview'), $lines['overview']),
            ];

            if ($blog['posts'] !== []) {
                $items = [HtmlFragment::fromText($this->t('digest.most_read'))];

                foreach ($blog['posts'] as $post) {
                    $items[] = $this->component('list-item', ['item' => $this->t('digest.post_views', ['title' => $post['title'], 'count' => $post['views']])]);
                }

                $parts[] = $this->stat($blog, 'content', $this->t('digest.labels.content'), HtmlFragment::join($items));
            }

            foreach (['acquisition', 'goals'] as $page) {
                if (isset($lines[$page])) {
                    $parts[] = $this->stat($blog, $page, $this->t('digest.labels.'.$page), $lines[$page]);
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
