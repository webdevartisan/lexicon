<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\BlogTextModel;
use App\Models\PostTranslationModel;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use App\Services\BlogCacheInvalidator;
use App\Services\BlogLocales;
use App\Services\BlogPageContext;
use App\Services\ThemeService;
use App\Services\ThemeWording;
use App\Services\ViewerContext;
use Framework\Core\App;
use Framework\Exceptions\PageNotFoundException;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * Every themed blog page, a module page included, starts from the same blog, settings and meta.
 */
beforeEach(function () {
    $_SESSION = [];
    auth()->logout();

    $this->email = faker()->unique()->safeEmail();
    $this->ownerId = UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $this->email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    $this->blogs = new BlogModel($this->db);
    $this->slugOf = fn (int $id): string => (string) $this->blogs->getBlog($id)->toArray()['blog_slug'];

    $container = App::container();
    $this->context = new BlogPageContext(
        $this->blogs,
        new UserModel($this->db),
        new BlogSettingsModel($this->db),
        new UserProfileModel($this->db),
        new BlogLocales(new PostTranslationModel($this->db)),
        new ThemeWording(
            new BlogTextModel($this->db),
            $container->get(ThemeService::class),
            $container->get(BlogCacheInvalidator::class)
        ),
        $container->get(ViewerContext::class),
    );
});

afterEach(function () {
    auth()->logout();
});

test('a published blog gives its row, settings and canonical url', function () {
    $blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    $slug = ($this->slugOf)($blogId);

    $page = $this->context->forSlug($slug);

    expect((int) $page['blog']['id'])->toBe($blogId)
        ->and($page['meta']['canonical'])->toEndWith('/blog/'.$slug)
        ->and($page['meta']['robots'])->toBeNull()
        ->and($page)->toHaveKeys(['user', 'settings', 'viewer', 'localeSet', 'wording']);
});

test('a draft blog is not found for a guest', function () {
    $slug = ($this->slugOf)(BlogFactory::new($this->blogs)->draft()->create($this->ownerId));

    expect(fn () => $this->context->forSlug($slug))->toThrow(PageNotFoundException::class);
});

test('the owner previews a draft blog with noindex', function () {
    $slug = ($this->slugOf)(BlogFactory::new($this->blogs)->draft()->create($this->ownerId));
    expect(auth()->login($this->email, 'password123'))->toBeTrue();

    expect($this->context->forSlug($slug)['meta']['robots'])->toBe('noindex, nofollow');
});

test('an unknown slug is not found', function () {
    expect(fn () => $this->context->forSlug('no-such-blog-here'))->toThrow(PageNotFoundException::class);
});
