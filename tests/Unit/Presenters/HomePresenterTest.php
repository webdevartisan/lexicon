<?php

declare(strict_types=1);

use App\Presenters\HomePresenter;
use App\Services\LocaleRegistry;
use App\ValueObjects\AnalyticsRange;

/**
 * How what the Home service gathered reads on the page: the cards, their context lines and the lists.
 */
beforeEach(function () {
    $this->presenter = new HomePresenter(
        static function (string $key, array $params = []): string {
            return $key.($params === [] ? '' : ':'.implode(',', $params));
        },
        'en',
        LocaleRegistry::instance(),
    );

    $this->counts = ['published' => 5, 'draft' => 3, 'pending' => 1, 'needs_changes' => 2, 'scheduled' => 1];

    // A person who sees nothing at all; each test opens only the sections it is about
    $this->overview = static function (array $sections = []): array {
        return array_replace([
            'workflowEnabled' => false,
            'canCreate' => true,
            'blogPosts' => null,
            'myWork' => null,
            'comments' => null,
            'subscribers' => null,
            'newSubscribers' => null,
            'review' => null,
            'analytics' => null,
            'publishedRecently' => null,
            'rhythm' => null,
            'upNext' => null,
            'activity' => null,
        ], $sections);
    };

    $this->manager = function (array $sections = []): array {
        return ($this->overview)(array_replace([
            'blogPosts' => ['counts' => $this->counts, 'needsAttention' => []],
        ], $sections));
    };
});

test('a person who may see nothing gets an empty page', function () {
    $home = $this->presenter->present(($this->overview)(), 4);

    expect($home['stats'])->toBe([])
        ->and($home['summary'])->toBe('')
        ->and($home['activity'])->toBeNull()
        ->and($home['drafts'])->toBeNull()
        ->and($home['reviewItems'])->toBeNull()
        ->and($home['attention'])->toBeNull()
        ->and($home['attentionHasDrafts'])->toBeFalse()
        ->and($home['topRead'])->toBeNull()
        ->and($home['rhythm'])->toBeNull();
});

test('the summary counts drafts and scheduled posts and stays silent when there are none', function () {
    $home = $this->presenter->present(($this->manager)(), 4);

    expect($home['summary'])->toBe('3 drafts · dashboard.home.scheduled:1');

    $quiet = $this->presenter->present(
        ($this->manager)(['blogPosts' => ['counts' => ['draft' => 0, 'scheduled' => 0] + $this->counts, 'needsAttention' => []]]),
        4
    );

    expect($quiet['summary'])->toBe('');
});

test('the published card says how many went live this month', function () {
    $busy = $this->presenter->present(($this->manager)(['publishedRecently' => 2]), 4)['stats'][0];
    $none = $this->presenter->present(($this->manager)(['publishedRecently' => 0]), 4)['stats'][0];

    expect($busy['value'])->toBe('5')
        ->and($busy['href'])->toBe('/dashboard/post?status=published')
        ->and($busy['context'])->toBe(['good', 'dashboard.home.context.publishedMonth:2'])
        ->and($none['context'])->toBe(['muted', 'dashboard.home.context.publishedNone']);
});

test('the comments card asks for action first, then shows the week', function (int $pending, int $recent, string $tone, string $key) {
    $overview = ($this->manager)(['comments' => ['approved' => 9, 'pending' => $pending, 'recent' => $recent]]);

    $card = $this->presenter->present($overview, 4)['stats'][1];

    expect($card['value'])->toBe('9')
        ->and($card['context'][0])->toBe($tone)
        ->and($card['context'][1])->toStartWith($key)
        ->and($card['href'])->toBe('/dashboard/blog/4/comments'.($pending > 0 ? '?status=pending' : ''));
})->with([
    'waiting' => [2, 5, 'warn', 'dashboard.home.context.commentsWaiting'],
    'new this week' => [0, 5, 'good', 'dashboard.home.context.commentsWeek'],
    'quiet' => [0, 0, 'muted', 'dashboard.home.context.commentsNone'],
]);

test('the subscribers card shows the week or says it was quiet', function () {
    $growing = $this->presenter->present(($this->manager)(['subscribers' => 40, 'newSubscribers' => 3]), 4)['stats'][1];
    $still = $this->presenter->present(($this->manager)(['subscribers' => 40, 'newSubscribers' => 0]), 4)['stats'][1];

    expect($growing['value'])->toBe('40')
        ->and($growing['context'])->toBe(['good', 'dashboard.home.context.subscribersWeek:3'])
        ->and($still['context'])->toBe(['muted', 'dashboard.home.context.subscribersNone']);
});

test('a writer sees their own pipeline, with review states only when the blog uses review', function () {
    $mine = ['counts' => $this->counts, 'drafts' => []];

    $without = $this->presenter->present(($this->overview)(['myWork' => $mine]), 4)['stats'];
    $with = $this->presenter->present(($this->overview)(['myWork' => $mine, 'workflowEnabled' => true]), 4)['stats'];

    expect($without)->toHaveCount(2)
        ->and($with)->toHaveCount(4)
        ->and(array_column($with, 'href'))->toBe([
            '/dashboard/post?status=published',
            '/dashboard/post?status=draft',
            '/dashboard/post?status=pending',
            '/dashboard/post?status=needs_changes',
        ]);
});

test('a reviewer gets queue cards, but a manager already sees the queue elsewhere', function () {
    $review = ['inReview' => 2, 'needsChanges' => 1, 'items' => []];

    $reviewer = $this->presenter->present(($this->overview)(['review' => $review]), 4)['stats'];
    $manager = $this->presenter->present(($this->manager)(['review' => $review]), 4)['stats'];

    expect(array_column($reviewer, 'value'))->toBe(['2', '1'])
        ->and(array_unique(array_column($reviewer, 'href')))->toBe(['/dashboard/blog/4/review-queue'])
        ->and($manager)->toHaveCount(1);
});

test('the views card says when nobody has visited yet', function () {
    $analytics = [
        'metrics' => ['views' => ['value' => 0, 'previous' => 0, 'change' => null]],
        'range' => AnalyticsRange::fromQuery(['range' => '7d'], 'UTC'),
        'topPost' => null,
    ];

    $card = $this->presenter->present(($this->overview)(['analytics' => $analytics]), 4)['stats'][0];

    expect($card['value'])->toBe('0')
        ->and($card['href'])->toBe('/dashboard/blog/4/insights?range=7d')
        ->and($card['context'])->toBe(['muted', 'dashboard.home.context.viewsNone']);
});

test('what needs attention lists waiting work first and flags whether drafts follow', function () {
    $overview = ($this->manager)([
        'comments' => ['approved' => 1, 'pending' => 2, 'recent' => 0],
        'review' => ['inReview' => 1, 'needsChanges' => 3, 'items' => []],
        'blogPosts' => [
            'counts' => $this->counts,
            'needsAttention' => [['id' => 8, 'title' => 'Half done', 'status' => 'draft']],
        ],
    ]);

    $home = $this->presenter->present($overview, 4);

    expect(array_column($home['attention'], 'text'))->toBe([
        '2 comments waiting for review',
        '1 post awaiting review',
        '3 posts sent back for changes',
        'Half done',
    ])
        ->and($home['attention'][0]['href'])->toBe('/dashboard/blog/4/comments?status=pending')
        ->and($home['attention'][3]['href'])->toBe('/dashboard/post/8/edit')
        ->and($home['attentionHasDrafts'])->toBeTrue();
});

test('with nothing waiting the attention list is empty, not missing', function () {
    $home = $this->presenter->present(($this->manager)(), 4);

    expect($home['attention'])->toBe([])
        ->and($home['attentionHasDrafts'])->toBeFalse();
});

test('review items open the review page and name the reviewer or say none is assigned', function () {
    $overview = ($this->overview)(['review' => ['inReview' => 2, 'needsChanges' => 0, 'items' => [
        ['id' => 7, 'title' => 'Waiting', 'workflow_state' => 'in_review', 'reviewer_handle' => null],
        ['id' => 9, 'title' => 'Sent back', 'workflow_state' => 'needs_changes', 'reviewer_handle' => 'ada'],
    ]]]);

    $items = $this->presenter->present($overview, 4)['reviewItems'];

    expect($items[0])->toBe([
        'href' => '/dashboard/post/7/review',
        'text' => 'Waiting',
        'meta' => 'dashboard.overview.stateInReview · dashboard.overview.unassigned',
    ])
        ->and($items[1]['meta'])->toBe('dashboard.overview.stateNeedsChanges · @ada');
});

test('a writer\'s drafts open in the editor', function () {
    $overview = ($this->overview)(['myWork' => ['counts' => $this->counts, 'drafts' => [
        ['id' => 3, 'title' => 'First idea', 'status' => 'draft'],
    ]]]);

    $drafts = $this->presenter->present($overview, 4)['drafts'];

    expect($drafts)->toHaveCount(1)
        ->and($drafts[0]['href'])->toBe('/dashboard/post/3/edit')
        ->and($drafts[0]['text'])->toBe('First idea');
});

test('the most read card needs a post with a title', function () {
    $analytics = static function (?array $top): array {
        return ['metrics' => ['views' => ['value' => 0, 'previous' => 0, 'change' => null]],
            'range' => AnalyticsRange::fromQuery(['range' => '7d'], 'UTC'), 'topPost' => $top];
    };

    $none = $this->presenter->present(($this->overview)(['analytics' => $analytics(null)]), 4);
    $some = $this->presenter->present(
        ($this->overview)(['analytics' => $analytics(['post_id' => 12, 'title' => 'Popular', 'views' => 30])]),
        4
    );

    expect($none['topRead'])->toBeNull()
        ->and($some['topRead']['text'])->toBe('Popular')
        ->and($some['topRead']['href'])->toBe('/dashboard/blog/4/insights/posts/12?range=7d')
        ->and($some['topRead']['meta'])->toBe('30 views');
});

test('the publishing strip scales to the busiest week and marks the current one', function () {
    $home = $this->presenter->present(($this->manager)(['rhythm' => [0, 2, 4, 1]]), 4);

    expect(array_column($home['rhythm']['bars'], 'height'))->toBe([0, 50, 100, 25])
        ->and(array_column($home['rhythm']['bars'], 'current'))->toBe([false, false, false, true])
        ->and($home['rhythm']['total'])->toBe('7 posts in the last 12 weeks')
        ->and($home['rhythm']['next'])->toBeNull();
});

test('a strip of quiet weeks does not divide by zero', function () {
    $home = $this->presenter->present(($this->manager)(['rhythm' => [0, 0, 0]]), 4);

    expect(array_column($home['rhythm']['bars'], 'height'))->toBe([0, 0, 0])
        ->and($home['rhythm']['total'])->toBe('0 posts in the last 12 weeks');
});

test('the next scheduled post links to its editor', function () {
    $upNext = ['id' => 21, 'title' => 'Coming soon', 'published_at' => gmdate('Y-m-d H:i:s', time() + 3 * 86400)];

    $home = $this->presenter->present(($this->manager)(['rhythm' => [1], 'upNext' => $upNext]), 4);

    expect($home['rhythm']['next']['href'])->toBe('/dashboard/post/21/edit')
        ->and($home['rhythm']['next']['text'])->toStartWith('dashboard.home.rhythm.upNext:Coming soon,');
});

test('each kind of event gets its own icon, sentence and link', function () {
    $when = gmdate('Y-m-d H:i:s', time() - 3 * 3600);
    $event = static function (array $columns) use ($when): array {
        return array_replace(['happened_at' => $when, 'post_id' => 6, 'subject' => 'A post', 'actor' => null, 'detail' => null], $columns);
    };

    $rows = $this->presenter->present(($this->overview)(['activity' => [
        $event(['kind' => 'comment', 'actor' => 'ada', 'detail' => 'approved']),
        $event(['kind' => 'comment', 'detail' => 'pending']),
        $event(['kind' => 'published', 'actor' => 'grace']),
        $event(['kind' => 'subscriber', 'post_id' => null, 'subject' => null]),
        $event(['kind' => 'member', 'post_id' => null, 'subject' => null, 'actor' => 'linus', 'detail' => 'Editor']),
    ]]), 4)['activity'];

    expect($rows[0]['text'])->toBe('dashboard.home.activity.comment:@ada,A post')
        ->and($rows[0]['tone'])->toBe('sky')
        ->and($rows[1]['text'])->toBe('dashboard.home.activity.commentGuest:A post')
        ->and($rows[1]['tone'])->toBe('amber')
        ->and($rows[1]['meta'])->toBe('dashboard.home.activity.commentPending')
        ->and($rows[2]['href'])->toBe('/dashboard/post/6/edit')
        ->and($rows[2]['meta'])->toBe('dashboard.home.activity.publishedBy:@grace')
        ->and($rows[3]['href'])->toBe('/dashboard/blog/4/subscribers')
        ->and($rows[4]['href'])->toBe('/dashboard/blog/4/team')
        ->and($rows[4]['meta'])->toBe('Editor')
        ->and(array_unique(array_column($rows, 'when')))->toHaveCount(1)
        ->and($rows[0]['when'])->not->toBe('');
});
