<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Templates\HtmlFragment;

/**
 * The weekly Insights summary for an owner, one section per blog, told in the
 * order of the Insights pages, each part linking to its page for that week.
 *
 * The section is the email's repeated section, filled once per blog. Counts
 * are numbers next to labels in the wording rather than counted phrases, so
 * no language needs plural rules in the data.
 */
class InsightsDigestMail extends Mailable
{
    /** One per owner, all sent the same morning, so throughput matters more than speed. */
    protected string $tier = self::TIER_BULK;

    /** Each goal's placeholder in the section, by the goal it counts. */
    private const GOALS = [
        'subscribe' => 'subscriptions',
        'comment' => 'comments',
        'like' => 'likes',
        'save' => 'saves',
        'share' => 'shares',
        'signup' => 'signups',
    ];

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
            ->fromTemplate([
                'week_start' => $this->day($this->weekStart, 'MMMd'),
                'week_end' => $this->day($this->weekEnd, 'yMMMd'),
                'blogs' => $this->repeat(array_map($this->section(...), $this->blogs)),
            ]);
    }

    /**
     * One blog's values for the repeated section.
     *
     * @param  array{name: string, id: int, slug: string, from: string, to: string, views: int, previous: int,
     *     visitors: int, source: ?string, posts: list<array{title: string, views: int}>, goals: array<string, int>}  $blog
     * @return array<string, mixed>
     */
    private function section(array $blog): array
    {
        $values = [
            'blog_name' => $blog['name'],
            'blog_url' => $this->url('/blog/'.rawurlencode($blog['slug'])),
            'views' => $this->number($blog['views']),
            'visitors' => $this->number($blog['visitors']),
            'change' => $this->change($blog['views'], $blog['previous']),
            // A dash rather than words when there is nothing to name.
            'top_source' => $blog['source'] ?? '–',
            'most_read' => $this->mostRead($blog['posts']),
            'overview_url' => $this->pageUrl($blog, 'overview'),
            'content_url' => $this->pageUrl($blog, 'content'),
            'acquisition_url' => $this->pageUrl($blog, 'acquisition'),
            'goals_url' => $this->pageUrl($blog, 'goals'),
        ];

        foreach (self::GOALS as $goal => $placeholder) {
            $values[$placeholder] = $this->number($blog['goals'][$goal] ?? 0);
        }

        return $values;
    }

    /**
     * Views against the week before as a signed percentage, or a dash with nothing to compare.
     */
    private function change(int $views, int $previous): string
    {
        if ($previous === 0) {
            return '–';
        }

        $formatter = new \NumberFormatter($this->getLocale(), \NumberFormatter::PERCENT);
        $formatter->setTextAttribute(\NumberFormatter::POSITIVE_PREFIX, '+');

        return (string) $formatter->format(round(($views - $previous) / $previous, 2));
    }

    /**
     * The week's most read posts, one per line with its views.
     *
     * @param  list<array{title: string, views: int}>  $posts
     */
    private function mostRead(array $posts): HtmlFragment
    {
        if ($posts === []) {
            return HtmlFragment::fromText('–');
        }

        return HtmlFragment::fromText(implode("\n", array_map(
            fn (array $post): string => $post['title'].' · '.$this->number($post['views']),
            $posts
        )));
    }

    /**
     * A Y-m-d day as the reader's language writes it.
     */
    private function day(string $date, string $skeleton): string
    {
        try {
            return $this->date(new \DateTimeImmutable($date, new \DateTimeZone('UTC')), $skeleton);
        } catch (\Exception) {
            return $date;
        }
    }

    /**
     * @param  array{id: int, from: string, to: string}  $blog
     */
    private function pageUrl(array $blog, string $page): string
    {
        return $this->url('/dashboard/blog/'.$blog['id'].'/insights/'.$page.'?'
            .http_build_query(['range' => 'custom', 'from' => $blog['from'], 'to' => $blog['to']]));
    }
}
