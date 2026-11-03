<?php

declare(strict_types=1);

use App\Controllers\AccountProfileController;
use App\Models\GuidedSetupMarkModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Models\UserSocialLinkModel;
use App\Services\DisplayNameService;
use App\Services\PublicCacheInvalidator;
use App\Services\UploadService;
use App\Services\UserHandleValidator;
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
    $this->profiles = new UserProfileModel($this->db);
    $this->controller = new AccountProfileController(
        $this->userModel,
        $this->profiles,
        new UserSocialLinkModel($this->db),
        new UserPreferencesModel($this->db),
        $c->get(UploadService::class),
        $c->get(PublicCacheInvalidator::class),
        $c->get(DisplayNameService::class),
        $c->get(UserHandleValidator::class),
        new GuidedSetupMarkModel($this->db),
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
    expect($routes)->toMatch("/'prefix'\\s*=>\\s*'\\/account',\\s*'middleware'\\s*=>\\s*\\['auth'[,\\]]/");
});

test('edit renders the account profile view', function () {
    $request = makeRequest('/account/profile', 'GET');
    setupController($this->controller, $request, $this->viewer);

    $response = $this->controller->edit();

    expect($response)->toBeInstanceOf(Response::class);
    expect($this->viewer->capturedTemplate)->toBe('public.Account.profile');
});

test('editName renders the name view', function () {
    $request = makeRequest('/account/name', 'GET');
    setupController($this->controller, $request, $this->viewer);

    $response = $this->controller->editName();

    expect($response)->toBeInstanceOf(Response::class);
    expect($this->viewer->capturedTemplate)->toBe('public.Account.name');
});

test('a profile save persists the about fields and returns to the profile page', function () {
    $request = makeRequest('/account/profile/update', 'POST', [
        '_token' => csrf()->getToken(),
        'section' => 'profile',
        'bio' => 'Mathematician.',
        'occupation' => 'Analyst',
        'location' => 'London',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'update', $request);

    expect($response->getStatusCode())->toBe(302);
    expect($response->getHeader('Location'))->toContain('/account/profile');

    $profile = $this->profiles->findOrCreate($this->userId);
    expect($profile['bio'])->toBe('Mathematician.');
    expect($profile['occupation'])->toBe('Analyst');
    expect($profile['location'])->toBe('London');
});

test('a name save persists the names and the tag and returns to the name page', function () {
    $request = makeRequest('/account/profile/update', 'POST', [
        '_token' => csrf()->getToken(),
        'section' => 'name',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'handle' => 'ada-lovelace',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'update', $request);

    expect($response->getStatusCode())->toBe(302);
    expect($response->getHeader('Location'))->toContain('/account/name');

    $saved = $this->userModel->findById($this->userId);
    expect($saved['first_name'])->toBe('Ada');
    expect($saved['last_name'])->toBe('Lovelace');
    expect($saved['handle'])->toBe('ada-lovelace');
});

test('a name save leaves the profile visibility alone', function () {
    $this->profiles->upsert($this->userId, ['is_public' => 1]);

    $request = makeRequest('/account/profile/update', 'POST', [
        '_token' => csrf()->getToken(),
        'section' => 'name',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'handle' => 'ada-lovelace',
    ]);
    setupController($this->controller, $request, $this->viewer);

    callController($this->controller, 'update', $request);

    expect((int) $this->profiles->findOrCreate($this->userId)['is_public'])->toBe(1);
});
