<?php

declare(strict_types=1);

use App\Controllers\Dashboard\SlugCheckController;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

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
    $blogs = new BlogModel($this->db);
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($users)
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    $strangerId = UserFactory::new($users)->create();

    $this->blogId = BlogFactory::new($blogs)->published()->create($this->userId);
    $this->strangersBlogId = BlogFactory::new($blogs)->published()->create($strangerId);
    $this->strangersBlogSlug = $blogs->getBlog($this->strangersBlogId)->slug();

    PostFactory::new(new PostModel($this->db))
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->userId, 'slug' => 'taken-here', 'excerpt' => ''])
        ->draft()
        ->create();

    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->check = function (array $query): array {
        $request = makeRequest('/dashboard/slug-check', 'GET', [], $query);
        $controller = App::container()->get(SlugCheckController::class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());
        $response = callController($controller, 'check', $request);

        return [$response->getStatusCode(), json_decode($response->getBody(), true)];
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('says a free post address is available', function () {
    [$status, $body] = ($this->check)(['type' => 'post', 'blog_id' => $this->blogId, 'slug' => 'brand-new']);

    expect($status)->toBe(200)
        ->and($body['available'])->toBeTrue()
        ->and($body['saved_as'])->toBe('brand-new');
});

it('tells the writer which numbered address a taken post address will get', function () {
    [, $body] = ($this->check)(['type' => 'post', 'blog_id' => $this->blogId, 'slug' => 'taken-here']);

    expect($body['available'])->toBeFalse()
        ->and($body['saved_as'])->toBe('taken-here-2');
});

it('refuses to check addresses in a blog the user cannot write in', function () {
    [$status, $body] = ($this->check)(['type' => 'post', 'blog_id' => $this->strangersBlogId, 'slug' => 'anything']);

    expect($status)->toBe(403)
        ->and($body)->not->toHaveKey('available');
});

it('reports a badly formed address without looking it up', function () {
    [, $body] = ($this->check)(['type' => 'post', 'blog_id' => $this->blogId, 'slug' => '-bad-']);

    expect($body['available'])->toBeFalse()
        ->and($body['message'])->toContain('lowercase letters')
        ->and($body)->not->toHaveKey('saved_as');
});

it('marks a blog address another blog uses as taken', function () {
    [, $body] = ($this->check)(['type' => 'blog', 'slug' => $this->strangersBlogSlug]);

    expect($body['available'])->toBeFalse()
        ->and($body['message'])->toContain('Another blog already uses this address');
});

it('rejects an unknown kind of address', function () {
    [$status] = ($this->check)(['type' => 'user', 'slug' => 'abc']);

    expect($status)->toBe(400);
});
