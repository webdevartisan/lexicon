<?php

declare(strict_types=1);

use App\Controllers\Admin\RoleController;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\UserFactory;

/**
 * Editing what a role grants is administrator-only. manage_roles can be
 * delegated, so a holder must not be able to widen their own role or strip
 * the administrator role.
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

    $this->db->execute(
        "INSERT INTO roles (role_name, role_slug, description, scope, is_system, level)
         VALUES ('Test role manager', 'test_role_manager', 'Delegated roles area', 'system', 0, 50)"
    );
    $this->managerRole = (int) $this->db->getConnection()->lastInsertId();
    $this->permissionId = fn (string $slug): int => (int) $this->db
        ->query('SELECT id FROM permissions WHERE permission_slug = ?', [$slug])->fetchColumn();
    $this->db->execute(
        'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
        [$this->managerRole, ($this->permissionId)('manage_roles')]
    );

    $this->adminEmail = faker()->unique()->safeEmail();
    UserFactory::new($this->users)
        ->withAttributes(['email' => $this->adminEmail, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->withRoles(roleIds($this->db, ['administrator']))
        ->create();

    $this->managerEmail = faker()->unique()->safeEmail();
    UserFactory::new($this->users)
        ->withAttributes(['email' => $this->managerEmail, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->withRoles([$this->managerRole])
        ->create();

    $this->viewer = Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
    Mockery::close();
});

/**
 * @param  array<string, mixed>  $post
 */
function roleAction(string $uri, array $post, object $viewer): RoleController
{
    $controller = App::container()->get(RoleController::class);
    $request = makeRequest($uri, 'POST', ['_token' => App::container()->get(Csrf::class)->getToken()] + $post);
    setupController($controller, $request, $viewer);

    return $controller;
}

/**
 * @return string[]
 */
function grantedSlugs(\Framework\Database $db, int $roleId): array
{
    return $db->query(
        'SELECT p.permission_slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
        [$roleId]
    )->fetchAll(PDO::FETCH_COLUMN);
}

it('refuses a manage_roles delegate who tries to widen their own role', function () {
    expect(auth()->login($this->managerEmail, 'password123'))->toBeTrue();

    $grab = array_map($this->permissionId, ['manage_roles', 'manage_all_users', 'manage_site_settings']);
    $controller = roleAction("/admin/roles/{$this->managerRole}/permissions", ['permissions' => $grab], $this->viewer);

    expect(fn () => $controller->updatePermissions((string) $this->managerRole))->toThrow(UnauthorizedException::class);
    expect(grantedSlugs($this->db, $this->managerRole))->toBe(['manage_roles']);
});

it('refuses a manage_roles delegate who tries to strip the administrator role', function () {
    expect(auth()->login($this->managerEmail, 'password123'))->toBeTrue();

    $adminRole = roleIds($this->db, ['administrator'])[0];
    $before = grantedSlugs($this->db, $adminRole);
    $controller = roleAction("/admin/roles/{$adminRole}/permissions", ['permissions' => []], $this->viewer);

    expect(fn () => $controller->updatePermissions((string) $adminRole))->toThrow(UnauthorizedException::class);
    expect(grantedSlugs($this->db, $adminRole))->toBe($before);
});

it('lets an administrator change a role\'s permissions', function () {
    expect(auth()->login($this->adminEmail, 'password123'))->toBeTrue();

    // syncPermissions opens its own transaction and nesting is refused, so
    // commit the wrapper and remove the rows by hand afterwards
    $this->db->getConnection()->commit();

    try {
        $post = ['permissions' => [($this->permissionId)('manage_all_users')]];
        roleAction("/admin/roles/{$this->managerRole}/permissions", $post, $this->viewer)
            ->updatePermissions((string) $this->managerRole);

        expect(grantedSlugs($this->db, $this->managerRole))->toBe(['manage_all_users']);
    } finally {
        $this->db->execute('DELETE FROM users WHERE email IN (?, ?)', [$this->adminEmail, $this->managerEmail]);
        $this->db->execute('DELETE FROM roles WHERE id = ?', [$this->managerRole]);
    }
});

it('rejects permission ids that do not exist and leaves the role untouched', function () {
    expect(auth()->login($this->adminEmail, 'password123'))->toBeTrue();

    $post = ['permissions' => [($this->permissionId)('manage_all_users'), 999999]];
    roleAction("/admin/roles/{$this->managerRole}/permissions", $post, $this->viewer)
        ->updatePermissions((string) $this->managerRole);

    expect(grantedSlugs($this->db, $this->managerRole))->toBe(['manage_roles']);
});

it('ignores the level a delegate submits when creating a role', function () {
    expect(auth()->login($this->managerEmail, 'password123'))->toBeTrue();

    roleAction('/admin/roles/create', [
        'role_name' => 'Delegate made role',
        'description' => '',
        'scope' => 'system',
        'level' => '99',
    ], $this->viewer)->create();

    $level = $this->db->query("SELECT level FROM roles WHERE role_slug = 'delegate_made_role'")->fetchColumn();

    expect((int) $level)->toBe(0);
});

it('keeps a custom role\'s level when a delegate edits it', function () {
    expect(auth()->login($this->managerEmail, 'password123'))->toBeTrue();

    roleAction("/admin/roles/{$this->managerRole}/update", [
        'role_name' => 'Test role manager',
        'description' => 'Renamed description',
        'scope' => 'system',
        'level' => '99',
    ], $this->viewer)->update((string) $this->managerRole);

    $level = $this->db->query('SELECT level FROM roles WHERE id = ?', [$this->managerRole])->fetchColumn();

    expect((int) $level)->toBe(50);
});
