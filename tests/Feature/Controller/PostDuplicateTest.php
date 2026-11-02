<?php

declare(strict_types=1);

use App\Controllers\Dashboard\PostDuplicateController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\TagModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Duplicating a post offers a free name and address, then makes a fresh draft
 * that keeps the writing and tags but none of the original's history.
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

    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->posts = new PostModel($this->db);
    $tags = new TagModel($this->db);

    $this->ownerId = UserFactory::new($this->users)->create();
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->blogId, []);

    $this->postId = PostFactory::new($this->posts)
        ->withAttributes([
            'blog_id' => $this->blogId,
            'author_id' => $this->ownerId,
            'title' => 'Field notes',
            'slug' => 'field-notes',
            'content' => '<p>What we saw on the hill.</p>',
            'meta_title' => 'Notes from the hill',
            'is_featured' => 1,
        ])
        ->published()
        ->create();
    $tags->syncForPost($this->postId, $this->blogId, ['hills', 'walks']);

    $this->signIn = function (int $userId): void {
        $email = faker()->unique()->safeEmail();
        $this->db->query('UPDATE users SET email = ?, password = ? WHERE id = ?', [$email, password_hash('password123', PASSWORD_DEFAULT), $userId]);
        expect(auth()->login($email, 'password123'))->toBeTrue();
    };

    $this->call = function (string $action, string $method, array $fields = []) {
        $request = makeRequest('/dashboard/post/'.$this->postId.'/duplicate', $method, $fields + [
            '_token' => App::container()->get(Csrf::class)->getToken(),
        ]);
        $controller = App::container()->get(PostDuplicateController::class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());

        return callController($controller, $action, $request, (string) $this->postId);
    };

    $this->suggest = function (): array {
        $response = ($this->call)('suggest', 'GET');

        return [$response->getStatusCode(), json_decode($response->getBody(), true)];
    };

    $this->copies = fn (): array => $this->db->query(
        'SELECT * FROM posts WHERE blog_id = ? AND id <> ? ORDER BY id',
        [$this->blogId, $this->postId]
    )->fetchAll();
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('offers the first free copy name and address', function () {
    ($this->signIn)($this->ownerId);

    [$status, $body] = ($this->suggest)();

    expect($status)->toBe(200)
        ->and($body)->toBe(['title' => 'Field notes (copy)', 'slug' => 'field-notes-copy']);
});

it('numbers the next copy once the first exists', function () {
    ($this->signIn)($this->ownerId);
    ($this->call)('store', 'POST', ['title' => 'Field notes (copy)', 'slug' => 'field-notes-copy']);

    [, $body] = ($this->suggest)();

    expect($body)->toBe(['title' => 'Field notes (copy 2)', 'slug' => 'field-notes-copy-2']);
});

it('makes a fresh draft that keeps the writing and tags', function () {
    ($this->signIn)($this->ownerId);

    ($this->call)('store', 'POST', ['title' => 'Field notes (copy)', 'slug' => 'field-notes-copy']);

    $copies = ($this->copies)();
    expect($copies)->toHaveCount(1);
    $copy = $copies[0];
    expect($copy['title'])->toBe('Field notes (copy)')
        ->and($copy['slug'])->toBe('field-notes-copy')
        ->and($copy['status'])->toBe('draft')
        ->and($copy['content'])->toBe('<p>What we saw on the hill.</p>')
        ->and($copy['meta_title'])->toBe('Notes from the hill')
        ->and((int) $copy['is_featured'])->toBe(0)
        ->and($copy['published_at'])->toBeNull()
        ->and(array_column($this->posts->tags((int) $copy['id']), 'name'))->toEqualCanonicalizing(['hills', 'walks']);
});

it('gives a taken address the next free number', function () {
    ($this->signIn)($this->ownerId);

    ($this->call)('store', 'POST', ['title' => 'Named by hand', 'slug' => 'field-notes']);

    expect(($this->copies)()[0]['slug'])->toBe('field-notes-2');
});

it('makes whoever copied it the author', function () {
    $editorId = UserFactory::new($this->users)->create();
    $this->blogs->addUserToBlog($this->blogId, $editorId, 'editor', $this->ownerId);
    ($this->signIn)($editorId);

    ($this->call)('store', 'POST', ['title' => 'Field notes (copy)', 'slug' => 'field-notes-copy']);

    expect((int) ($this->copies)()[0]['author_id'])->toBe($editorId);
});

it('refuses to copy a post a moderator hid', function () {
    $this->db->query("UPDATE posts SET status = 'moderated' WHERE id = ?", [$this->postId]);
    ($this->signIn)($this->ownerId);

    [$status] = ($this->suggest)();
    ($this->call)('store', 'POST', ['title' => 'Field notes (copy)', 'slug' => 'field-notes-copy']);

    expect($status)->toBe(409)
        ->and(($this->copies)())->toBeEmpty();
});

it('refuses someone outside the blog', function () {
    ($this->signIn)(UserFactory::new($this->users)->create());

    expect(fn () => ($this->suggest)())->toThrow(UnauthorizedException::class)
        ->and(($this->copies)())->toBeEmpty();
});
