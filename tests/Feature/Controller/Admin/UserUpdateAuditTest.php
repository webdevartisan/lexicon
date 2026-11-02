<?php

declare(strict_types=1);

use App\Controllers\Admin\UserController;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use Framework\Database;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;
use Tests\Helpers\DatabaseHelper;

/**
 * Saving the admin user form logs which fields changed. The stored row comes
 * back with native types while the form posts text, so these pin that a save
 * which changes nothing names nothing, and that a real change still shows up.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }

    $this->db = \Framework\Core\App::container()->get(Database::class);

    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    // Looking up the target checks it isn't the shared deleted-user account.
    DatabaseHelper::deletedUser($this->db);

    $this->userModel = new UserModel($this->db);

    $password = 'password123';
    $email = faker()->unique()->safeEmail();
    UserFactory::new($this->userModel)
        ->withAttributes(['email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)])
        ->create();

    expect(auth()->login($email, $password))->toBeTrue();

    $this->targetId = UserFactory::new($this->userModel)
        ->withAttributes(['first_name' => 'Grace', 'last_name' => 'Hopper'])
        ->create();
    (new UserProfileModel($this->db))->upsert($this->targetId, [
        'is_public' => 1,
        'bio' => '',
        'occupation' => '',
        'location' => '',
    ]);

    $this->controller = \Framework\Core\App::container()->get(UserController::class);

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public function render(string $template, array $data = []): string
        {
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

/**
 * Submit the target's edit form with its current values, apply the overrides
 * (null leaves a field out, as an unticked checkbox would), and return the
 * fields the audit entry says changed.
 *
 * @param  array<string, string|null>  $overrides
 * @return list<string>
 */
function saveAdminUserForm(object $test, array $overrides = []): array
{
    $user = $test->userModel->find($test->targetId);
    $post = array_filter(array_merge([
        '_token' => csrf()->getToken(),
        'handle' => $user['handle'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'timezone' => 'UTC',
        'locale' => 'auto',
        'default_blog_id' => '0',
        'is_public' => '1',
    ], $overrides), static fn (?string $value): bool => $value !== null);

    $request = makeRequest("/admin/users/{$test->targetId}/update", 'POST', $post);
    setupController($test->controller, $request, $test->viewer);
    callController($test->controller, 'update', $request, (string) $test->targetId);

    $entry = $test->db->query(
        "SELECT details FROM activity_log WHERE action = 'user.updated' AND resource_id = ? ORDER BY id DESC LIMIT 1",
        [$test->targetId]
    )->fetch(PDO::FETCH_ASSOC);

    expect($entry)->toBeArray('the save should have written an audit entry');

    return json_decode((string) $entry['details'], true)['fields'];
}

it('names no fields when a save changes nothing', function () {
    expect(saveAdminUserForm($this))->toBe([]);
});

it('still logs a profile switched to private', function () {
    expect(saveAdminUserForm($this, ['is_public' => null]))->toBe(['is_public'])
        ->and((new UserProfileModel($this->db))->findOrCreate($this->targetId)['is_public'])->toBe(0);
});
