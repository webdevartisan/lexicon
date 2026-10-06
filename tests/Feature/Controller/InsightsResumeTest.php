<?php

declare(strict_types=1);

use App\Controllers\Dashboard\InsightsController;
use App\Models\BlogModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Database;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * A blog's Insights link opens the page the user looked at last, on any blog
 * where they may still open it, and Overview keeps an address of its own.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }

    $this->db = App::container()->get(Database::class);

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
    $hostId = UserFactory::new($users)->create();

    $this->ownBlog = BlogFactory::new($blogs)->published()->create($this->userId);
    $this->otherBlog = BlogFactory::new($blogs)->published()->create($hostId);
    $this->db->query(
        "INSERT INTO blog_users (blog_id, user_id, role, assigned_by, assigned_at, is_active) VALUES (?, ?, 'author', ?, NOW(), 1)",
        [$this->otherBlog, $this->userId, $hostId]
    );

    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public ?string $template = null;

        /** @var array<string, mixed> */
        public array $data = [];

        public function render(string $template, array $data = []): string
        {
            $this->template = $template;
            $this->data = $data;

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

    $this->open = function (string $action, array $query, string ...$arguments) {
        $controller = App::container()->get(InsightsController::class);
        setupController($controller, makeRequest('/dashboard/blog/insights', 'GET', [], $query), $this->viewer);

        return $controller->{$action}(...$arguments);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

test('Overview has its own address', function () {
    ($this->open)('page', [], (string) $this->ownBlog, 'overview');

    expect($this->viewer->template)->toBe('insights.overview')
        ->and($this->viewer->data['pagePath'])->toBe("/dashboard/blog/{$this->ownBlog}/insights/overview");
});

test('the Insights link goes back to the last page', function () {
    ($this->open)('page', ['range' => '7d'], (string) $this->ownBlog, 'technical');

    $resumed = ($this->open)('index', [], (string) $this->ownBlog);

    expect($resumed->getStatusCode())->toBe(302)
        ->and($resumed->getHeader('Location'))->toContain("/dashboard/blog/{$this->ownBlog}/insights/technical");
});

test('on a blog where that page is not open to the user, the link shows Overview', function () {
    ($this->open)('page', [], (string) $this->ownBlog, 'technical');

    ($this->open)('index', [], (string) $this->otherBlog);

    expect($this->viewer->template)->toBe('insights.overview')
        ->and($this->viewer->data['scope'])->toBe('author');
});
