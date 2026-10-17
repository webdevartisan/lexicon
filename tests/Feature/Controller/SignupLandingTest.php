<?php

declare(strict_types=1);

use App\Controllers\WelcomeController;
use App\Models\BlogModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * The "What would you like to do?" page a new account lands on when sign-up
 * didn't say where to go. RegisterControllerTest covers the redirects into it.
 */
beforeEach(function () {
    // auth() resolves users through the container, so run on its connection
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
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->users)
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public ?string $template = null;

        public function render(?string $template, array $data = []): string
        {
            $this->template = $template;

            return 'mocked view';
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

    $this->open = function () {
        $controller = App::container()->get(WelcomeController::class);
        setupController($controller, makeRequest('/welcome', 'GET'), $this->viewer);

        return $controller->index();
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

test('welcome shows the two choices to a new account', function () {
    $response = ($this->open)();

    expect($response->getStatusCode())->toBe(200)
        ->and($this->viewer->template)->toBe('public.Welcome.index');
});

test('welcome redirects owners to the dashboard', function () {
    BlogFactory::new(new BlogModel($this->db))->create($this->userId);

    $response = ($this->open)();

    expect($response->getHeader('Location'))->toEndWith('/dashboard');
});
