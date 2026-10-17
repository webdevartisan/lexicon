<?php

declare(strict_types=1);

use App\Controllers\Dashboard\GuidedSetupController;
use App\Controllers\Dashboard\HomeController;
use App\Models\BlogModel;
use App\Models\GuidedSetupMarkModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\GuidedSetupService;
use App\Services\TranslationService;
use Framework\Core\App;
use Framework\Core\Request;
use Framework\Exceptions\CsrfTokenException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Framework\Security\CsrfMiddleware;
use Framework\Session;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\MiddlewareTestHelper;

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
    $this->blogs = new BlogModel($this->db);
    $this->marks = new GuidedSetupMarkModel($this->db);

    $this->loginAs = function (int $userId) {
        $email = $this->users->find($userId)['email'];
        $this->db->execute('UPDATE users SET password = ? WHERE id = ?', [password_hash('password123', PASSWORD_DEFAULT), $userId]);
        expect(auth()->login($email, 'password123'))->toBeTrue();
    };

    $this->ownerId = UserFactory::new($this->users)->create();
    $this->blogId = BlogFactory::new($this->blogs)->draft()->withAttributes(['description' => ''])->create($this->ownerId);
    ($this->loginAs)($this->ownerId);

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

    // Each call gets a fresh controller with a fetch-style request
    $this->call = function (string $action, ?int $blogId = null) {
        $blogId ??= $this->blogId;
        $controller = new GuidedSetupController(App::container()->get(GuidedSetupService::class), $this->marks);
        $request = new Request("/dashboard/setup/{$blogId}/{$action}", 'POST', [], [], [], [], ['REMOTE_ADDR' => '127.0.0.1'], ['x-requested-with' => 'XMLHttpRequest']);
        setupController($controller, $request, $this->viewer);

        return $controller->$action((string) $blogId);
    };

    $this->body = fn ($response) => json_decode((string) $response->getBody(), true);
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

test('hide then show round-trips', function () {
    $hidden = ($this->call)('hide');

    expect($hidden->getStatusCode())->toBe(200)
        ->and(($this->body)($hidden))->toBe(['state' => 'hidden', 'done' => 1, 'total' => 6]);

    $shown = ($this->call)('show');

    expect(($this->body)($shown)['state'])->toBe('open')
        ->and($this->marks->marksFor($this->ownerId, $this->blogId))->toBe([]);
});

test('dismiss refused before finished', function () {
    $response = ($this->call)('dismiss');

    expect($response->getStatusCode())->toBe(422)
        ->and($this->marks->marksFor($this->ownerId, $this->blogId))->toBe([]);
});

test('copied marks link_copied', function () {
    $response = ($this->call)('copied');

    expect(($this->body)($response)['done'])->toBe(2)
        ->and($this->marks->marksFor($this->ownerId, $this->blogId))->toBe(['link_copied' => true]);
});

test('stranger gets 403', function () {
    $strangerId = UserFactory::new($this->users)->create();
    auth()->logout();
    ($this->loginAs)($strangerId);

    foreach (['hide', 'show', 'dismiss', 'copied'] as $action) {
        expect(($this->call)($action)->getStatusCode())->toBe(403);
    }

    expect($this->marks->marksFor($strangerId, $this->blogId))->toBe([]);
});

test('post without csrf gets 419', function () {
    $session = App::container()->get(Session::class);
    $middleware = new CsrfMiddleware(new Csrf($session), $session, new TranslationService('en'));
    $reached = false;
    $request = new Request("/dashboard/setup/{$this->blogId}/hide", 'POST', [], [], [], [], [], []);

    expect(fn () => $middleware->process($request, MiddlewareTestHelper::createTrackingHandler($reached)))
        ->toThrow(CsrfTokenException::class)
        ->and($reached)->toBeFalse();
});

test('overview renders the card for a new owner', function () {
    (new UserPreferencesModel($this->db))->setDefaultBlogId($this->ownerId, $this->blogId);

    $controller = App::container()->get(HomeController::class);
    setupController($controller, makeRequest('/dashboard', 'GET'), $this->viewer);
    $controller->index();

    expect($this->viewer->capturedData['setup']['total'])->toBe(6)
        ->and($this->viewer->capturedData['setup']['done'])->toBe(1)
        ->and($this->viewer->capturedData['setup']['state'])->toBe('open');
});
