<?php

declare(strict_types=1);

use App\Controllers\Dashboard\PostController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * Autosave writes into the blog the form is for, and a date it cannot read is
 * reported without losing the rest of the draft.
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
    $blogs = new BlogModel($this->db);
    $users = new UserModel($this->db);

    $ownerId = UserFactory::new($users)->create();
    $email = faker()->unique()->safeEmail();
    $this->authorId = UserFactory::new($users)
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();

    $this->sharedBlogId = BlogFactory::new($blogs)->published()->create($ownerId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->sharedBlogId, []);
    $blogs->addUserToBlog($this->sharedBlogId, $this->authorId, 'author', $ownerId);
    $this->strangersBlogId = BlogFactory::new($blogs)->published()->create($ownerId);

    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->autosave = function (array $fields): array {
        $request = makeRequest('/dashboard/post/autosave', 'POST', $fields + [
            '_token' => App::container()->get(Csrf::class)->getToken(),
            'title' => 'Autosaved draft',
            'slug' => 'autosaved-draft',
            'content' => '<p>Body</p>',
            'timezone' => 'UTC',
        ]);
        $controller = App::container()->get(PostController::class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());
        $response = callController($controller, 'autosave', $request);

        return [$response->getStatusCode(), json_decode($response->getBody(), true)];
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('creates the draft in the shared blog the form is for, even with no default blog', function () {
    [$status, $body] = ($this->autosave)(['blog_id' => $this->sharedBlogId]);

    expect($status)->toBe(200)
        ->and((int) $this->posts->find($body['id'])['blog_id'])->toBe($this->sharedBlogId);
});

it('refuses to create a draft in a blog the writer does not belong to', function () {
    [$status, $body] = ($this->autosave)(['blog_id' => $this->strangersBlogId]);

    expect($status)->toBe(403)
        ->and($body['success'])->toBeFalse();
});

it('saves the rest of the draft and reports a date it cannot read', function () {
    [$status, $body] = ($this->autosave)(['blog_id' => $this->sharedBlogId, 'published_at' => '18.09.26 25:99']);

    $saved = $this->posts->find($body['id']);
    expect($status)->toBe(200)
        ->and($body['errors']['published_at'][0])->toContain('dd.mm.yy hh:mm')
        ->and($saved['title'])->toBe('Autosaved draft')
        ->and($saved['published_at'])->toBeNull();
});

it('stores a readable date in UTC', function () {
    [, $body] = ($this->autosave)(['blog_id' => $this->sharedBlogId, 'published_at' => '25.12.26 09:30', 'timezone' => 'Europe/Athens']);

    expect($body)->not->toHaveKey('errors')
        ->and((string) $this->posts->find($body['id'])['published_at'])->toBe('2026-12-25 07:30:00');
});
