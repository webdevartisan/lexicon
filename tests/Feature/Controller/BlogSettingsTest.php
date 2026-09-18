<?php

declare(strict_types=1);

use App\Controllers\Dashboard\BlogController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\UserFactory;

/**
 * Blog settings: new blogs publish comments instantly, visibility is saved from the
 * action bar, and the address is shown read only.
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

    $email = faker()->unique()->safeEmail();
    $userId = UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    $this->db->execute(
        'INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE role_slug = ?',
        [$userId, 'reader']
    );
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->blogs = new BlogModel($this->db);
    $this->settings = new BlogSettingsModel($this->db);

    $this->call = function (string $action, array $post, array $args = [], ?TemplateViewerInterface $viewer = null) {
        $request = makeRequest('/dashboard/blogs', 'POST', $post + [
            '_token' => App::container()->get(Csrf::class)->getToken(),
        ], [], ['HTTP_REFERER' => base_url().'/en/dashboard']);

        $controller = App::container()->get(BlogController::class);
        setupController($controller, $request, $viewer ?? Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());

        return callController($controller, $action, $request, ...$args);
    };

    $this->newBlog = function (): array {
        $slug = 'settings-'.strtolower(uniqid());
        ($this->call)('create', ['name' => 'Settings test', 'slug' => $slug, 'description' => '']);

        return $this->blogs->getBlogBySlug($slug);
    };

    $this->save = function (array $blog, array $fields) {
        return ($this->call)('update', $fields + [
            '_method' => 'PUT',
            'name' => 'Settings test',
            'description' => '',
            'locale' => 'en',
            'timezone' => 'UTC',
        ], [(string) $blog['id']]);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('publishes comments instantly on a newly created blog', function () {
    $blog = ($this->newBlog)();

    expect((int) $this->settings->findByBlogId((int) $blog['id'])['comments_auto_publish'])->toBe(1);
});

it('still honours an explicit choice to hold comments for review', function () {
    $blog = ($this->newBlog)();
    $this->db->execute('DELETE FROM blog_settings WHERE blog_id = ?', [$blog['id']]);

    $this->settings->createDefaultForBlog((int) $blog['id'], ['comments_auto_publish' => 0]);

    expect((int) $this->settings->findByBlogId((int) $blog['id'])['comments_auto_publish'])->toBe(0);
});

it('saves the visibility chosen in the action bar', function () {
    $blog = ($this->newBlog)();

    ($this->save)($blog, ['status' => 'published']);

    $saved = $this->blogs->find((int) $blog['id']);
    expect($saved['status'])->toBe('published')
        ->and($saved['published_at'])->not->toBeNull();
});

it('refuses an unknown visibility and leaves the blog as it was', function () {
    $blog = ($this->newBlog)();

    ($this->save)($blog, ['status' => 'deleted']);

    expect($this->blogs->find((int) $blog['id'])['status'])->toBe('draft');
});

it('shows the blog address and no editable slug', function () {
    $blog = ($this->newBlog)();
    $viewer = new class implements TemplateViewerInterface
    {
        public array $data = [];

        public function render(?string $template, array $data = []): string
        {
            $this->data = $data;

            return '';
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

    ($this->call)('settings', [], [(string) $blog['id']], $viewer);
    $captured = $viewer->data;

    $view = file_get_contents(ROOT_PATH.'/views/areas/dashboard/Blog/settings.lex.php');
    $bar = file_get_contents(ROOT_PATH.'/views/partials/dashboard/blog/_settings_action_bar.lex.php');

    expect($captured['blogUrl'])->toBe(base_url().'/blog/'.$blog['blog_slug'])
        ->and($view)->not->toContain('name="slug"')
        ->and($view)->not->toContain('name="status"')
        ->and($bar)->toContain('name="status"');
});
