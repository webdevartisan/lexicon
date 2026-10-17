<?php

declare(strict_types=1);

use App\Controllers\Dashboard\BlogStartController;
use App\Controllers\Dashboard\HomeController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\GuidedSetupMarkModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use Framework\Core\App;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

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
    $this->settings = new BlogSettingsModel($this->db);

    $this->loginAs = function (int $userId) {
        $email = $this->users->find($userId)['email'];
        $this->db->execute('UPDATE users SET password = ? WHERE id = ?', [password_hash('password123', PASSWORD_DEFAULT), $userId]);
        expect(auth()->login($email, 'password123'))->toBeTrue();
    };

    $this->ownerId = UserFactory::new($this->users)->create();
    $this->blogId = BlogFactory::new($this->blogs)->draft()->withAttributes(['description' => ''])->create($this->ownerId);
    $this->settings->createDefaultForBlog($this->blogId, []);
    ($this->loginAs)($this->ownerId);

    $this->viewer = Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();

    $this->call = function (string $action, string $method = 'GET', array $post = []) {
        $post = $method === 'POST' ? $post + ['_token' => App::container()->get(Csrf::class)->getToken()] : [];
        $request = makeRequest("/dashboard/blog/{$this->blogId}/start", $method, $post);
        $controller = App::container()->get(BlogStartController::class);
        setupController($controller, $request, $this->viewer);

        return callController($controller, $action, $request, (string) $this->blogId);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

test('about saves description and goes to look', function () {
    $response = ($this->call)('saveAbout', 'POST', ['description' => 'Notes on slow travel']);

    expect($response->getHeader('Location'))->toContain("/dashboard/blog/{$this->blogId}/start/look")
        ->and($this->blogs->getBlog($this->blogId)->description())->toBe('Notes on slow travel');
});

test('about skip leaves description empty', function () {
    $response = ($this->call)('saveAbout', 'POST', ['description' => 'ignored', 'intent' => 'skip']);

    expect($response->getHeader('Location'))->toContain("/dashboard/blog/{$this->blogId}/start/look")
        ->and($this->blogs->getBlog($this->blogId)->description())->toBe('');
});

test('look activates theme and marks look_chosen', function () {
    $response = ($this->call)('saveLook', 'POST', ['theme' => 'offset']);

    expect($response->getHeader('Location'))->toContain("/dashboard/post/new?blog_id={$this->blogId}")
        ->and($this->settings->findByBlogId($this->blogId)['theme'])->toBe('offset')
        ->and((new GuidedSetupMarkModel($this->db))->marksFor($this->ownerId, $this->blogId))->toHaveKey('look_chosen');
});

test('look rejects an unknown theme key', function () {
    $response = ($this->call)('saveLook', 'POST', ['theme' => 'not-a-theme']);

    expect($response->getHeader('Location'))->toContain("/dashboard/blog/{$this->blogId}/start/look")
        ->and($this->settings->findByBlogId($this->blogId)['theme'])->toBe('folio')
        ->and((new GuidedSetupMarkModel($this->db))->marksFor($this->ownerId, $this->blogId))->toBe([]);
});

test('stranger gets 403 on every start route', function () {
    $strangerId = UserFactory::new($this->users)->create();
    auth()->logout();
    ($this->loginAs)($strangerId);

    foreach ([['about', 'GET'], ['saveAbout', 'POST'], ['look', 'GET'], ['saveLook', 'POST']] as [$action, $method]) {
        expect(fn () => ($this->call)($action, $method, ['description' => 'x', 'theme' => 'offset']))
            ->toThrow(UnauthorizedException::class);
    }

    expect($this->blogs->getBlog($this->blogId)->description())->toBe('');
});

test('abandoning after step one leaves a usable draft blog', function () {
    (new UserPreferencesModel($this->db))->setDefaultBlogId($this->ownerId, $this->blogId);
    $viewer = new class() implements TemplateViewerInterface
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

    $controller = App::container()->get(HomeController::class);
    setupController($controller, makeRequest('/dashboard', 'GET'), $viewer);
    $controller->index();

    $start = array_values(array_filter($viewer->capturedData['setup']['steps'], fn ($step) => $step->key === 'start'))[0];

    expect($this->blogs->getBlog($this->blogId)->status())->toBe('draft')
        ->and($start->done)->toBeTrue()
        ->and($viewer->capturedData['setup']['state'])->toBe('open');
});
