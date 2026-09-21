<?php

declare(strict_types=1);

use App\Controllers\Dashboard\PostController;
use App\Controllers\Dashboard\PostTranslationController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\PostTranslationModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * A post body that arrives without the editor still reaches the database with its
 * scripting removed, whichever door it came through.
 */
const XSS_BODY = '<p>Real words</p><script>alert(1)</script><img src="/uploads/a.png" onerror="alert(2)">';

beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = App::container()->get(\Framework\Database::class);
    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    $this->posts = new PostModel($this->db);
    $blogs = new BlogModel($this->db);
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->blogId = BlogFactory::new($blogs)->published()->create($this->userId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->blogId, []);

    $this->postId = PostFactory::new($this->posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->userId, 'excerpt' => 'x'])
        ->draft()
        ->create();

    $this->token = App::container()->get(Csrf::class)->getToken();
    $this->viewer = Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();

    $this->post = function (string $action, string $url, array $fields, string ...$args) {
        $request = makeRequest($url, 'POST', $fields + ['_token' => $this->token]);
        $controller = App::container()->get(PostController::class);
        setupController($controller, $request, $this->viewer);

        return callController($controller, $action, $request, ...$args);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('strips scripting from a new post', function () {
    ($this->post)('create', '/dashboard/post/create', [
        'title' => 'Fresh post',
        'slug' => 'fresh-post',
        'content' => XSS_BODY,
        'blog_id' => $this->blogId,
        'intent' => 'save_draft',
        'twitter_card_type' => 'summary_large_image',
    ]);

    $stored = $this->posts->findBy('slug', 'fresh-post')[0]['content'];

    expect($stored)->toContain('Real words')
        ->and($stored)->not->toContain('alert(1)')
        ->and($stored)->not->toContain('onerror');
});

it('strips scripting from an edited post', function () {
    ($this->post)('update', '/dashboard/post/'.$this->postId.'/update', [
        'title' => 'Edited post',
        'content' => XSS_BODY,
        'excerpt' => 'x',
        'intent' => 'save_draft',
        'twitter_card_type' => 'summary_large_image',
    ], (string) $this->postId);

    $stored = $this->posts->find($this->postId)['content'];

    expect($stored)->toContain('Real words')
        ->and($stored)->not->toContain('alert(1)')
        ->and($stored)->not->toContain('onerror');
});

it('strips scripting from an autosaved draft', function () {
    $response = ($this->post)('autosave', '/dashboard/post/autosave', [
        'id' => $this->postId,
        'title' => 'Autosaved post',
        'slug' => 'autosaved-post',
        'content' => XSS_BODY,
    ]);

    expect($response->getStatusCode())->toBe(200);

    $stored = $this->posts->find($this->postId)['content'];

    expect($stored)->toContain('Real words')
        ->and($stored)->not->toContain('alert(1)')
        ->and($stored)->not->toContain('onerror');
});

it('strips scripting from a translation', function () {
    $this->db->query('UPDATE blog_settings SET translations_enabled = 1 WHERE blog_id = ?', [$this->blogId]);

    $request = makeRequest('/dashboard/post/'.$this->postId.'/translations/el/update', 'POST', [
        '_token' => $this->token,
        'title' => 'Μετάφραση',
        'content' => XSS_BODY,
        'excerpt' => '',
    ]);
    $controller = App::container()->get(PostTranslationController::class);
    setupController($controller, $request, $this->viewer);
    callController($controller, 'update', $request, (string) $this->postId, 'el');

    $stored = (string) ((new PostTranslationModel($this->db))->findOne($this->postId, 'el')['content'] ?? '');

    expect($stored)->toContain('Real words')
        ->and($stored)->not->toContain('alert(1)')
        ->and($stored)->not->toContain('onerror');
});

test('every class that stores post content is handed the sanitizer', function (string $class) {
    $takesSanitizer = array_filter(
        (new ReflectionClass($class))->getConstructor()?->getParameters() ?? [],
        static fn (ReflectionParameter $p): bool => (string) $p->getType() === \App\Services\PostContentSanitizer::class
    );

    expect($takesSanitizer)->not->toBeEmpty($class.' can store post content without sanitizing it.');
})->with([
    \App\Controllers\Dashboard\PostController::class,
    \App\Controllers\Dashboard\PostTranslationController::class,
    \App\Controllers\Admin\PostController::class,
    \App\Services\PostAutosaveService::class,
]);
