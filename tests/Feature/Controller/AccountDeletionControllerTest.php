<?php

declare(strict_types=1);

use App\Controllers\AccountDeletionController;
use App\Models\UserModel;
use App\Services\UserDeletionService;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;

/**
 * Deletion moved to the front. The UserDeletionService is a mock so the REAL
 * uploader never runs: storage/uploads/ is shared with development, and a test
 * that ran the real deleteUserUploads() would wipe real files. Posts and
 * comments are left in place by the service, which the corrected copy promises.
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
    $this->password = 'password123';
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->users)
        ->withAttributes([
            'email' => $email,
            'password' => password_hash($this->password, PASSWORD_DEFAULT),
        ])
        ->create();

    expect(auth()->login($email, $this->password))->toBeTrue();

    $this->deletion = Mockery::mock(UserDeletionService::class);

    $this->controller = new AccountDeletionController($this->users, $this->deletion);

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public ?string $capturedTemplate = null;

        public function render(string $template, array $data = []): string
        {
            $this->capturedTemplate = $template;

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
    // /account/delete: GET => confirm, POST => destroy. No GET performs the delete.
    expect($routes)->toContain("\$r->add('/delete', ['controller' => 'AccountDeletionController', 'action' => 'confirm', 'method' => 'GET'])");
    expect($routes)->toContain("\$r->add('/delete', ['controller' => 'AccountDeletionController', 'action' => 'destroy', 'method' => 'POST'])");

    // destroy() asserts CSRF like every other unsafe action.
    $source = file_get_contents(ROOT_PATH.'/src/App/Controllers/AccountDeletionController.php');
    expect($source)->toContain('csrf()->assertValid');
});

test('a wrong password cancels the deletion and never calls the service', function () {
    $this->deletion->shouldReceive('canDeleteUser')->andReturn(['canDelete' => true, 'reason' => '']);
    $this->deletion->shouldNotReceive('deleteUser');

    $request = makeRequest('/account/delete', 'POST', [
        '_token' => csrf()->getToken(),
        'password' => 'not-my-password',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'destroy', $request);

    expect($response->getStatusCode())->toBe(302);
    expect($response->getHeader('Location'))->toContain('/account/preferences');
});

test('a correct password deletes via the service and the real uploader never runs', function () {
    $this->deletion->shouldReceive('canDeleteUser')->andReturn(['canDelete' => true, 'reason' => '']);
    // deleteUser is the mock, so the real UserDeletionService (which calls
    // deleteUserUploads on storage/uploads/) is never touched.
    $this->deletion->shouldReceive('deleteUser')->once()->with($this->userId);

    $request = makeRequest('/account/delete', 'POST', [
        '_token' => csrf()->getToken(),
        'password' => $this->password,
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'destroy', $request);

    expect($response->getStatusCode())->toBe(302);
    expect($response->getHeader('Location'))->toMatch('#/en/?$#');
});

test('the confirm page renders the corrected delete view', function () {
    $this->deletion->shouldReceive('canDeleteUser')->andReturn(['canDelete' => true, 'reason' => '']);

    $request = makeRequest('/account/delete', 'GET');
    setupController($this->controller, $request, $this->viewer);

    $this->controller->confirm();

    expect($this->viewer->capturedTemplate)->toBe('public.Account.delete');
});
