<?php

declare(strict_types=1);

/**
 * CommentService::create refuses comments the public form would never offer.
 *
 * The template hides the form when comments are off or the blog is not live;
 * these pin the same rules on the server so a hand-built POST gets nowhere.
 */

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use App\Resources\BlogResource;
use App\Services\CommentAudienceResolver;
use App\Services\CommentService;
use App\Services\NotificationService;
use Framework\Session;

/**
 * Build a service whose post, blog and settings are canned.
 *
 * @param  array<string, mixed>  $post  Overrides for the post row
 * @param  string  $blogStatus  Status the blog row reports
 * @param  array<string, mixed>|null  $settings  Settings row, null for a missing one
 * @return array{0: CommentService, 1: Mockery\MockInterface}
 */
function makeCommentServiceFor(array $post = [], string $blogStatus = 'published', ?array $settings = ['comments_enabled' => 1]): array
{
    $posts = Mockery::mock(PostModel::class);
    $posts->shouldReceive('find')->andReturn(array_merge([
        'id' => 7,
        'blog_id' => 3,
        'status' => 'published',
        'visibility' => 'public',
        'comments_enabled' => 1,
    ], $post));

    $blog = Mockery::mock(BlogResource::class);
    $blog->shouldReceive('status')->andReturn($blogStatus);

    $blogs = Mockery::mock(BlogModel::class);
    $blogs->shouldReceive('getBlog')->andReturn($blog);

    $blogSettings = Mockery::mock(BlogSettingsModel::class);
    $blogSettings->shouldReceive('findByBlogId')->andReturn($settings);

    $comments = Mockery::mock(CommentModel::class);

    $service = new CommentService(
        $posts,
        $comments,
        $blogSettings,
        $blogs,
        Mockery::mock(NotificationService::class),
        Mockery::mock(CommentAudienceResolver::class),
        Mockery::mock(Session::class),
    );

    return [$service, $comments];
}

afterEach(function () {
    Mockery::close();
});

it('rejects a comment when the blog has comments switched off', function () {
    [$service, $comments] = makeCommentServiceFor(settings: ['comments_enabled' => 0]);
    $comments->shouldNotReceive('create');

    $result = $service->create(7, null, 'Hello there', ['id' => 1]);

    expect($result['ok'])->toBeFalse()
        ->and($result['id'])->toBeNull();
});

it('rejects a comment when the post has comments switched off', function () {
    [$service, $comments] = makeCommentServiceFor(post: ['comments_enabled' => 0]);
    $comments->shouldNotReceive('create');

    expect($service->create(7, null, 'Hello there', ['id' => 1])['ok'])->toBeFalse();
});

it('rejects a comment on a blog that is not published', function (string $status) {
    [$service, $comments] = makeCommentServiceFor(blogStatus: $status);
    $comments->shouldNotReceive('create');

    expect($service->create(7, null, 'Hello there', ['id' => 1])['ok'])->toBeFalse();
})->with(['draft', 'archived', 'suspended']);
