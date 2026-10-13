<?php

declare(strict_types=1);

use App\Controllers\Dashboard\HomeController;
use App\Models\BlogModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * Any blog the user may view can become the active one, owned or shared; a
 * blog they cannot view is refused and leaves the preference alone.
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

    $users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->preferences = new UserPreferencesModel($this->db);

    $this->ownerId = UserFactory::new($users)->create();
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($users)
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();

    $this->ownBlogId = BlogFactory::new($this->blogs)->published()->create($this->userId);
    $this->sharedBlogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    $this->blogs->addUserToBlog($this->sharedBlogId, $this->userId, 'author', $this->ownerId);
    $this->foreignBlogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    $this->preferences->setDefaultBlogId($this->userId, $this->ownBlogId);

    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->activate = function (int $blogId, ?string $returnTo = null) {
        $request = makeRequest('/dashboard/active-blog', 'POST', ['blog' => (string) $blogId] + ($returnTo === null ? [] : ['return_to' => $returnTo]));
        $controller = App::container()->get(HomeController::class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());

        return $controller->setActiveBlog();
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('makes a shared blog active', function () {
    $response = ($this->activate)($this->sharedBlogId, '/dashboard');

    expect($this->preferences->getDefaultBlogId($this->userId))->toBe($this->sharedBlogId)
        ->and($response->getHeader('Location'))->toMatch('#/dashboard$#');
});

it('refuses a blog the user cannot view and keeps the active one', function () {
    $response = ($this->activate)($this->foreignBlogId, '/dashboard');

    expect($this->preferences->getDefaultBlogId($this->userId))->toBe($this->ownBlogId)
        ->and($response->getHeader('Location'))->toContain('/dashboard/blog');
});

it('refuses a blog that does not exist', function () {
    ($this->activate)(999999999, '/dashboard');

    expect($this->preferences->getDefaultBlogId($this->userId))->toBe($this->ownBlogId);
});

it('works without a return_to field', function () {
    $response = ($this->activate)($this->sharedBlogId);

    expect($response->getStatusCode())->toBe(302)
        ->and($this->preferences->getDefaultBlogId($this->userId))->toBe($this->sharedBlogId);
});

it('keeps the page when the new role allows everything the old one did', function () {
    $otherOwned = BlogFactory::new($this->blogs)->published()->create($this->userId);

    $response = ($this->activate)($otherOwned, '/dashboard/blog/'.$this->ownBlogId.'/settings');

    expect($response->getHeader('Location'))->toContain('/dashboard/blog/'.$otherOwned.'/settings');
});

it('falls back to the Overview when the new role could not open the page', function () {
    $response = ($this->activate)($this->sharedBlogId, '/dashboard/blog/'.$this->ownBlogId.'/settings');

    expect($response->getHeader('Location'))->toMatch('#/dashboard$#');
});

it('never redirects off the site', function () {
    $response = ($this->activate)($this->sharedBlogId, '//evil.example/path');

    expect($response->getHeader('Location'))->not->toContain('evil.example');
});
