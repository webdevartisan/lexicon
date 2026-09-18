<?php

declare(strict_types=1);

use App\Controllers\Dashboard\BlogController;
use App\Models\BlogModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Framework\Session;
use Tests\Factories\UserFactory;

/**
 * Blog creation turns any name with letters or numbers into a valid address,
 * and says so clearly when it cannot.
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

    $email = faker()->unique()->safeEmail();
    $userId = UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    $this->db->execute(
        'INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE role_slug = ?',
        [$userId, 'reader']
    );
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->blogs = new BlogModel($this->db);

    $this->create = function (string $name, string $slug) {
        $request = makeRequest('/dashboard/blog/create', 'POST', [
            '_token' => App::container()->get(Csrf::class)->getToken(),
            'name' => $name,
            'slug' => $slug,
            'description' => '',
        ], [], ['HTTP_REFERER' => base_url().'/en/dashboard/blog/new']);

        $controller = App::container()->get(BlogController::class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());

        return callController($controller, 'create', $request);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

function slugErrors(): array
{
    return App::container()->get(Session::class)->get('_errors', [])['slug'] ?? [];
}

it('builds the address from a name wrapped in hyphens when none was typed', function () {
    $name = '------hello'.uniqid().'-------';
    ($this->create)($name, '');

    $expected = slugify($name);
    expect($this->blogs->getBlogBySlug($expected))->not->toBeNull()
        ->and($expected)->not->toStartWith('-')
        ->and($expected)->not->toEndWith('-');
});

it('refuses a name with nothing to build an address from, with a clear message', function (string $name) {
    ($this->create)($name, '');

    expect(implode(' ', slugErrors()))->toContain('could not build a web address');
})->with(['🎉🎉🎉', 'Καλημέρα']);

it('refuses a typed address with invalid characters', function (string $slug) {
    ($this->create)('My blog', $slug);

    expect(implode(' ', slugErrors()))->toContain('lowercase letters, numbers and single hyphens')
        ->and($this->blogs->getBlogBySlug($slug))->toBeNull();
})->with(['-hello-', 'Hello World', 'hello--world', 'héllo', 'hello_world']);
