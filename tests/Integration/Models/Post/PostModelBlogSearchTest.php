<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\PublishedPostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Reader search inside one blog: only that blog's published, public posts come back.
 */
beforeEach(function () {
    $this->postModel = new PostModel($this->db);
    $this->published = new PublishedPostModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);

    $this->userId = UserFactory::new($this->userModel)->create();
    $this->blogId = BlogFactory::new($this->blogModel)->published()->create($this->userId);

    $this->addPost = function (int $blogId, string $title, array $extra = []): int {
        return PostFactory::new($this->postModel)
            ->withAttributes($extra + [
                'author_id' => $this->userId,
                'blog_id' => $blogId,
                'title' => $title,
                'status' => 'published',
                'visibility' => 'public',
                'published_at' => date('Y-m-d H:i:s'),
            ])
            ->create();
    };
});

it('finds posts in the blog by a word prefix', function () {
    ($this->addPost)($this->blogId, 'Notes on Mercury');
    ($this->addPost)($this->blogId, 'Something unrelated');

    $result = $this->published->searchInBlog($this->blogId, 'merc');

    expect($result['total'])->toBe(1)
        ->and($result['data'][0]['title'])->toBe('Notes on Mercury');
});

it('leaves out other blogs, drafts and private posts', function () {
    $otherBlog = BlogFactory::new($this->blogModel)->published()->create($this->userId);

    ($this->addPost)($this->blogId, 'Venus visible');
    ($this->addPost)($otherBlog, 'Venus elsewhere');
    ($this->addPost)($this->blogId, 'Venus draft', ['status' => 'draft']);
    ($this->addPost)($this->blogId, 'Venus private', ['visibility' => 'private']);

    $result = $this->published->searchInBlog($this->blogId, 'venus');

    expect($result['total'])->toBe(1)
        ->and($result['data'][0]['title'])->toBe('Venus visible');
});

it('counts every match but returns no more than the limit', function () {
    foreach (['One', 'Two', 'Three'] as $n) {
        ($this->addPost)($this->blogId, "Saturn ring {$n}");
    }

    $result = $this->published->searchInBlog($this->blogId, 'saturn', 2);

    expect($result['total'])->toBe(3)
        ->and($result['data'])->toHaveCount(2);
});

it('returns nothing for empty or operator-only input', function (string $query) {
    ($this->addPost)($this->blogId, 'Jupiter storms');

    expect($this->published->searchInBlog($this->blogId, $query))->toBe(['data' => [], 'total' => 0]);
})->with(['empty' => [''], 'spaces' => ['   '], 'operators' => ['+-"()*<>~@']]);

it('treats operators mixed into a word as plain text', function () {
    ($this->addPost)($this->blogId, 'Neptune winds');

    $result = $this->published->searchInBlog($this->blogId, '+nept* -"(winds)');

    expect($result['total'])->toBe(1);
});
