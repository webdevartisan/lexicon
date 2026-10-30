<?php

declare(strict_types=1);

use App\Controllers\Dashboard\BlogController;
use App\Controllers\Dashboard\BlogTextController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\BlogTextModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\FrontPageTexts;
use App\Services\ThemeWording;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Saving texts from the blog's own pages: only the texts sent change, each
 * language keeps its own wording, a theme's texts change only where the theme
 * allows it, and only the blog's team can save.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = App::container()->get(\Framework\Database::class);
    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $this->signIn = function (): int {
        $_SESSION = [];
        auth()->logout();
        $email = faker()->unique()->safeEmail();
        $userId = UserFactory::new(new UserModel($this->db))
            ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
            ->create();
        $this->db->execute(
            'INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE role_slug = ?',
            [$userId, 'reader']
        );
        expect(auth()->login($email, 'password123'))->toBeTrue();

        return $userId;
    };
    $this->ownerId = ($this->signIn)();

    $this->blogs = new BlogModel($this->db);
    $this->settings = new BlogSettingsModel($this->db);
    $this->texts = new BlogTextModel($this->db);

    $request = function (array $post): \Framework\Core\Request {
        return makeRequest('/dashboard/blog', 'POST', $post + [
            '_token' => App::container()->get(Csrf::class)->getToken(),
        ], [], ['HTTP_REFERER' => base_url().'/en/dashboard']);
    };

    $slug = 'front-texts-'.strtolower(uniqid());
    $create = $request(['name' => 'Front texts', 'slug' => $slug, 'description' => '']);
    $blogs = App::container()->get(BlogController::class);
    setupController($blogs, $create, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());
    callController($blogs, 'create', $create);
    $this->blogId = (int) $this->blogs->getBlogBySlug($slug)['id'];

    $this->save = function (array $post) use ($request): array {
        $req = $request($post);
        $controller = App::container()->get(BlogTextController::class);
        setupController($controller, $req, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());
        $response = callController($controller, 'save', $req, (string) $this->blogId);

        return [$response->getStatusCode(), json_decode($response->getBody(), true)];
    };

    // A blog has a Greek page once it takes translations and one post is translated
    $this->addGreek = function (): void {
        $this->settings->updateForBlog($this->blogId, ['translations_enabled' => 1]);
        $postId = PostFactory::new(new PostModel($this->db))
            ->published()
            ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->ownerId])
            ->create();
        $this->db->execute(
            'INSERT INTO post_translations (post_id, locale, title, content) VALUES (?, ?, ?, ?)',
            [$postId, 'el', 'Greek title', 'Greek body']
        );
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('saves a text in the blog language and leaves the others alone', function () {
    $this->settings->updateForBlog($this->blogId, ['subtitle' => 'Kept intro', 'social_links' => '{"github":"https://github.com/lexicon"}']);

    [$status] = ($this->save)(['locale' => 'en', 'tagline' => 'New tagline']);

    $stored = $this->settings->findByBlogId($this->blogId);
    expect($status)->toBe(200)
        ->and($stored['tagline'])->toBe('New tagline')
        ->and($stored['subtitle'])->toBe('Kept intro')
        ->and($stored['social_links'])->toBe('{"github":"https://github.com/lexicon"}');
});

it('keeps a second language apart and falls back to the blog language once cleared', function () {
    ($this->addGreek)();
    $this->settings->updateForBlog($this->blogId, ['tagline' => 'English tagline']);

    [$status] = ($this->save)(['locale' => 'el', 'tagline' => 'Greek tagline']);
    $settings = $this->settings->findByBlogId($this->blogId);
    $front = App::container()->get(FrontPageTexts::class);

    expect($status)->toBe(200)
        ->and($settings['tagline'])->toBe('English tagline')
        ->and($front->shownIn($this->blogId, $settings, 'el')['tagline'])->toBe('Greek tagline')
        ->and($front->shownIn($this->blogId, $settings, 'en')['tagline'])->toBe('English tagline');

    ($this->save)(['locale' => 'el', 'tagline' => '']);

    expect($this->texts->forLocale($this->blogId, 'el'))->toBe([])
        ->and($front->shownIn($this->blogId, $settings, 'el')['tagline'])->toBe('English tagline');
});

it('refuses a language the blog has no page in', function () {
    [$status, $body] = ($this->save)(['locale' => 'el', 'tagline' => 'Greek tagline']);

    expect($status)->toBe(422)
        ->and($body['error'])->toBe('This blog has no page in that language.')
        ->and($this->texts->forLocale($this->blogId, 'el'))->toBe([]);
});

it('refuses a text over its length limit and stores nothing', function () {
    [$status, $body] = ($this->save)(['locale' => 'en', 'tagline' => str_repeat('a', 161)]);

    expect($status)->toBe(422)
        ->and($body['error'])->toBe('Keep the tagline to 160 characters.')
        ->and($this->settings->findByBlogId($this->blogId)['tagline'] ?? null)->toBeNull();
});

it('refuses text that is not valid UTF-8', function () {
    [$status] = ($this->save)(['locale' => 'en', 'tagline' => "\xB5a"]);

    expect($status)->toBe(422);
});

it('does not let someone outside the blog save', function () {
    ($this->signIn)();

    [$status] = ($this->save)(['locale' => 'en', 'tagline' => 'Not theirs']);

    expect($status)->toBe(403)
        ->and($this->settings->findByBlogId($this->blogId)['tagline'] ?? null)->toBeNull();
});

it('stores theme wording the theme allows, per language, and shows it with placeholders filled in', function () {
    $this->settings->updateForBlog($this->blogId, ['theme' => 'nocturne']);

    [$status] = ($this->save)([
        'wording_locale' => 'en',
        'wording' => ['nocturne::home.edition' => 'The Morning Edition', 'nocturne::home.desk' => 'Written by {owner}'],
    ]);

    $wording = App::container()->get(ThemeWording::class)->load($this->blogId, 'nocturne', 'en');
    expect($status)->toBe(200)
        ->and($this->texts->forLocale($this->blogId, 'en'))->toBe([
            'nocturne::home.desk' => 'Written by {owner}',
            'nocturne::home.edition' => 'The Morning Edition',
        ])
        ->and($wording->html('nocturne::home.desk', ['owner' => 'Ada']))->toBe('Written by Ada')
        ->and($this->texts->forLocale($this->blogId, 'el'))->toBe([]);
});

it('escapes theme wording an owner typed', function () {
    $this->settings->updateForBlog($this->blogId, ['theme' => 'nocturne']);
    ($this->save)(['wording_locale' => 'en', 'wording' => ['nocturne::home.edition' => 'Tea & <b>toast</b>']]);

    $wording = App::container()->get(ThemeWording::class)->load($this->blogId, 'nocturne', 'en');

    expect($wording->html('nocturne::home.edition'))->toBe('Tea &amp; &lt;b&gt;toast&lt;/b&gt;');
});

it('refuses a theme text the theme keeps fixed', function () {
    $this->settings->updateForBlog($this->blogId, ['theme' => 'nocturne']);

    [$status] = ($this->save)(['wording_locale' => 'en', 'wording' => ['nocturne::nav.home' => 'Start']]);

    expect($status)->toBe(422)
        ->and($this->texts->forLocale($this->blogId, 'en'))->toBe([]);
});

it('refuses wording for a theme the blog does not use', function () {
    $this->settings->updateForBlog($this->blogId, ['theme' => 'folio']);

    [$status] = ($this->save)(['wording_locale' => 'en', 'wording' => ['nocturne::home.edition' => 'The Morning Edition']]);

    expect($status)->toBe(422);
});
