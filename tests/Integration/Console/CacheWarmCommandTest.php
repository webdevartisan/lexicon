<?php

declare(strict_types=1);

use App\Console\Commands\CacheWarmCommand;
use App\Models\BlogModel;
use App\Models\CategoryModel;
use App\Models\PostModel;
use App\Models\TagModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\CategoryFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\TagFactory;
use Tests\Factories\UserFactory;

beforeEach(function () {
    $this->ownerId = UserFactory::new(new UserModel($this->db))->create();

    // The warmer's route expansion is private and its constructor wants the whole cache stack, so build it bare.
    $this->mocks = function (array $configured = []): array {
        $reflection = new ReflectionClass(CacheWarmCommand::class);
        $command = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('database')->setValue($command, $this->db);
        $reflection->getProperty('config')->setValue($command, ['warmup_mocks' => $configured]);

        return $reflection->getMethod('resolveWarmupMocks')->invoke($command);
    };

    $this->blogWithPosts = function (int $posts, bool $published = true): int {
        $blogFactory = BlogFactory::new(new BlogModel($this->db));
        $blogId = ($published ? $blogFactory->published() : $blogFactory->draft())->create($this->ownerId);

        for ($i = 0; $i < $posts; $i++) {
            PostFactory::new(new PostModel($this->db))
                ->withAttributes(['blog_id' => $blogId, 'author_id' => $this->ownerId])
                ->published()->create();
        }

        return $blogId;
    };

    $this->slugOf = fn (int $blogId): string => (string) $this->db
        ->query('SELECT blog_slug FROM blogs WHERE id = ?', [$blogId])->fetchColumn();
});

it('picks the published blog with the most published posts first', function () {
    $quiet = ($this->blogWithPosts)(1);
    $busy = ($this->blogWithPosts)(3);

    $mocks = ($this->mocks)();

    expect($mocks['blog_slugs'][0])->toBe(($this->slugOf)($busy))
        ->and($mocks['blog_slugs'])->toContain(($this->slugOf)($quiet));
});

it('skips blogs that are not published', function () {
    $draft = ($this->blogWithPosts)(5, false);
    $live = ($this->blogWithPosts)(1);

    $slugs = ($this->mocks)()['blog_slugs'];

    expect($slugs)->toContain(($this->slugOf)($live))
        ->and($slugs)->not->toContain(($this->slugOf)($draft));
});

it('takes categories and tags from the first blog only when published posts use them', function () {
    $blogId = ($this->blogWithPosts)(2);
    $usedCategory = CategoryFactory::new(new CategoryModel($this->db))->forBlog($blogId)->create();
    $unusedCategory = CategoryFactory::new(new CategoryModel($this->db))->forBlog($blogId)->create();
    $tagId = TagFactory::new(new TagModel($this->db))->forBlog($blogId)->create();

    $postId = (int) $this->db->query('SELECT id FROM posts WHERE blog_id = ? LIMIT 1', [$blogId])->fetchColumn();
    $this->db->execute('UPDATE posts SET category_id = ? WHERE id = ?', [$usedCategory, $postId]);
    $this->db->execute('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)', [$postId, $tagId]);

    $mocks = ($this->mocks)();
    $slug = fn (string $table, int $id) => (string) $this->db->query("SELECT slug FROM {$table} WHERE id = ?", [$id])->fetchColumn();

    expect($mocks['categories'])->toBe([$slug('categories', $usedCategory)])
        ->and($mocks['categories'])->not->toContain($slug('categories', $unusedCategory))
        ->and($mocks['tags'])->toBe([$slug('tags', $tagId)]);
});

it('keeps slugs set in the config instead of querying', function () {
    ($this->blogWithPosts)(2);

    $mocks = ($this->mocks)(['blog_slugs' => ['pinned-blog'], 'categories' => ['news'], 'tags' => ['php']]);

    expect($mocks)->toBe(['blog_slugs' => ['pinned-blog'], 'categories' => ['news'], 'tags' => ['php']]);
});

it('returns empty lists when no blog is published', function () {
    ($this->blogWithPosts)(2, false);

    expect(($this->mocks)())->toBe(['blog_slugs' => [], 'categories' => [], 'tags' => []]);
});
