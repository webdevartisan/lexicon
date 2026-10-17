<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Presenters\VisibilityPresenter;
use App\Resources\BlogResource;

const OWNER_PERMISSIONS = ['create_posts', 'edit_own_blog', 'publish_blog_posts'];
const AUTHOR_PERMISSIONS = ['create_posts', 'edit_own_posts', 'publish_own_posts'];

/**
 * A blog where user 1 is the owner and user 2 is an author.
 */
function visibilityBlog(string $status = 'published', bool $reviews = false, bool $hasPublishedPost = true): BlogResource
{
    $model = Mockery::mock(BlogModel::class);
    $model->shouldReceive('blogPermissionsFor')->with(7, 1, 1)->andReturn(OWNER_PERMISSIONS);
    $model->shouldReceive('blogPermissionsFor')->with(7, 2, 1)->andReturn(AUTHOR_PERMISSIONS);
    $model->shouldReceive('reviewsPosts')->with(7)->andReturn($reviews);
    $model->shouldReceive('hasPublishedPost')->with(7)->andReturn($hasPublishedPost);

    return new BlogResource(['id' => 7, 'owner_id' => 1, 'blog_slug' => 'field-notes', 'status' => $status], $model);
}

function visibilityPost(array $overrides = []): array
{
    return array_merge([
        'id' => 12,
        'slug' => 'first-light',
        'status' => 'published',
        'visibility' => 'public',
        'published_at' => '2026-10-01 09:00:00',
        'timezone' => 'UTC',
    ], $overrides);
}

test('published post on a live blog is live with an open link', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(), visibilityBlog(), 1);

    expect($line['tone'])->toBe('live')
        ->and($line['key'])->toBe('setup.visibility.live')
        ->and($line['action'])->toBe(['labelKey' => 'setup.visibility.actions.openPost', 'url' => '/blog/field-notes/first-light', 'method' => 'GET']);
});

test('published post on a draft blog says the blog is offline and offers to go live', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(), visibilityBlog('draft'), 1);

    expect($line['tone'])->toBe('offline')
        ->and($line['key'])->toBe('setup.visibility.offlinePost')
        ->and($line['action'])->toBe(['labelKey' => 'setup.visibility.actions.goLive', 'url' => '/dashboard/blog/7/go-live', 'method' => 'POST']);
});

test('offline action hidden for a member without update rights', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(), visibilityBlog('draft'), 2);

    expect($line['key'])->toBe('setup.visibility.offlinePost')
        ->and($line['action'])->toBeNull();
});

test('draft on a blog that never went live says publishing puts it online', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(['status' => 'draft']), visibilityBlog('draft', hasPublishedPost: false), 1);

    expect($line['tone'])->toBe('draft')
        ->and($line['key'])->toBe('setup.visibility.draftFirst')
        ->and($line['action'])->toBeNull();
});

test('draft the viewer can publish', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(['status' => 'draft']), visibilityBlog(), 1);

    expect($line['key'])->toBe('setup.visibility.draft');
});

test('draft on a blog taken offline does not promise to bring it back', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(['status' => 'draft']), visibilityBlog('draft', hasPublishedPost: true), 1);

    expect($line['key'])->toBe('setup.visibility.draft');
});

test('author on review blog gets the review draft line', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(['status' => 'draft']), visibilityBlog(reviews: true), 2);

    expect($line['key'])->toBe('setup.visibility.draftReview');
});

test('submitted post says it is in review', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(['status' => 'pending']), visibilityBlog(reviews: true), 2);

    expect($line['tone'])->toBe('scheduled')
        ->and($line['key'])->toBe('setup.visibility.pending');
});

test('scheduled post shows the date in its time zone', function () {
    $post = visibilityPost(['status' => 'scheduled', 'published_at' => '2026-11-03 07:30:00', 'timezone' => 'Europe/Athens']);

    $line = VisibilityPresenter::forPost($post, visibilityBlog(), 1);

    expect($line['tone'])->toBe('scheduled')
        ->and($line['key'])->toBe('setup.visibility.scheduled')
        ->and($line['params'])->toBe(['date' => '3 Nov 2026, 09:30']);
});

test('private post is private', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(['visibility' => 'private']), visibilityBlog(), 1);

    expect($line['tone'])->toBe('private')
        ->and($line['key'])->toBe('setup.visibility.private')
        ->and($line['action'])->toBeNull();
});

test('unlisted post on a live blog can be opened by link', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(['visibility' => 'unlisted']), visibilityBlog(), 1);

    expect($line['tone'])->toBe('live')
        ->and($line['key'])->toBe('setup.visibility.unlisted')
        ->and($line['action']['url'])->toBe('/blog/field-notes/first-light');
});

test('unlisted post on a draft blog says the blog is offline', function () {
    $line = VisibilityPresenter::forPost(visibilityPost(['visibility' => 'unlisted']), visibilityBlog('draft'), 1);

    expect($line['key'])->toBe('setup.visibility.offlinePost');
});

test('live blog says it is online', function () {
    $line = VisibilityPresenter::forBlog(visibilityBlog(), 1);

    expect($line['tone'])->toBe('live')
        ->and($line['key'])->toBe('setup.visibility.blogOnline')
        ->and($line['action'])->toBe(['labelKey' => 'setup.visibility.actions.openBlog', 'url' => '/blog/field-notes', 'method' => 'GET']);
});

test('draft blog says it is offline and offers to go live', function () {
    $line = VisibilityPresenter::forBlog(visibilityBlog('draft'), 1);

    expect($line['tone'])->toBe('offline')
        ->and($line['key'])->toBe('setup.visibility.blogOffline')
        ->and($line['action']['method'])->toBe('POST');
});

test('archived blog is archived with no action', function () {
    $line = VisibilityPresenter::forBlog(visibilityBlog('archived'), 1);

    expect($line['tone'])->toBe('offline')
        ->and($line['key'])->toBe('setup.visibility.archived')
        ->and($line['action'])->toBeNull();
});
