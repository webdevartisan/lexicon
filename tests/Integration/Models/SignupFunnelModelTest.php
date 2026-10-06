<?php

declare(strict_types=1);

use App\Models\AnalyticsDailyEventModel;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\SignupFunnelModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\AnalyticsFixture;

/**
 * Four accounts whose right answers are known by hand.
 *
 * Writer: 20 days old, started a blog on day 3, published on day 5 and saved a post on day 1.
 * Late: 20 days old, liked a post only on day 16, outside the first 14 days.
 * New: 2 days old and already saved a post, but too young to judge.
 * The shared deleted-user account, which nobody signed up for.
 */
beforeEach(function () {
    $users = new UserModel($this->db);
    $age = function (string $table, int $id, string $column, string $when) {
        $this->db->execute("UPDATE {$table} SET {$column} = UTC_TIMESTAMP() - INTERVAL {$when} WHERE id = ?", [$id]);
    };

    $this->writer = UserFactory::new($users)->create();
    $late = UserFactory::new($users)->create();
    $new = UserFactory::new($users)->create();
    $shared = UserFactory::new($users)->withAttributes(['handle' => 'deleted-user'])->create();

    $age('users', $this->writer, 'created_at', '20 DAY');
    $age('users', $late, 'created_at', '20 DAY');
    $age('users', $new, 'created_at', '2 DAY');
    $age('users', $shared, 'created_at', '20 DAY');

    $blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->writer);
    $age('blogs', $blogId, 'created_at', '17 DAY');
    $postId = PostFactory::new(new PostModel($this->db))
        ->withAttributes(['blog_id' => $blogId, 'author_id' => $this->writer])->published()->create();
    $age('posts', $postId, 'published_at', '15 DAY');

    $this->db->execute(
        'INSERT INTO post_bookmarks (post_id, user_id, created_at) VALUES (?, ?, UTC_TIMESTAMP() - INTERVAL 19 DAY), (?, ?, UTC_TIMESTAMP() - INTERVAL 1 DAY)',
        [$postId, $this->writer, $postId, $new]
    );
    $this->db->execute(
        'INSERT INTO post_votes (post_id, user_id, value, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP() - INTERVAL 4 DAY)',
        [$postId, $late]
    );

    $this->funnel = new SignupFunnelModel($this->db);
    $this->from = gmdate('Y-m-d', strtotime('-30 days'));
    $this->to = gmdate('Y-m-d');
});

test('only accounts old enough are judged, on what they did in their first days', function () {
    expect($this->funnel->outcomes($this->from, $this->to, 14, 'deleted-user'))->toBe([
        'accounts' => 3,
        'judged' => 2,
        'active_readers' => 1,
        'started_blog' => 1,
        'published_post' => 1,
    ]);
});

test('accounts are counted per UTC day, without the shared account', function () {
    $byDay = $this->funnel->accountsByDay($this->from, $this->to, 'deleted-user');

    expect(array_sum($byDay))->toBe(3)
        ->and($byDay[gmdate('Y-m-d', strtotime('-20 days'))])->toBe(2);
});

test('sign-up sources are counted per value, most first', function () {
    $events = new AnalyticsDailyEventModel($this->db);
    foreach ([['search', 'Google', 'blog:3'], ['search', 'Google', 'home'], ['social', 'Reddit', 'blog:3']] as [$channel, $source, $from]) {
        AnalyticsFixture::event($this->db, ['name' => 'signup', 'channel' => $channel, 'referrer_source' => $source, 'props' => ['came_from' => $from]]);
    }
    AnalyticsFixture::rollups($this->db)->rebuildFrom($this->to, 30, 10);

    expect($events->signupBreakdown('source', $this->to, $this->to, 10))->toBe([
        ['value' => 'Google', 'signups' => 2],
        ['value' => 'Reddit', 'signups' => 1],
    ])
        ->and($events->signupBreakdown('came_from', $this->to, $this->to, 1))->toBe([['value' => 'blog:3', 'signups' => 2]]);
});
