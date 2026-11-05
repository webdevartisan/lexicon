<?php

declare(strict_types=1);

use App\Controllers\Admin\ExtrasController;
use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\ModuleModel;
use App\Models\UserModel;
use App\Services\PublicCacheInvalidator;
use Framework\Core\App;
use Framework\Exceptions\PageNotFoundException;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Modules\ModuleRegistry;
use Framework\Security\Csrf;
use Framework\Session;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * Administrators switch every installed extra on or off for the whole site.
 *
 * Installing the fixture modules runs DDL, which would commit the test's
 * transaction, so they are installed before the transaction starts and removed after.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = App::container()->get(\Framework\Database::class);
    ModuleFixtures::dropSampleExtra($this->db);
    ModuleFixtures::dropSamplePlatform($this->db);
    $this->db->execute("DELETE FROM modules WHERE module_key IN ('sample-extra', 'sample-platform')");
    ModuleFixtures::installer($this->db)->install('sample-extra');
    ModuleFixtures::installer($this->db)->install('sample-platform');
    $this->db->getConnection()->beginTransaction();

    $_SESSION = [];
    auth()->logout();
    $email = faker()->unique()->safeEmail();
    UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->modules = new ModuleModel($this->db);
    $this->registry = new ModuleRegistry(
        ModuleFixtures::PATH,
        ModuleFixtures::cacheFile(),
        fn () => $this->modules->states()
    );

    $this->controller = function (array $post = [], ?TemplateViewerInterface $viewer = null): ExtrasController {
        $post += ['_token' => App::container()->get(Csrf::class)->getToken()];
        $request = makeRequest('/admin/extras', $post === [] ? 'GET' : 'POST', $post, [], []);
        $controller = new ExtrasController($this->registry, $this->modules, new PublicCacheInvalidator());
        $viewer ??= Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();
        setupController($controller, $request, $viewer);

        return $controller;
    };
    $this->toggle = function (string $key, string $enabled) {
        $post = ['module' => $key, 'enabled' => $enabled];

        return callController(($this->controller)($post), 'toggle', makeRequest('/admin/extras', 'POST', $post));
    };
});

afterEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    ModuleFixtures::dropSampleExtra($this->db);
    ModuleFixtures::dropSamplePlatform($this->db);
    $this->db->execute("DELETE FROM modules WHERE module_key IN ('sample-extra', 'sample-platform')");
    ModuleFixtures::cleanUp();
});

test('the page lists every installed module with its scope and status', function () {
    $this->modules->switchOff('sample-platform');
    $this->registry->rebuild();
    $seen = null;
    $viewer = Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();
    $viewer->shouldReceive('render')->andReturnUsing(function ($template, $data) use (&$seen) {
        $seen = $data['extras'] ?? null;

        return '';
    });

    ($this->controller)([], $viewer)->index();

    expect($seen)->toHaveCount(2)
        ->and($seen[0])->toMatchArray(['key' => 'sample-extra', 'scope' => 'Each blog', 'status' => 'On'])
        ->and($seen[1])->toMatchArray(['key' => 'sample-platform', 'scope' => 'Whole site', 'status' => 'Off']);
});

test('switching a blog module off site wide hides it from blogs but keeps their choice', function () {
    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $blogId = BlogFactory::new(new BlogModel($this->db))->create($ownerId);
    $blogModules = new BlogModuleModel($this->db);
    $blogModules->enable($blogId, 'sample-extra');

    ($this->toggle)('sample-extra', '0');
    $hidden = $this->registry->blogExtras();
    $kept = $blogModules->activeKeys($blogId);
    ($this->toggle)('sample-extra', '1');

    expect($hidden)->toBe([])
        ->and($kept)->toBe(['sample-extra'])
        ->and(array_keys($this->registry->blogExtras()))->toBe(['sample-extra'])
        ->and(App::container()->get(Session::class)->get('_flash', [])['success'] ?? [])->toBe([
            'Extra switched off for the whole site.',
            'Extra switched on for the whole site.',
        ]);
});

test('switching a paused module on resumes it', function () {
    $this->modules->pause('sample-platform', 'Too many errors');

    ($this->toggle)('sample-platform', '1');

    expect($this->modules->states()['sample-platform'])->toBe('on')
        ->and($this->modules->paused())->toBe([]);
});

test('an unknown module key is a 404', function () {
    expect(fn () => ($this->toggle)('nothing-here', '1'))->toThrow(PageNotFoundException::class);
});

test('a user without manage_extras cannot open the page', function () {
    expect(fn () => ($this->controller)()->beforeAction('index'))->toThrow(UnauthorizedException::class);
});
