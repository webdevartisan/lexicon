<?php

declare(strict_types=1);

use App\Controllers\Dashboard\BlogController;
use App\Models\BlogModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * My blogs offers "Create blog" only to accounts allowed to start one.
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

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public array $capturedData = [];

        public function render(?string $template, array $data = []): string
        {
            $this->capturedData = $data;

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

    $this->myBlogs = function (string $roleSlug): array {
        $email = faker()->unique()->safeEmail();
        $userId = UserFactory::new(new UserModel($this->db))
            ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
            ->create();
        $this->db->execute(
            'INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE role_slug = ?',
            [$userId, $roleSlug]
        );
        BlogFactory::new(new BlogModel($this->db))->create($userId);
        expect(auth()->login($email, 'password123'))->toBeTrue();

        $controller = App::container()->get(BlogController::class);
        setupController($controller, makeRequest('/dashboard/blog', 'GET'), $this->viewer);
        $controller->index();

        return $this->viewer->capturedData;
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

test('an account that may start blogs gets the Create blog button', function () {
    expect(($this->myBlogs)('reader'))->toHaveKey('can_create_blog', true);
});
