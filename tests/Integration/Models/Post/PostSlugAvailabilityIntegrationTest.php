<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

beforeEach(function () {
    $this->posts = new PostModel($this->db);
    $blogs = new BlogModel($this->db);
    $ownerId = UserFactory::new(new UserModel($this->db))->create();

    $this->blogId = BlogFactory::new($blogs)->published()->create($ownerId);
    $this->otherBlogId = BlogFactory::new($blogs)->published()->create($ownerId);

    $this->post = function (int $blogId, string $slug) use ($ownerId): int {
        return PostFactory::new($this->posts)
            ->withAttributes(['blog_id' => $blogId, 'author_id' => $ownerId, 'slug' => $slug, 'excerpt' => ''])
            ->draft()
            ->create();
    };
});

it('keeps a free address as it is', function () {
    expect($this->posts->availableSlug($this->blogId, 'hello'))->toBe('hello');
});

it('numbers a taken address, skipping numbers already in use', function () {
    ($this->post)($this->blogId, 'hello');
    ($this->post)($this->blogId, 'hello-2');

    expect($this->posts->availableSlug($this->blogId, 'hello'))->toBe('hello-3');
});

it('only counts posts in the same blog', function () {
    ($this->post)($this->otherBlogId, 'hello');

    expect($this->posts->availableSlug($this->blogId, 'hello'))->toBe('hello');
});

it('does not treat a post as clashing with itself', function () {
    $id = ($this->post)($this->blogId, 'hello');

    expect($this->posts->availableSlug($this->blogId, 'hello', $id))->toBe('hello');
});

it('is not fooled by addresses that only start the same way', function () {
    ($this->post)($this->blogId, 'hello');
    ($this->post)($this->blogId, 'hello-world');

    expect($this->posts->availableSlug($this->blogId, 'hello'))->toBe('hello-2');
});

it('stays within the length limit when it adds a number', function () {
    $long = str_repeat('a', 100);
    ($this->post)($this->blogId, $long);

    $slug = $this->posts->availableSlug($this->blogId, $long);

    expect(strlen($slug))->toBeLessThanOrEqual(100)
        ->and($slug)->toEndWith('-2');
});
