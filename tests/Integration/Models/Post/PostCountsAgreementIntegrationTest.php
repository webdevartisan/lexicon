<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * The Overview cards and the Posts page tabs and list must report the same
 * number of published posts, whether they cover the whole blog (owners and
 * editors) or one writer's own posts.
 */
beforeEach(function () {
    $this->postModel = new PostModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->userModel = new UserModel($this->db);

    $this->ownerId = UserFactory::new($this->userModel)->create();
    $this->collaboratorId = UserFactory::new($this->userModel)->create();
    $this->blogId = BlogFactory::new($this->blogModel)->published()->create($this->ownerId);
    $this->otherBlogId = BlogFactory::new($this->blogModel)->published()->create($this->ownerId);

    $this->publishedEverywhere = function (int $blogId, ?int $authorId = null): array {
        $counts = $this->postModel->countsByStatusForAuthor(authorId: $authorId, blogId: $blogId)['published'];
        $list = $this->postModel->findByAuthorWithFiltersPagination(
            authorId: $authorId,
            perPage: 100,
            blogId: $blogId,
            status: 'published'
        );

        return [$counts, $list['pagination']['total_records'], count($list['data'])];
    };

    $this->makePost = function (int $blogId, int $authorId, string $status): int {
        return PostFactory::new($this->postModel)
            ->withAttributes(['blog_id' => $blogId, 'author_id' => $authorId, 'status' => $status])
            ->create();
    };
});

it('agrees on zero when the blog has no posts', function () {
    expect(($this->publishedEverywhere)($this->blogId))->toBe([0, 0, 0]);
});

it('agrees on a single published post', function () {
    ($this->makePost)($this->blogId, $this->ownerId, 'published');

    expect(($this->publishedEverywhere)($this->blogId))->toBe([1, 1, 1]);
});

it('counts only published posts among mixed statuses', function () {
    foreach (['published', 'published', 'draft', 'scheduled', 'archived', 'pending'] as $status) {
        ($this->makePost)($this->blogId, $this->ownerId, $status);
    }

    expect(($this->publishedEverywhere)($this->blogId))->toBe([2, 2, 2]);
});

it('includes a collaborator post in the whole-blog scope', function () {
    ($this->makePost)($this->blogId, $this->ownerId, 'published');
    ($this->makePost)($this->blogId, $this->collaboratorId, 'published');

    expect(($this->publishedEverywhere)($this->blogId))->toBe([2, 2, 2]);
});

it('keeps a writer to their own posts in the own-posts scope', function () {
    ($this->makePost)($this->blogId, $this->ownerId, 'published');
    ($this->makePost)($this->blogId, $this->collaboratorId, 'published');
    ($this->makePost)($this->blogId, $this->collaboratorId, 'draft');

    expect(($this->publishedEverywhere)($this->blogId, $this->collaboratorId))->toBe([1, 1, 1]);
});

it('keeps each blog count separate', function () {
    ($this->makePost)($this->blogId, $this->ownerId, 'published');
    ($this->makePost)($this->otherBlogId, $this->ownerId, 'published');
    ($this->makePost)($this->otherBlogId, $this->ownerId, 'published');

    expect(($this->publishedEverywhere)($this->blogId))->toBe([1, 1, 1])
        ->and(($this->publishedEverywhere)($this->otherBlogId))->toBe([2, 2, 2]);
});
