<?php

declare(strict_types=1);

use App\Controllers\Dashboard\BlogController;
use App\Controllers\Dashboard\ExtrasController;
use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\UserModel;
use App\Services\BlogCacheInvalidator;
use Framework\Core\App;
use Framework\Exceptions\CsrfTokenException;
use Framework\Exceptions\PageNotFoundException;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * Blog owners switch extras on and off for their own blog, and delete an extra's
 * data once it is off. Nobody outside the blog can do either.
 *
 * Installing the fixture module runs DDL, which would commit the test's
 * transaction, so it is installed before the transaction starts and removed after.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = App::container()->get(\Framework\Database::class);
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    ModuleFixtures::dropSampleExtra($this->db);
    $this->db->execute("DELETE FROM modules WHERE module_key = 'sample-extra'");
    ModuleFixtures::installer($this->db)->install('sample-extra');
    $this->db->getConnection()->beginTransaction();

    $this->signIn = function (): int {
        $_SESSION = [];
        auth()->logout();
        $email = faker()->unique()->safeEmail();
        $userId = UserFactory::new(new UserModel($this->db))
            ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
            ->create();
        $this->db->execute(
            'INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE role_slug = ?',
            [$userId, 'reader']
        );
        expect(auth()->login($email, 'password123'))->toBeTrue();

        return $userId;
    };
    ($this->signIn)();

    $this->request = fn (array $post, bool $withToken = true): \Framework\Core\Request => makeRequest(
        '/dashboard/blog',
        'POST',
        $post + ($withToken ? ['_token' => App::container()->get(Csrf::class)->getToken()] : []),
        [],
        ['HTTP_REFERER' => base_url().'/en/dashboard']
    );

    $slug = 'extras-'.strtolower(uniqid());
    $create = ($this->request)(['name' => 'Extras', 'slug' => $slug, 'description' => '']);
    $blogs = App::container()->get(BlogController::class);
    setupController($blogs, $create, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());
    callController($blogs, 'create', $create);
    $this->blogId = (int) (new BlogModel($this->db))->getBlogBySlug($slug)['id'];
    $this->blogModules = new BlogModuleModel($this->db);

    $this->call = function (
        string $action,
        array $post = [],
        bool $withToken = true,
        ?TemplateViewerInterface $viewer = null,
    ) {
        $request = ($this->request)($post, $withToken);
        $controller = new ExtrasController(
            new BlogModel($this->db),
            ModuleFixtures::registry(['sample-extra' => 'on']),
            $this->blogModules,
            ModuleFixtures::instances($this->db),
            App::container()->get(BlogCacheInvalidator::class)
        );
        $viewer ??= Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();
        setupController($controller, $request, $viewer);

        return callController($controller, $action, $request, (string) $this->blogId);
    };
    $session = App::container()->get(\Framework\Session::class);
    $this->flashes = fn (string $type): array => $session->get('_flash', [])[$type] ?? [];
    $this->notesLeft = fn (): int => (int) $this->db
        ->query('SELECT COUNT(*) FROM sample_extra_notes WHERE blog_id = ?', [$this->blogId])
        ->fetchColumn();
});

afterEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    ModuleFixtures::dropSampleExtra($this->db);
    $this->db->execute("DELETE FROM modules WHERE module_key = 'sample-extra'");
    ModuleFixtures::cleanUp();
});

test('the blog owner switches an extra on and off', function () {
    ($this->call)('toggle', ['module' => 'sample-extra', 'enabled' => '1']);
    $on = $this->blogModules->activeKeys($this->blogId);
    ($this->call)('toggle', ['module' => 'sample-extra', 'enabled' => '0']);

    expect($on)->toBe(['sample-extra'])
        ->and($this->blogModules->activeKeys($this->blogId))->toBe([])
        ->and(($this->flashes)('success'))->toBe(['Extra switched on.', 'Extra switched off.']);
});

test('the page lists installed extras with whether each is on', function () {
    $this->blogModules->enable($this->blogId, 'sample-extra');
    $seen = null;
    $viewer = Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();
    $viewer->shouldReceive('render')->andReturnUsing(function ($template, $data) use (&$seen) {
        $seen = $data['extras'] ?? null;

        return '';
    });

    ($this->call)('index', viewer: $viewer);

    expect($seen)->toHaveCount(1)
        ->and($seen[0])
        ->toMatchArray(['key' => 'sample-extra', 'name' => 'Sample extra', 'on' => true, 'hasData' => false]);
});

test('someone outside the blog cannot toggle', function () {
    ($this->signIn)();

    expect(fn () => ($this->call)('toggle', ['module' => 'sample-extra', 'enabled' => '1']))
        ->toThrow(UnauthorizedException::class);
    expect($this->blogModules->activeKeys($this->blogId))->toBe([]);
});

test('an unknown or uninstalled module key is refused', function () {
    expect(fn () => ($this->call)('toggle', ['module' => 'not-installed', 'enabled' => '1']))
        ->toThrow(PageNotFoundException::class);
    expect($this->blogModules->activeKeys($this->blogId))->toBe([]);
});

test('an enabled value other than 0 or 1 is refused', function () {
    ($this->call)('toggle', ['module' => 'sample-extra', 'enabled' => 'yes']);

    expect($this->blogModules->activeKeys($this->blogId))->toBe([]);
});

test('purge is refused while the extra is on', function () {
    $this->blogModules->enable($this->blogId, 'sample-extra');
    $this->db->execute('INSERT INTO sample_extra_notes (blog_id, note) VALUES (?, ?)', [$this->blogId, 'kept']);

    ($this->call)('purge', ['module' => 'sample-extra']);

    expect(($this->notesLeft)())->toBe(1)
        ->and(($this->flashes)('error'))->not->toBeEmpty();
});

test('purge deletes the blog data once the extra is off', function () {
    $this->db->execute('INSERT INTO sample_extra_notes (blog_id, note) VALUES (?, ?)', [$this->blogId, 'gone']);

    ($this->call)('purge', ['module' => 'sample-extra']);

    expect(($this->notesLeft)())->toBe(0)
        ->and(($this->flashes)('success'))->toBe(['Data deleted.']);
});

test('a missing token is refused', function () {
    expect(fn () => ($this->call)('toggle', ['module' => 'sample-extra', 'enabled' => '1'], withToken: false))
        ->toThrow(CsrfTokenException::class);
    expect($this->blogModules->activeKeys($this->blogId))->toBe([]);
});
