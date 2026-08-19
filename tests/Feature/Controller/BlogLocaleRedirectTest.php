<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\LocaleState;
use App\ValueObjects\LocaleContext;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * A page that exists in one language must resolve at exactly one URL. Before
 * this guard every locale prefix rendered byte-identical HTML with a wrong lang
 * attribute and a false set of hreflang alternates.
 */
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

    // Slugs are set explicitly so each assertion can name the URL it expects.
    $this->makeBlog = function (string $defaultLocale, int $translationsEnabled): array {
        $slug = 'loc-'.bin2hex(random_bytes(4));
        $ownerId = UserFactory::new($this->userModel)->create();

        $blogId = BlogFactory::new($this->blogModel)
            ->published()
            ->withAttributes(['blog_slug' => $slug])
            ->create($ownerId);

        $this->settingsModel->createDefaultForBlog($blogId, [
            'default_locale' => $defaultLocale,
        ]);

        // createDefaultForBlog does not insert translations_enabled, since a new
        // blog starts monolingual. Only the update path whitelists that column.
        if ($translationsEnabled === 1) {
            $this->settingsModel->updateForBlog($blogId, ['translations_enabled' => 1]);
        }

        return [$blogId, $slug, $ownerId];
    };

    $this->makePost = function (int $blogId, int $ownerId, ?string $locale = null): string {
        $slug = 'post-'.bin2hex(random_bytes(4));

        $postId = PostFactory::new($this->postModel)
            ->published()
            ->withAttributes(['blog_id' => $blogId, 'author_id' => $ownerId, 'slug' => $slug])
            ->create();

        if ($locale !== null) {
            $this->db->query(
                'INSERT INTO post_translations (post_id, locale, title, content) VALUES (?, ?, ?, ?)',
                [$postId, $locale, 'Translated title', 'Translated body']
            );
        }

        return $slug;
    };

    // Built by hand rather than resolved from the container: the container's
    // Database is the development connection, while the factories above write to
    // the test database inside a transaction. Same approach as the other feature
    // controller tests.
    $this->makeController = fn (): \App\Controllers\BlogController => new \App\Controllers\BlogController(
        $this->blogModel,
        $this->userModel,
        $this->postModel,
        new \App\Models\CategoryModel($this->db),
        $this->settingsModel,
        new \App\Models\TagModel($this->db),
        new \App\Models\PostVoteModel($this->db),
        new \App\Models\PostBookmarkModel($this->db),
        new \App\Models\PostTranslationModel($this->db),
        new \App\Models\UserProfileModel($this->db),
        new \App\Services\ContentLocaleResolver(new \App\Services\LocaleRegistry(ROOT_PATH)),
        new \App\Services\HeadI18nBuilder(new \App\Services\LocaleRegistry(ROOT_PATH))
    );

    // Pre-routing has already stripped the prefix by the time a controller runs,
    // so the URI is bare and the locale arrives through LocaleState.
    $this->visit = function (string $action, string $uri, string $locale, array $query, ...$args) {
        LocaleState::set(LocaleContext::forGuest($locale));

        $controller = ($this->makeController)();
        $request = makeRequest($uri, 'GET', [], $query);

        setupController($controller, $request, $this->viewer);

        return callController($controller, $action, $request, ...$args);
    };
});

afterEach(function () {
    LocaleState::reset();
});

test('a monolingual blog redirects a foreign locale to its default', function () {
    [, $slug] = ($this->makeBlog)('en', 0);

    $response = ($this->visit)('showBlog', '/blog/'.$slug, 'el', [], $slug);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toBe('/en/blog/'.$slug);
});

test('a monolingual blog serves its own locale without redirecting', function () {
    [, $slug] = ($this->makeBlog)('en', 0);

    expect(($this->visit)('showBlog', '/blog/'.$slug, 'en', [], $slug)->getStatusCode())->toBe(200);
});

/**
 * A blog whose base language is not the platform default is a first-class case,
 * so English is the one that redirects here.
 */
test('a greek-first blog redirects english to greek', function () {
    [, $slug] = ($this->makeBlog)('el', 0);

    $response = ($this->visit)('showBlog', '/blog/'.$slug, 'en', [], $slug);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toBe('/el/blog/'.$slug);
});

test('a blog with a translated post serves that locale', function () {
    [$blogId, $slug, $ownerId] = ($this->makeBlog)('en', 1);
    ($this->makePost)($blogId, $ownerId, 'el');

    expect(($this->visit)('showBlog', '/blog/'.$slug, 'el', [], $slug)->getStatusCode())->toBe(200);
});

/**
 * The narrowing that matters: the blog carries Greek content, this post does not.
 */
test('an untranslated post redirects even when its blog has other translations', function () {
    [$blogId, $slug, $ownerId] = ($this->makeBlog)('en', 1);

    ($this->makePost)($blogId, $ownerId, 'el');
    $bareSlug = ($this->makePost)($blogId, $ownerId);

    $response = ($this->visit)(
        'showBlogPost',
        '/blog/'.$slug.'/'.$bareSlug,
        'el',
        [],
        $slug,
        $bareSlug
    );

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toBe('/en/blog/'.$slug.'/'.$bareSlug);
});

/**
 * LocalePrefixIntake strips the query string along with the prefix, so this pins
 * that pagination survives a locale redirect rather than dumping the reader on
 * page one.
 */
test('the query string survives the redirect', function () {
    [, $slug] = ($this->makeBlog)('en', 0);

    $response = ($this->visit)(
        'archiveBlog',
        '/blog/'.$slug.'/archive',
        'el',
        ['page' => '3'],
        $slug
    );

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toBe('/en/blog/'.$slug.'/archive?page=3');
});
