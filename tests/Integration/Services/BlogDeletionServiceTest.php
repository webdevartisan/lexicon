<?php

declare(strict_types=1);

use App\Interfaces\UploadServiceInterface;
use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsVisitModel;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\BlogDeletionService;
use App\Services\PublicCacheInvalidator;
use Tests\Factories\BlogFactory;
use Tests\Factories\CommentFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\AnalyticsFixture;

/**
 * The upload service is a mock: storage/uploads/ is shared with development, so
 * the real one would delete real files.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->posts = new PostModel($this->db);

    $this->cacheInvalidator = Mockery::mock(PublicCacheInvalidator::class)->shouldIgnoreMissing();
    $this->service = new BlogDeletionService(
        $this->blogs,
        $this->posts,
        new BlogSettingsModel($this->db),
        new UserPreferencesModel($this->db),
        Mockery::mock(UploadServiceInterface::class)->shouldIgnoreMissing(),
        $this->cacheInvalidator,
        new AnalyticsEventModel($this->db),
        new AnalyticsVisitModel($this->db),
        AnalyticsFixture::rollups($this->db)
    );

    $this->ownerId = UserFactory::new($this->users)->withAttributes(['handle' => 'blog-owner-x'])->create();
    $this->writerId = UserFactory::new($this->users)->withAttributes(['handle' => 'guest-writer-x'])->create();
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    $this->postId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->writerId, 'blog_id' => $this->blogId])
        ->published()
        ->create();
});

it('counts replies among the deleted comments', function () {
    $comments = new CommentModel($this->db);
    $parentId = CommentFactory::new($comments)->create($this->postId, $this->ownerId);
    CommentFactory::new($comments)->withAttributes(['parent_comment_id' => $parentId])->create($this->postId, $this->writerId);

    $stats = $this->service->deleteBlog($this->blogId, $this->ownerId);

    expect($stats['deleted_comments'])->toBe(2);
});

it('clears the profile page of everyone who wrote in the blog', function () {
    $this->cacheInvalidator->shouldReceive('purgeProfile')->once()->with('guest-writer-x');

    $this->service->deleteBlog($this->blogId, $this->ownerId);
});
