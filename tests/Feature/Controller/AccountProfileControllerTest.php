<?php

declare(strict_types=1);

use App\Controllers\AccountProfileController;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use App\Models\UserSocialLinkModel;
use App\Services\DisplayNameService;
use App\Services\PublicCacheInvalidator;
use App\Services\UploadService;
use Framework\Core\Response;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;

/**
 * AccountProfileController is the front rewrite of Dashboard\ProfileController.
 * The behaviour pinned here is the move to /account/profile and that a save
 * still persists. Real database and a real Auth session, view layer stubbed.
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

    $this->userModel = new UserModel($this->db);

    $password = 'password123';
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->userModel)
        ->withAttributes([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ])
        ->create();

    expect(auth()->login($email, $password))->toBeTrue();

    $c = \Framework\Core\App::container();
    $this->controller = new AccountProfileController(
        $this->userModel,
        new UserProfileModel($this->db),
        new UserSocialLinkModel($this->db),
        $c->get(UploadService::class),
        $c->get(PublicCacheInvalidator::class),
        $c->get(DisplayNameService::class),
    );

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public ?string $capturedTemplate = null;

        public array $capturedData = [];

        public function render(string $template, array $data = []): string
        {
            $this->capturedTemplate = $template;
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
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

test('the account route group sits behind auth middleware', function () {
    $routes = file_get_contents(ROOT_PATH.'/config/routes.php');
    // The /account group declares auth, so a guest is redirected before any action.
    expect($routes)->toMatch("/'prefix'\\s*=>\\s*'\\/account',\\s*'middleware'\\s*=>\\s*\\['auth'\\]/");
});

test('edit renders the account profile view', function () {
    $request = makeRequest('/account/profile', 'GET');
    setupController($this->controller, $request, $this->viewer);

    $response = $this->controller->edit();

    expect($response)->toBeInstanceOf(Response::class);
    expect($this->viewer->capturedTemplate)->toBe('public.Account.profile');
});

test('a profile save persists and redirects back to the profile page', function () {
    $token = csrf()->getToken();
    $request = makeRequest('/account/profile/update', 'POST', [
        '_token' => $token,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'bio' => 'Mathematician.',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'update', $request);

    expect($response->getStatusCode())->toBe(302);
    expect($response->getHeader('Location'))->toContain('/account/profile');

    $saved = $this->userModel->findById($this->userId);
    expect($saved['first_name'])->toBe('Ada');
    expect($saved['last_name'])->toBe('Lovelace');
});
