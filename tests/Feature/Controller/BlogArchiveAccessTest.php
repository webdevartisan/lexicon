<?php

declare(strict_types=1);

use App\Controllers\Dashboard\BlogArchiveController;
use App\Models\BlogModel;
use App\Models\UserModel;
use App\Services\BlogArchiveRateLimiter;
use App\Services\TranslationService;
use Framework\Core\App;
use Framework\Core\Request;
use Framework\Exceptions\CsrfTokenException;
use Framework\Exceptions\UnauthorizedException;
use Framework\Helpers\RateLimiter;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Framework\Security\CsrfMiddleware;
use Framework\Session;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\MiddlewareTestHelper;
use Tests\Helpers\ThrottleTestHelper;

/**
 * Backup & restore is the owner's alone. An export is every post including drafts,
 * and an import can overwrite them, so no delegated role may reach any of it, and
 * hiding the sidebar link is not the control.
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
    $blogs = new BlogModel($this->db);
    $this->ownerId = UserFactory::new($users)->create();
    $this->blogId = BlogFactory::new($blogs)->published()->create($this->ownerId);

    $this->loginAs = function (int $userId): void {
        $email = faker()->unique()->safeEmail();
        $this->db->query('UPDATE users SET email = ?, password = ? WHERE id = ?', [$email, password_hash('password123', PASSWORD_DEFAULT), $userId]);
        expect(auth()->login($email, 'password123'))->toBeTrue();
    };

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public function render(string $template, array $data = []): string
        {
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

    $this->controller = function (string $path): BlogArchiveController {
        $token = App::container()->get(Csrf::class)->getToken();
        $request = makeRequest($path, 'POST', [
            '_token' => $token,
            'import_id' => str_repeat('0', 32),
            'on_clash' => 'skip',
        ]);
        $controller = App::container()->get(BlogArchiveController::class);
        setupController($controller, $request, $this->viewer);

        return $controller;
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('refuses every archive action to a collaborator who is not the owner', function (string $role) {
    $memberId = UserFactory::new(new UserModel($this->db))->create();
    (new BlogModel($this->db))->addUserToBlog($this->blogId, $memberId, $role, $this->ownerId);
    ($this->loginAs)($memberId);

    foreach (['index', 'export', 'preview', 'confirm', 'cancel'] as $action) {
        $controller = ($this->controller)('/dashboard/blog/'.$this->blogId.'/backup');

        expect(fn () => $controller->$action((string) $this->blogId))->toThrow(UnauthorizedException::class);
    }
})->with(['editor', 'author']);

it('lets the owner open the page', function () {
    ($this->loginAs)($this->ownerId);

    $response = ($this->controller)('/dashboard/blog/'.$this->blogId.'/backup')->index((string) $this->blogId);

    expect($response->getStatusCode())->toBe(200);
});

it('rejects an archive POST without a CSRF token before it reaches the controller', function (string $action) {
    $session = App::container()->get(Session::class);
    $middleware = new CsrfMiddleware(new Csrf($session), $session, new TranslationService('en'));
    $reached = false;

    $request = new Request('/dashboard/blog/'.$this->blogId.'/backup/'.$action, 'POST', [], ['include_drafts' => '1'], [], [], [], []);

    $handler = MiddlewareTestHelper::createTrackingHandler($reached);

    expect(fn () => $middleware->process($request, $handler))
        ->toThrow(CsrfTokenException::class)
        ->and($reached)->toBeFalse();
})->with(['export', 'import/preview', 'import/confirm', 'import/cancel']);

it('throttles exports past the hourly allowance', function () {
    // The test environment runs with caching off, so the limiter gets an in-memory cache.
    $limiter = new BlogArchiveRateLimiter(new RateLimiter(ThrottleTestHelper::fakeCache(), 900));
    $userId = 42;

    for ($i = 0; $i < 10; $i++) {
        $limiter->hit($userId, 'export');
    }

    expect($limiter->tooManyAttempts($userId, 'export'))->toBeTrue()
        ->and($limiter->tooManyAttempts($userId, 'import'))->toBeFalse();
});
