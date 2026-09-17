<?php

declare(strict_types=1);

use App\Controllers\AccountDeletionController;
use App\Models\UserModel;
use App\Services\AccountErasureSchedulerService;
use App\Services\AccountErasureService;
use App\Services\PasswordConfirmRateLimiter;
use Framework\Helpers\RateLimiter;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;
use Tests\Helpers\ThrottleTestHelper;

/**
 * The erasure service is a mock: storage/uploads/ is shared with development,
 * so the real one would delete real files.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = \Framework\Core\App::container()->get(\Framework\Database::class);
    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    $this->users = new UserModel($this->db);
    $this->password = 'correct-horse-battery';
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->users)
        ->withAttributes([
            'email' => $email,
            'password' => password_hash($this->password, PASSWORD_DEFAULT),
        ])
        ->create();

    expect(auth()->login($email, $this->password))->toBeTrue();

    $this->erasure = Mockery::mock(AccountErasureService::class);
    $this->erasure->shouldReceive('blockers')->andReturn(['last_administrator' => false, 'shared_blogs' => [], 'reported_content' => false])->byDefault();
    $this->erasure->shouldReceive('canErase')->andReturn(true)->byDefault();

    $this->scheduler = Mockery::mock(AccountErasureSchedulerService::class);

    $this->throttle = new PasswordConfirmRateLimiter(new RateLimiter(ThrottleTestHelper::fakeCache()));

    $this->controller = new AccountDeletionController($this->users, $this->erasure, $this->scheduler, $this->throttle);

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public ?string $capturedTemplate = null;

        /** @var array<string, mixed> */
        public array $capturedData = [];

        public function render(string $template, array $data = []): string
        {
            $this->capturedTemplate = $template;
            $this->capturedData = $data;

            return 'mocked';
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
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
    Mockery::close();
});

test('deletion is a POST action, reached from a GET confirm page', function () {
    $routes = file_get_contents(ROOT_PATH.'/config/routes.php');
    expect($routes)->toContain("\$r->add('/delete', ['controller' => 'AccountDeletionController', 'action' => 'confirm', 'method' => 'GET'])");
    expect($routes)->toContain("\$r->add('/delete', ['controller' => 'AccountDeletionController', 'action' => 'destroy', 'method' => 'POST'])");

    $source = file_get_contents(ROOT_PATH.'/src/App/Controllers/AccountDeletionController.php');
    expect($source)->toContain('csrf()->assertValid');
});

test('a wrong password cancels the deletion', function () {
    $this->scheduler->shouldNotReceive('schedule');

    $request = makeRequest('/account/delete', 'POST', [
        '_token' => csrf()->getToken(),
        'password' => 'not-my-password',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'destroy', $request);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toContain('/account/preferences');
});

test('a blocked account goes back to the confirm page instead of being erased', function () {
    $this->erasure->shouldReceive('canErase')->andReturn(false);
    $this->scheduler->shouldNotReceive('schedule');

    $request = makeRequest('/account/delete', 'POST', [
        '_token' => csrf()->getToken(),
        'password' => $this->password,
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'destroy', $request);

    expect($response->getHeader('Location'))->toContain('/account/delete');
});

test('a correct password schedules erasure and signs out', function () {
    $this->scheduler->shouldReceive('schedule')->once()->with($this->userId, $this->userId, Mockery::type('string'));

    $request = makeRequest('/account/delete', 'POST', [
        '_token' => csrf()->getToken(),
        'password' => $this->password,
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'destroy', $request);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toMatch('#/en/?$#')
        ->and(auth()->check())->toBeFalse();
});

test('the confirm page shows what blocks deletion', function () {
    $blockers = ['last_administrator' => false, 'shared_blogs' => [['id' => 7, 'blog_name' => 'Team blog']]];
    $this->erasure->shouldReceive('blockers')->andReturn($blockers);
    $this->erasure->shouldReceive('canErase')->andReturn(false);

    $request = makeRequest('/account/delete', 'GET');
    setupController($this->controller, $request, $this->viewer);

    $this->controller->confirm();

    expect($this->viewer->capturedTemplate)->toBe('public.Account.delete')
        ->and($this->viewer->capturedData['blockers'])->toBe($blockers)
        ->and($this->viewer->capturedData['canDelete'])->toBeFalse();
});
