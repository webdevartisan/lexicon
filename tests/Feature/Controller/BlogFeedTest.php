<?php

declare(strict_types=1);

use App\Controllers\FeedController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\PublishedPostModel;
use App\Models\UserModel;
use App\Services\LocaleState;
use App\ValueObjects\LocaleContext;
use Framework\Exceptions\PageNotFoundException;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

beforeEach(function () {
    $this->blogModel = new BlogModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->postModel = new PostModel($this->db);
    $this->settingsModel = new BlogSettingsModel($this->db);

    $this->viewer = new class() implements Framework\Interfaces\TemplateViewerInterface
    {
        public function render(string $template, array $data = []): string
        {
            return '';
        }

        public function addGlobals(array $vars): void {}

        public function compiledViewStats(): array
        {
            return [];
        }

        public function pruneCompiledViews(int $maxAgeSeconds): int
        {
            return 0;
        }

        public function clearCompiledViews(): array
        {
            return [];
        }
    };

    $this->makeBlog = function (bool $published = true): array {
        $slug = 'feed-'.bin2hex(random_bytes(4));
        $ownerId = UserFactory::new($this->userModel)->create();

        $factory = BlogFactory::new($this->blogModel)->withAttributes(['blog_slug' => $slug]);
        $blogId = ($published ? $factory->published() : $factory)->create($ownerId);
        $this->settingsModel->createDefaultForBlog($blogId, ['default_locale' => 'en']);

        return [$blogId, $slug, $ownerId];
    };

    $this->visit = function (string $slug): \Framework\Core\Response {
        LocaleState::set(LocaleContext::forGuest('en'));

        $controller = new FeedController(
            $this->blogModel,
            $this->settingsModel,
            new PublishedPostModel($this->db),
            $this->userModel
        );
        $request = makeRequest('/blog/'.$slug.'/feed.xml', 'GET', [], []);
        setupController($controller, $request, $this->viewer);

        return callController($controller, 'show', $request, $slug);
    };
});

afterEach(function () {
    LocaleState::reset();
});

test('the feed lists published posts as valid RSS', function () {
    [$blogId, $slug, $ownerId] = ($this->makeBlog)();
    PostFactory::new($this->postModel)->published()->withAttributes([
        'blog_id' => $blogId,
        'author_id' => $ownerId,
        'slug' => 'first-entry',
        'title' => 'First entry',
    ])->create();

    $response = ($this->visit)($slug);
    $xml = simplexml_load_string($response->getBody());

    expect($response->getHeader('Content-Type'))->toContain('application/xml')
        ->and($xml)->not->toBeFalse()
        ->and((string) $xml->channel->item[0]->title)->toBe('First entry')
        ->and((string) $xml->channel->item[0]->link)->toEndWith('/blog/'.$slug.'/first-entry');
});

test('drafts stay out of the feed', function () {
    [$blogId, $slug, $ownerId] = ($this->makeBlog)();
    PostFactory::new($this->postModel)->draft()->withAttributes([
        'blog_id' => $blogId,
        'author_id' => $ownerId,
        'title' => 'Unfinished',
    ])->create();

    $xml = simplexml_load_string(($this->visit)($slug)->getBody());

    expect(count($xml->channel->item))->toBe(0);
});

test('post images are made absolute for feed readers', function () {
    [$blogId, $slug, $ownerId] = ($this->makeBlog)();
    PostFactory::new($this->postModel)->published()->withAttributes([
        'blog_id' => $blogId,
        'author_id' => $ownerId,
        'content' => '<p>Look</p><img src="/uploads/a.jpg">',
    ])->create();

    $body = ($this->visit)($slug)->getBody();

    expect($body)->toContain('src="'.rtrim(base_url(), '/').'/uploads/a.jpg"');
});

test('a blog that is not live has no feed', function () {
    [, $slug] = ($this->makeBlog)(false);

    expect(fn () => ($this->visit)($slug))->toThrow(PageNotFoundException::class);
});

test('an unknown blog has no feed', function () {
    expect(fn () => ($this->visit)('no-such-blog-'.bin2hex(random_bytes(3))))->toThrow(PageNotFoundException::class);
});
