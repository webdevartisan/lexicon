<?php

declare(strict_types=1);

use App\Controllers\Dashboard\PostController;
use App\Controllers\Dashboard\PostReviewController;
use App\Controllers\Dashboard\PostStatusController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\ValueObjects\PostFieldRules;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Framework\Session;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * A draft can be saved with only a title, and no route takes a post with no
 * text out of draft: not the editor, the post list or the review page.
 */
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
    $email = faker()->unique()->safeEmail();
    $ownerId = UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($ownerId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->blogId, []);
    $this->db->query('UPDATE blog_settings SET workflow_enabled = 1 WHERE blog_id = ?', [$this->blogId]);

    $this->draft = fn (string $content): int => PostFactory::new($this->posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $ownerId, 'content' => $content])
        ->draft()
        ->create();

    $this->call = function (string $class, string $action, string $url, array $fields = [], string ...$args) {
        // The editor's "in" rule refuses a missing card type, so the form always sends one.
        $request = makeRequest($url, 'POST', $fields + [
            '_token' => App::container()->get(Csrf::class)->getToken(),
            'twitter_card_type' => 'summary_large_image',
        ]);
        $controller = App::container()->get($class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());

        return callController($controller, $action, $request, ...$args);
    };

    $this->session = fn (string $key): array => App::container()->get(Session::class)->get($key, []);
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('saves a new draft with only a title', function () {
    ($this->call)(PostController::class, 'create', '/dashboard/post/create', [
        'title' => 'Just a title', 'slug' => 'just-a-title', 'content' => '', 'blog_id' => $this->blogId, 'intent' => 'save_draft',
    ]);

    $saved = $this->posts->findBy('slug', 'just-a-title')[0] ?? null;

    expect($saved)->not->toBeNull()
        ->and($saved['status'])->toBe('draft');
});

it('refuses to publish a new post with no text', function () {
    ($this->call)(PostController::class, 'create', '/dashboard/post/create', [
        'title' => 'Empty launch', 'slug' => 'empty-launch', 'content' => '  ', 'blog_id' => $this->blogId, 'intent' => 'publish',
    ]);

    expect($this->posts->findBy('slug', 'empty-launch'))->toBe([])
        ->and(($this->session)('_errors')['content'][0] ?? '')->toBe(PostFieldRules::TEXT_REQUIRED);
});

it('saves a draft after its text is cleared', function () {
    $id = ($this->draft)('<p>Old words</p>');

    ($this->call)(PostController::class, 'update', '/dashboard/post/'.$id.'/update', [
        'title' => 'Starting over', 'content' => '', 'intent' => 'save_draft',
    ], (string) $id);

    $saved = $this->posts->find($id);
    expect($saved['title'])->toBe('Starting over')
        ->and((string) $saved['content'])->toBe('')
        ->and($saved['status'])->toBe('draft');
});

it('keeps a post with no text in draft whatever the editor asks for', function (array $fields) {
    $id = ($this->draft)('');

    ($this->call)(PostController::class, 'update', '/dashboard/post/'.$id.'/update', $fields + [
        'title' => 'Not yet', 'content' => '', 'timezone' => 'UTC',
    ], (string) $id);

    expect($this->posts->find($id)['status'])->toBe('draft')
        ->and(($this->session)('_errors')['content'][0] ?? '')->toBe(PostFieldRules::TEXT_REQUIRED);
})->with([
    'publish' => [['intent' => 'publish']],
    'schedule' => [['intent' => 'schedule', 'published_at' => '01.01.30 09:00']],
    'submit for review' => [['intent' => 'submit_review']],
]);

it('refuses a quick publish of a post with no text', function () {
    $id = ($this->draft)('');

    ($this->call)(PostStatusController::class, 'publish', '/dashboard/post/'.$id.'/publish', [], (string) $id);

    expect($this->posts->find($id)['status'])->toBe('draft')
        ->and(($this->session)('_flash')['error'] ?? '')->toContain(PostFieldRules::TEXT_REQUIRED);
});

it('leaves posts with no text out of a bulk publish or review', function (string $action, string $moved) {
    $written = ($this->draft)('<p>Ready</p>');
    $empty = ($this->draft)('');

    ($this->call)(PostController::class, 'bulk', '/dashboard/post/bulk', [
        'bulk_action' => $action, 'post_ids' => [$written, $empty],
    ]);

    expect($this->posts->find($written)['status'])->toBe($moved)
        ->and($this->posts->find($empty)['status'])->toBe('draft')
        ->and(implode(' ', ($this->session)('_flash')['success'] ?? []))->toContain('1 left as they are because they have no text yet');
})->with([
    'publish' => ['publish', 'published'],
    'review' => ['review', 'pending'],
]);

it('refuses to send a post with no text for review', function () {
    $id = ($this->draft)('');

    ($this->call)(PostReviewController::class, 'requestReview', '/dashboard/post/'.$id.'/request-review', [], (string) $id);

    $post = $this->posts->find($id);
    expect($post['status'])->toBe('draft')
        ->and($post['workflow_state'])->toBe('draft')
        ->and(($this->session)('_flash')['error'] ?? '')->toContain(PostFieldRules::TEXT_REQUIRED);
});
