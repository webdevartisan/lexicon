<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Services\LocaleRegistry;

/**
 * Turns what HomeService gathered into what the Home page prints: the stat
 * cards with their context lines, the activity rows, the link lists and the
 * weekly publishing strip. The view is left with markup only.
 */
final class HomePresenter
{
    /**
     * @param  callable(string, array<string, mixed>=): string  $t  Translator for the page's language
     */
    public function __construct(
        private $t,
        private string $locale,
        private LocaleRegistry $locales,
    ) {}

    /**
     * @param  array<string, mixed>  $overview  From HomeService::for()
     * @return array{
     *     summary: string, stats: list<array<string, mixed>>, activity: list<array<string, string>>|null,
     *     drafts: list<array<string, string>>|null, reviewItems: list<array<string, string>>|null,
     *     attention: list<array<string, string>>|null, attentionHasDrafts: bool,
     *     topRead: array<string, string>|null, rhythm: array<string, mixed>|null
     * }
     */
    public function present(array $overview, int $blogId): array
    {
        $myWork = $overview['myWork'];
        $review = $overview['review'];

        return [
            'summary' => $this->summary($overview),
            'stats' => $this->stats($overview, $blogId),
            'activity' => $this->activity($overview['activity'], $blogId),
            'drafts' => $myWork === null ? null : $this->draftItems($myWork['drafts']),
            'reviewItems' => $review === null ? null : $this->reviewItems($review['items']),
            'attention' => $this->attention($overview, $blogId),
            'attentionHasDrafts' => ($overview['blogPosts']['needsAttention'] ?? []) !== [],
            'topRead' => $this->topRead($overview['analytics'], $blogId),
            'rhythm' => $this->rhythm($overview['rhythm'], $overview['upNext']),
        ];
    }

    /**
     * Drafts and scheduled posts in one line, e.g. "3 drafts · 1 scheduled".
     *
     * @param  array<string, mixed>  $overview
     */
    private function summary(array $overview): string
    {
        $counts = ($overview['blogPosts'] ?? $overview['myWork'])['counts'] ?? null;
        $parts = [];

        if ($counts !== null && $counts['draft'] > 0) {
            $parts[] = translate_count('dashboard.home.drafts', (int) $counts['draft']);
        }
        if ($counts !== null && $counts['scheduled'] > 0) {
            $parts[] = $this->tr('dashboard.home.scheduled', ['count' => (int) $counts['scheduled']]);
        }

        return implode(' · ', $parts);
    }

    /**
     * One card per section the person's permissions opened.
     *
     * @param  array<string, mixed>  $overview
     * @return list<array<string, mixed>>
     */
    private function stats(array $overview, int $blogId): array
    {
        $published = $this->publishedContext($overview['publishedRecently']);
        $stats = [];

        if ($overview['blogPosts'] !== null) {
            $count = $overview['blogPosts']['counts']['published'];
            $stats[] = $this->stat('dashboard.stats.published', $count, '/dashboard/post?status=published', $published);
        }
        if ($overview['myWork'] !== null) {
            $stats = array_merge($stats, $this->writerStats($overview, $published));
        }
        if ($overview['review'] !== null && $overview['blogPosts'] === null) {
            $stats = array_merge($stats, $this->reviewerStats($overview['review'], $blogId));
        }
        if ($overview['comments'] !== null) {
            $stats[] = $this->commentsStat($overview['comments'], $blogId);
        }
        if ($overview['subscribers'] !== null) {
            $stats[] = $this->subscribersStat($overview['subscribers'], $overview['newSubscribers'], $blogId);
        }
        if (!empty($overview['analytics'])) {
            $stats[] = $this->viewsStat($overview['analytics'], $blogId);
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $overview
     * @param  array{0: string, 1: string}|null  $published  Context line of the published card
     * @return list<array<string, mixed>>
     */
    private function writerStats(array $overview, ?array $published): array
    {
        $counts = $overview['myWork']['counts'];
        $posts = '/dashboard/post?status=';
        $stats = [
            $this->stat('dashboard.overview.myPublished', $counts['published'], $posts.'published', $published),
            $this->stat('dashboard.overview.myDrafts', $counts['draft'], $posts.'draft'),
        ];

        if ($overview['workflowEnabled']) {
            $stats[] = $this->stat('dashboard.overview.submitted', $counts['pending'], $posts.'pending');
            $stats[] = $this->stat(
                'dashboard.overview.needsRevision',
                $counts['needs_changes'],
                $posts.'needs_changes',
            );
        }

        return $stats;
    }

    /**
     * @param  array{inReview: int, needsChanges: int}  $review
     * @return list<array<string, mixed>>
     */
    private function reviewerStats(array $review, int $blogId): array
    {
        $queue = '/dashboard/blog/'.$blogId.'/review-queue';

        return [
            $this->stat('dashboard.overview.awaitingReview', $review['inReview'], $queue),
            $this->stat('dashboard.overview.needsRevisit', $review['needsChanges'], $queue),
        ];
    }

    /**
     * Waiting comments come first because they ask for action; otherwise the week's new ones.
     *
     * @param  array{approved: int, pending: int, recent: int}  $comments
     * @return array<string, mixed>
     */
    private function commentsStat(array $comments, int $blogId): array
    {
        $pending = (int) $comments['pending'];

        if ($pending > 0) {
            $context = $this->context('warn', 'dashboard.home.context.commentsWaiting', $pending);
        } elseif ($comments['recent'] > 0) {
            $context = $this->context('good', 'dashboard.home.context.commentsWeek', (int) $comments['recent']);
        } else {
            $context = $this->context('muted', 'dashboard.home.context.commentsNone');
        }

        $href = '/dashboard/blog/'.$blogId.'/comments'.($pending > 0 ? '?status=pending' : '');

        return $this->stat('dashboard.stats.comments', $comments['approved'], $href, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscribersStat(int $total, int $newThisWeek, int $blogId): array
    {
        $context = $newThisWeek > 0
            ? $this->context('good', 'dashboard.home.context.subscribersWeek', $newThisWeek)
            : $this->context('muted', 'dashboard.home.context.subscribersNone');

        return $this->stat('dashboard.stats.subscribers', $total, '/dashboard/blog/'.$blogId.'/subscribers', $context);
    }

    /**
     * Last week's views against the week before. The badge's long wording becomes the line's tooltip.
     *
     * @param  array<string, mixed>  $analytics  From HomeService::traffic()
     * @return array<string, mixed>
     */
    private function viewsStat(array $analytics, int $blogId): array
    {
        $present = new AnalyticsPresenter($this->locale, $this->t, $this->locales, 'blog');
        $views = $analytics['metrics']['views'];
        $context = $this->context('muted', 'dashboard.home.context.viewsNone');
        $title = '';

        if ((int) $views['value'] > 0) {
            $previous = $analytics['range']->previous();
            $period = $this->tr('analytics.range.span', [
                'from' => $present->date($previous->fromDate()),
                'to' => $present->date($previous->toDate()),
            ]);
            $change = $present->change($views['change'], $present->number((int) $views['previous']), $period);
            $context = null;

            if ($change['direction'] !== 'none') {
                $tone = $change['tone'] === 'neutral' ? 'muted' : $change['tone'];
                $context = [$tone, $this->tr('dashboard.home.context.viewsCompare', ['change' => $change['short']])];
                $title = $change['label'];
            }
        }

        return $this->stat(
            'dashboard.home.views',
            $present->number((int) $views['value']),
            '/dashboard/blog/'.$blogId.'/insights?range=7d',
            $context,
            $title,
        );
    }

    /**
     * @return array{0: string, 1: string}|null Null when the person sees no publishing figures
     */
    private function publishedContext(?int $publishedRecently): ?array
    {
        if ($publishedRecently === null) {
            return null;
        }

        return $publishedRecently > 0
            ? $this->context('good', 'dashboard.home.context.publishedMonth', $publishedRecently)
            : $this->context('muted', 'dashboard.home.context.publishedNone');
    }

    /**
     * What needs a decision first: moderation and review counts, then the drafts that have waited longest.
     *
     * @param  array<string, mixed>  $overview
     * @return list<array<string, string>>|null Null for those who do not manage every post
     */
    private function attention(array $overview, int $blogId): ?array
    {
        if ($overview['blogPosts'] === null) {
            return null;
        }

        $comments = $overview['comments'];
        $review = $overview['review'];
        $queue = '/dashboard/blog/'.$blogId.'/review-queue';
        $items = [];

        if ($comments !== null && $comments['pending'] > 0) {
            $pendingComments = '/dashboard/blog/'.$blogId.'/comments?status=pending';
            $items[] = $this->countItem($pendingComments, 'comments', $comments['pending']);
        }
        if ($review !== null && $review['inReview'] > 0) {
            $items[] = $this->countItem($queue, 'review', $review['inReview']);
        }
        if ($review !== null && $review['needsChanges'] > 0) {
            $items[] = $this->countItem($queue, 'revisit', $review['needsChanges']);
        }

        return array_merge($items, $this->draftItems($overview['blogPosts']['needsAttention']));
    }

    /**
     * @return array<string, string>
     */
    private function countItem(string $href, string $key, int $count): array
    {
        return [
            'href' => $href,
            'text' => translate_count('dashboard.home.attention.'.$key, $count),
            'meta' => '',
        ];
    }

    /**
     * Posts that open in the editor, each with its status and last change.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, string>>
     */
    private function draftItems(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $status = $this->tr('components.postCard.status.'.($row['status'] ?? 'draft'));
            $changed = local_datetime($row['updated_at'] ?? $row['created_at'] ?? null, 'M j');

            $items[] = [
                'href' => '/dashboard/post/'.(int) $row['id'].'/edit',
                'text' => (string) $row['title'],
                'meta' => $status.' · '.$changed,
            ];
        }

        return $items;
    }

    /**
     * Posts that open on their review page, each with its state and reviewer.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, string>>
     */
    private function reviewItems(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $state = $this->tr(match ($row['workflow_state']) {
                'needs_changes' => 'dashboard.overview.stateNeedsChanges',
                'approved' => 'dashboard.reviewQueue.states.approved',
                default => 'dashboard.overview.stateInReview',
            });
            $reviewer = empty($row['reviewer_handle'])
                ? $this->tr('dashboard.overview.unassigned')
                : '@'.$row['reviewer_handle'];

            $items[] = [
                'href' => '/dashboard/post/'.(int) $row['id'].'/review',
                'text' => (string) $row['title'],
                'meta' => $state.' · '.$reviewer,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>|null  $events  From BlogActivityModel::recent()
     * @return list<array<string, string>>|null
     */
    private function activity(?array $events, int $blogId): ?array
    {
        if ($events === null) {
            return null;
        }

        $rows = [];
        foreach ($events as $event) {
            $rows[] = $this->activityRow($event, $blogId) + ['when' => relative_time($event['happened_at'], true)];
        }

        return $rows;
    }

    /**
     * Icon, tone, sentence and link for one kind of event.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, string>
     */
    private function activityRow(array $event, int $blogId): array
    {
        $actor = $event['actor'] === null ? null : '@'.$event['actor'];
        $post = (string) $event['subject'];
        $pending = $event['detail'] === 'pending';

        return match ($event['kind']) {
            'comment' => [
                'icon' => 'message-square',
                'tone' => $pending ? 'amber' : 'sky',
                'text' => $actor === null
                    ? $this->tr('dashboard.home.activity.commentGuest', ['post' => $post])
                    : $this->tr('dashboard.home.activity.comment', ['actor' => $actor, 'post' => $post]),
                'meta' => $pending ? $this->tr('dashboard.home.activity.commentPending') : '',
                'href' => '/dashboard/blog/'.$blogId.'/comments',
            ],
            'published' => [
                'icon' => 'check-circle-2',
                'tone' => 'green',
                'text' => $this->tr('dashboard.home.activity.published', ['post' => $post]),
                'meta' => $actor === null ? '' : $this->tr('dashboard.home.activity.publishedBy', ['actor' => $actor]),
                'href' => '/dashboard/post/'.(int) $event['post_id'].'/edit',
            ],
            'subscriber' => [
                'icon' => 'mail',
                'tone' => 'violet',
                'text' => $this->tr('dashboard.home.activity.subscriber'),
                'meta' => $this->tr('dashboard.home.activity.subscriberDetail'),
                'href' => '/dashboard/blog/'.$blogId.'/subscribers',
            ],
            default => [
                'icon' => 'user-plus',
                'tone' => 'slate',
                'text' => $this->tr('dashboard.home.activity.member', ['actor' => (string) $actor]),
                'meta' => (string) $event['detail'],
                'href' => '/dashboard/blog/'.$blogId.'/team',
            ],
        };
    }

    /**
     * @param  array<string, mixed>|null  $analytics
     * @return array<string, string>|null Null when no post was read in the range
     */
    private function topRead(?array $analytics, int $blogId): ?array
    {
        $post = $analytics['topPost'] ?? null;

        if (($post['title'] ?? null) === null) {
            return null;
        }

        return [
            'href' => '/dashboard/blog/'.$blogId.'/insights/posts/'.(int) $post['post_id'].'?range=7d',
            'text' => (string) $post['title'],
            'meta' => translate_count('dashboard.home.mostRead.views', (int) $post['views']),
        ];
    }

    /**
     * Bar heights as a share of the busiest week, plus the next scheduled post.
     *
     * @param  list<int>|null  $weeks  Posts per week, oldest first
     * @param  array<string, mixed>|null  $upNext
     * @return array{bars: list<array<string, mixed>>, total: string, next: array<string, string>|null}|null
     */
    private function rhythm(?array $weeks, ?array $upNext): ?array
    {
        if ($weeks === null) {
            return null;
        }

        $peak = max(1, ...$weeks);
        $last = array_key_last($weeks);
        $bars = [];

        foreach ($weeks as $week => $count) {
            $bars[] = [
                'height' => (int) round($count / $peak * 100),
                'title' => translate_count('dashboard.home.rhythm.bar', (int) $count),
                'current' => $week === $last,
            ];
        }

        return [
            'bars' => $bars,
            'total' => translate_count('dashboard.home.rhythm.total', array_sum($weeks)),
            'next' => $upNext === null ? null : [
                'href' => '/dashboard/post/'.(int) $upNext['id'].'/edit',
                'text' => $this->tr('dashboard.home.rhythm.upNext', [
                    'post' => (string) $upNext['title'],
                    'when' => relative_time($upNext['published_at']),
                ]),
            ],
        ];
    }

    /**
     * @param  array{0: string, 1: string}|null  $context  Tone and text of the line under the figure
     * @return array<string, mixed>
     */
    private function stat(
        string $labelKey,
        int|string $value,
        string $href,
        ?array $context = null,
        string $title = '',
    ): array {
        return [
            'label' => $this->tr($labelKey),
            'value' => (string) $value,
            'href' => $href,
            'context' => $context,
            'title' => $title,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function context(string $tone, string $key, ?int $count = null): array
    {
        return [$tone, $this->tr($key, $count === null ? [] : ['count' => $count])];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function tr(string $key, array $params = []): string
    {
        return ($this->t)($key, $params);
    }
}
