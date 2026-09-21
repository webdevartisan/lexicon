<?php

declare(strict_types=1);

use App\Controllers\Admin\UserController;
use App\Controllers\Admin\UserRoleController;
use App\Controllers\Admin\UserSecurityController;
use App\Controllers\Admin\UserSuspensionController;
use App\Models\BlogModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * The admin user actions re-check authority on the server. The row menu hides
 * what a viewer may not do, but these requests are sent straight at the
 * endpoints, the way a forged or replayed request would arrive.
 *
 * The delegate holds manage_all_users through a custom role, which is what
 * lets a non-administrator into the users area at all.
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

    $this->users = new UserModel($this->db);

    // Every admin user action checks targets against this shared placeholder.
    $this->db->execute(
        "INSERT INTO users (handle, email, password, display_name_cached, is_active)
         VALUES ('deleted-user', 'deleted-user@lexicon.invalid', '', 'Deleted user', 0)"
    );

    $this->db->execute(
        "INSERT INTO roles (role_name, role_slug, description, scope, is_system, level)
         VALUES ('Test user manager', 'test_user_manager', 'Delegated users area', 'system', 0, 50)"
    );
    $managerRole = (int) $this->db->getConnection()->lastInsertId();
    $permission = (int) $this->db->query("SELECT id FROM permissions WHERE permission_slug = 'manage_all_users'")->fetchColumn();
    $this->db->execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$managerRole, $permission]);

    $this->adminId = UserFactory::new($this->users)->withRoles(roleIds($this->db, ['administrator']))->create();
    $this->targetId = UserFactory::new($this->users)->withRoles(roleIds($this->db, ['reader']))->create();

    $this->managerEmail = faker()->unique()->safeEmail();
    $this->managerId = UserFactory::new($this->users)
        ->withAttributes(['email' => $this->managerEmail, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->withRoles([$managerRole])
        ->create();

    $this->viewer = Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
    Mockery::close();
});

/**
 * @param  class-string  $class
 * @param  array<string, mixed>  $post
 */
function adminAction(string $class, string $uri, array $post, object $viewer): object
{
    $controller = App::container()->get($class);
    $request = makeRequest($uri, 'POST', ['_token' => App::container()->get(Csrf::class)->getToken()] + $post);
    setupController($controller, $request, $viewer);

    return $controller;
}

function signInAs(string $email): void
{
    expect(auth()->login($email, 'password123'))->toBeTrue();
}

it('refuses a delegate who tries to change anyone\'s site role, including their own', function () {
    signInAs($this->managerEmail);
    $adminRole = roleIds($this->db, ['administrator'])[0];

    foreach ([$this->targetId, $this->managerId] as $userId) {
        $controller = adminAction(UserRoleController::class, "/admin/users/{$userId}/site-role", ['role_id' => $adminRole], $this->viewer);

        expect(fn () => $controller->updateSiteRole((string) $userId))->toThrow(UnauthorizedException::class);
        expect($this->users->getUserRoles($userId))->not->toContain('administrator');
    }
});

it('refuses a delegate who tries to sign in as someone', function () {
    signInAs($this->managerEmail);
    $controller = adminAction(UserSecurityController::class, "/admin/users/{$this->targetId}/impersonate", ['reason' => 'x'], $this->viewer);

    expect(fn () => $controller->impersonate((string) $this->targetId))->toThrow(UnauthorizedException::class);
    expect($_SESSION['user_id'])->toBe($this->managerId);
});

it('refuses a delegate who tries to suspend, reset or delete an administrator', function () {
    signInAs($this->managerEmail);
    $id = (string) $this->adminId;

    $suspend = adminAction(UserSuspensionController::class, "/admin/users/{$id}/suspend", ['type' => 'permanent', 'reason' => 'hostile'], $this->viewer);
    $password = adminAction(UserSecurityController::class, "/admin/users/{$id}/password/set", ['password' => 'Another-Pass-2026!', 'confirm_password' => 'Another-Pass-2026!'], $this->viewer);
    $delete = adminAction(UserController::class, "/admin/users/{$id}/destroy", [], $this->viewer);

    expect(fn () => $suspend->suspend($id))->toThrow(UnauthorizedException::class)
        ->and(fn () => $password->setPassword($id))->toThrow(UnauthorizedException::class)
        ->and(fn () => $delete->destroy($id))->toThrow(UnauthorizedException::class);

    $admin = $this->db->query('SELECT is_active, suspended_at, deleted_at FROM users WHERE id = ?', [$this->adminId])->fetch();

    expect((int) $admin['is_active'])->toBe(1)
        ->and($admin['suspended_at'])->toBeNull()
        ->and($admin['deleted_at'])->toBeNull();
});

it('still lets a delegate suspend an ordinary account', function () {
    signInAs($this->managerEmail);
    $id = (string) $this->targetId;
    $controller = adminAction(UserSuspensionController::class, "/admin/users/{$id}/suspend", ['type' => 'permanent', 'reason' => 'Spam links'], $this->viewer);

    $controller->suspend($id);

    expect($this->db->query('SELECT suspended_at FROM users WHERE id = ?', [$this->targetId])->fetchColumn())->not->toBeNull();
});

it('ignores the role a delegate posts when creating an account', function () {
    signInAs($this->managerEmail);
    $email = faker()->unique()->safeEmail();
    $controller = adminAction(UserController::class, '/admin/users/create', [
        'handle' => 'createdbydelegate',
        'email' => $email,
        'password' => 'Created-Pass-2026!',
        'role_id' => roleIds($this->db, ['administrator'])[0],
    ], $this->viewer);

    $controller->create();

    $created = (int) $this->db->query('SELECT id FROM users WHERE email = ?', [$email])->fetchColumn();

    expect($this->users->getUserRoles($created))->toBe(['reader']);
});

it('refuses to change or remove the owner row of a blog, so a blog cannot be left without one', function () {
    $this->db->execute('UPDATE users SET password = ? WHERE id = ?', [password_hash('password123', PASSWORD_DEFAULT), $this->adminId]);
    $adminEmail = $this->db->query('SELECT email FROM users WHERE id = ?', [$this->adminId])->fetchColumn();
    signInAs($adminEmail);

    $blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->targetId);
    $id = (string) $this->targetId;

    $remove = adminAction(UserRoleController::class, "/admin/users/{$id}/blog-roles/{$blogId}/remove", [], $this->viewer);
    $change = adminAction(UserRoleController::class, "/admin/users/{$id}/blog-roles/{$blogId}", ['role' => 'author'], $this->viewer);

    $remove->removeBlogRole($id, (string) $blogId);
    $change->updateBlogRole($id, (string) $blogId);

    expect((int) $this->db->query('SELECT owner_id FROM blogs WHERE id = ?', [$blogId])->fetchColumn())->toBe($this->targetId)
        ->and($_SESSION['_flash']['error'] ?? [])->not->toBeEmpty();
});
