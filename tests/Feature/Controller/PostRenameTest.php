<?php

declare(strict_types=1);

use App\Controllers\Dashboard\PostRenameController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Renaming from the Posts list changes the title only, for people who may edit
 * the post, and answers in JSON so the page never reloads.
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

    $this->ownerId = UserFactory::new($this->users)->create();
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->blogId, []);

    $this->postId = PostFactory::new($this->posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->ownerId, 'title' => 'Field notes', 'slug' => 'field-notes'])
        ->published()
        ->create();

    $this->signIn = function (int $userId): void {
        $email = faker()->unique()->safeEmail();
        $this->db->query('UPDATE users SET email = ?, password = ? WHERE id = ?', [$email, password_hash('password123', PASSWORD_DEFAULT), $userId]);
        expect(auth()->login($email, 'password123'))->toBeTrue();
    };

    $this->rename = function (string $title, ?string $token = null): array {
        $request = makeRequest('/dashboard/post/'.$this->postId.'/rename', 'POST', [
            '_token' => $token ?? App::container()->get(Csrf::class)->getToken(),
            'title' => $title,
        ]);
        $controller = App::container()->get(PostRenameController::class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());
        $response = callController($controller, 'rename', $request, (string) $this->postId);

        return [$response->getStatusCode(), json_decode($response->getBody(), true)];
    };

    $this->stored = fn (): array => $this->posts->find($this->postId);
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('saves the trimmed title and keeps the address', function () {
    ($this->signIn)($this->ownerId);

    [$status, $body] = ($this->rename)('  Notes from the field  ');

    expect($status)->toBe(200)
        ->and($body['title'])->toBe('Notes from the field')
        ->and(($this->stored)()['title'])->toBe('Notes from the field')
        ->and(($this->stored)()['slug'])->toBe('field-notes');
});

it('refuses a title that is too short and leaves the post alone', function () {
    ($this->signIn)($this->ownerId);

    [$status, $body] = ($this->rename)('x');

    expect($status)->toBe(422)
        ->and($body['error'])->not->toBeEmpty()
        ->and(($this->stored)()['title'])->toBe('Field notes');
});

it('refuses someone who may not edit the post', function () {
    $contributorId = UserFactory::new($this->users)->create();
    $this->blogs->addUserToBlog($this->blogId, $contributorId, 'contributor', $this->ownerId);
    ($this->signIn)($contributorId);

    [$status] = ($this->rename)('Taken over');

    expect($status)->toBe(403)
        ->and(($this->stored)()['title'])->toBe('Field notes');
});

it('refuses a request without a valid token', function () {
    ($this->signIn)($this->ownerId);

    [$status] = ($this->rename)('Notes from the field', 'not-the-token');

    expect($status)->toBe(419)
        ->and(($this->stored)()['title'])->toBe('Field notes');
});
