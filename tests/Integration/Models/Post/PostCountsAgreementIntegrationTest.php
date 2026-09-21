<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * The dashboard cards, the "Your blogs" list and the All Posts badges and list
 * must report the same number of published posts for a blog.
 */
beforeEach(function () {
    $this->postModel = new PostModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->userModel = new UserModel($this->db);

    $this->ownerId = UserFactory::new($this->userModel)->create();
    $this->collaboratorId = UserFactory::new($this->userModel)->create();
    $this->blogId = BlogFactory::new($this->blogModel)->published()->create($this->ownerId);
    $this->otherBlogId = BlogFactory::new($this->blogModel)->published()->create($this->ownerId);

    $this->publishedEverywhere = function (int $blogId): array {
        $cards = $this->postModel->countsByStatusForBlogs([$blogId])[$blogId]['published'];
        $pill = $this->postModel->countsByStatusForAuthor(
            authorId: null,
            blogId: $blogId,
            blogOwnerId: $this->ownerId
        )['published'];
        $list = $this->postModel->findByAuthorWithFiltersPagination(
            authorId: null,
            perPage: 100,
            blogId: $blogId,
            status: 'published',
            blogOwnerId: $this->ownerId
        );

        return [$cards, $pill, $list['pagination']['total_records'], count($list['data'])];
    };

    $this->makePost = function (int $blogId, int $authorId, string $status): int {
        return PostFactory::new($this->postModel)
            ->withAttributes(['blog_id' => $blogId, 'author_id' => $authorId, 'status' => $status])
            ->create();
    };
});

it('agrees on zero when the blog has no posts', function () {
    expect(($this->publishedEverywhere)($this->blogId))->toBe([0, 0, 0, 0]);
});

it('agrees on a single published post', function () {
    ($this->makePost)($this->blogId, $this->ownerId, 'published');

    expect(($this->publishedEverywhere)($this->blogId))->toBe([1, 1, 1, 1]);
});

it('counts only published posts among mixed statuses', function () {
    foreach (['published', 'published', 'draft', 'scheduled', 'archived', 'pending'] as $status) {
        ($this->makePost)($this->blogId, $this->ownerId, $status);
    }

    expect(($this->publishedEverywhere)($this->blogId))->toBe([2, 2, 2, 2]);
});

it('includes a collaborator post in the owner blog everywhere', function () {
    ($this->makePost)($this->blogId, $this->ownerId, 'published');
    ($this->makePost)($this->blogId, $this->collaboratorId, 'published');

    expect(($this->publishedEverywhere)($this->blogId))->toBe([2, 2, 2, 2]);
});

it('keeps each blog count separate across several blogs', function () {
    ($this->makePost)($this->blogId, $this->ownerId, 'published');
    ($this->makePost)($this->otherBlogId, $this->ownerId, 'published');
    ($this->makePost)($this->otherBlogId, $this->ownerId, 'published');

    $all = $this->postModel->countsByStatusForBlogs([$this->blogId, $this->otherBlogId]);

    expect($all[$this->blogId]['published'])->toBe(1)
        ->and($all[$this->otherBlogId]['published'])->toBe(2)
        ->and(($this->publishedEverywhere)($this->otherBlogId))->toBe([2, 2, 2, 2]);
});

it('returns a zeroed entry for a blog with no posts among several', function () {
    ($this->makePost)($this->blogId, $this->ownerId, 'draft');

    $all = $this->postModel->countsByStatusForBlogs([$this->blogId, $this->otherBlogId]);

    expect($all[$this->otherBlogId]['all'])->toBe(0)
        ->and($all[$this->blogId]['draft'])->toBe(1);
});

it('keeps posts on blogs the user does not own out of the owner scope', function () {
    $foreignBlog = BlogFactory::new($this->blogModel)->published()->create($this->collaboratorId);
    ($this->makePost)($foreignBlog, $this->ownerId, 'published');

    $pill = $this->postModel->countsByStatusForAuthor(authorId: null, blogOwnerId: $this->ownerId)['published'];

    expect($pill)->toBe(0);
});
