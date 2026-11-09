<?php

declare(strict_types=1);

use App\Models\BlogActivityModel;
use App\Models\BlogModel;
use App\Models\BlogSubscriberModel;
use App\Models\PostListingModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * The figures and the activity feed behind the Home page: what counts, what is left out, and who sees whose posts.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->posts = new PostModel($this->db);
    $this->listing = new PostListingModel($this->db);
    $this->activity = new BlogActivityModel($this->db);
    $this->subscribers = new BlogSubscriberModel($this->db);

    $this->ownerId = UserFactory::new($this->users)->create();
    $this->writerId = UserFactory::new($this->users)->create();
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);

    // Days are counted by MySQL so the fixtures and the queries share one clock
    $this->post = function (string $status, string $when, ?int $authorId = null): int {
        $postId = PostFactory::new($this->posts)
            ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $authorId ?? $this->ownerId, 'status' => $status])
            ->create();
        $this->db->query("UPDATE posts SET published_at = DATE_ADD(NOW(), INTERVAL {$when}) WHERE id = ?", [$postId]);

        return $postId;
    };

    $this->comment = function (int $postId, string $status = 'approved', string $age = '0 DAY'): int {
        $this->db->query(
            "INSERT INTO comments (post_id, user_id, content, status, created_at)
             VALUES (?, ?, 'A comment', ?, DATE_SUB(NOW(), INTERVAL {$age}))",
            [$postId, $this->writerId, $status]
        );

        return (int) $this->db->getConnection()->lastInsertId();
    };
});

test('the feed holds only the kinds asked for, newest first', function () {
    $postId = ($this->post)('published', '-2 DAY');
    ($this->comment)($postId);

    $kinds = array_column($this->activity->recent($this->blogId, [BlogActivityModel::COMMENT, BlogActivityModel::PUBLISHED]), 'kind');

    expect($kinds)->toBe(['comment', 'published'])
        ->and(array_column($this->activity->recent($this->blogId, [BlogActivityModel::COMMENT]), 'kind'))->toBe(['comment'])
        ->and($this->activity->recent($this->blogId, []))->toBe([])
        ->and($this->activity->recent($this->blogId, ['nothing-like-this']))->toBe([]);
});

test('spam, deleted and hidden comments never reach the feed', function () {
    $postId = ($this->post)('published', '-1 DAY');
    ($this->comment)($postId);
    ($this->comment)($postId, 'spam');
    $deleted = ($this->comment)($postId);
    $hidden = ($this->comment)($postId);
    $this->db->query('UPDATE comments SET deleted_at = NOW() WHERE id = ?', [$deleted]);
    $this->db->query('UPDATE comments SET hidden_at = NOW() WHERE id = ?', [$hidden]);

    expect($this->activity->recent($this->blogId, [BlogActivityModel::COMMENT]))->toHaveCount(1);
});

test('a pending comment is marked as waiting', function () {
    ($this->comment)(($this->post)('published', '-1 DAY'), 'pending');

    [$event] = $this->activity->recent($this->blogId, [BlogActivityModel::COMMENT]);

    expect($event['detail'])->toBe('pending');
});

test('posts that are not live yet are not announced as published', function () {
    ($this->post)('published', '+2 DAY');
    ($this->post)('draft', '-1 DAY');

    expect($this->activity->recent($this->blogId, [BlogActivityModel::PUBLISHED]))->toBe([]);
});

test('the owner is not listed as having joined their own blog', function () {
    $this->blogs->addUserToBlog($this->blogId, $this->writerId, 'author', $this->ownerId);

    $joined = $this->activity->recent($this->blogId, [BlogActivityModel::MEMBER]);
    $handle = $this->db->query('SELECT handle FROM users WHERE id = ?', [$this->writerId])->fetchColumn();

    expect($joined)->toHaveCount(1)
        ->and($joined[0]['actor'])->toBe($handle)
        ->and($joined[0]['detail'])->toBe('author');
});

test('only confirmed subscribers appear in the feed and in the weekly count', function () {
    $this->subscribers->subscribe($this->blogId, 'waiting@example.test');
    $this->subscribers->subscribe($this->blogId, 'new@example.test', null, true);
    $this->subscribers->subscribe($this->blogId, 'old@example.test', null, true);
    $this->db->query("UPDATE blog_subscribers SET confirmed_at = DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE email = 'old@example.test'");

    expect($this->activity->recent($this->blogId, [BlogActivityModel::SUBSCRIBER]))->toHaveCount(2)
        ->and($this->subscribers->countConfirmedSince($this->blogId, 7))->toBe(1)
        ->and($this->subscribers->countConfirmedSince($this->blogId, 30))->toBe(2);
});

test('the feed is capped however many rows are asked for', function () {
    $postId = ($this->post)('published', '-1 DAY');
    for ($i = 0; $i < 25; $i++) {
        ($this->comment)($postId);
    }

    expect($this->activity->recent($this->blogId, [BlogActivityModel::COMMENT], 500))->toHaveCount(20)
        ->and($this->activity->recent($this->blogId, [BlogActivityModel::COMMENT], 0))->toHaveCount(1);
});

test('published posts are counted by the day and by the week, for the whole blog or one writer', function () {
    ($this->post)('published', '-2 DAY');
    ($this->post)('published', '-10 DAY', $this->writerId);
    ($this->post)('published', '-40 DAY');
    ($this->post)('published', '+3 DAY');
    ($this->post)('draft', '-1 DAY');

    $weeks = $this->listing->publishedPerWeek($this->blogId, null, 12);

    expect($this->listing->countPublishedSince($this->blogId, null, 30))->toBe(2)
        ->and($this->listing->countPublishedSince($this->blogId, $this->writerId, 30))->toBe(1)
        ->and($weeks)->toHaveCount(12)
        ->and($weeks[11])->toBe(1)
        ->and($weeks[10])->toBe(1)
        ->and($weeks[6])->toBe(1)
        ->and(array_sum($weeks))->toBe(3)
        ->and(array_sum($this->listing->publishedPerWeek($this->blogId, $this->writerId, 12)))->toBe(1);
});

test('the next scheduled post is the one that goes live first', function () {
    expect($this->listing->nextScheduled($this->blogId, null))->toBeNull();

    ($this->post)('scheduled', '+5 DAY');
    $sooner = ($this->post)('scheduled', '+2 DAY', $this->writerId);

    expect($this->listing->nextScheduled($this->blogId, null)['id'])->toBe($sooner)
        ->and($this->listing->nextScheduled($this->blogId, $this->ownerId)['id'])->not->toBe($sooner)
        ->and($this->listing->nextScheduled($this->blogId, $this->writerId)['id'])->toBe($sooner);
});

test('only approved comments from the last days count as new', function () {
    $postId = ($this->post)('published', '-20 DAY');
    ($this->comment)($postId, 'approved', '1 DAY');
    ($this->comment)($postId, 'approved', '10 DAY');
    ($this->comment)($postId, 'pending', '1 DAY');

    expect($this->posts->countApprovedCommentsSince($this->blogId, 7))->toBe(1)
        ->and($this->posts->countApprovedCommentsSince($this->blogId, 30))->toBe(2);
});
