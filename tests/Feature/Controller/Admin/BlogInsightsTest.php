<?php

declare(strict_types=1);

use App\Controllers\Admin\BlogInsightsController;
use App\Controllers\Dashboard\InsightsController as DashboardInsightsController;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\Analytics\InsightsSavedView;
use Framework\Core\App;
use Framework\Database;
use Framework\Exceptions\PageNotFoundException;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * One blog's Insights in the control panel: staff read any blog's numbers from
 * there, and no longer through that blog's dashboard.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }

    $this->db = App::container()->get(Database::class);

    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    // Signs in a new account holding one system role, and returns its id.
    $this->signIn = function (string $role): int {
        $email = faker()->unique()->safeEmail();
        $userId = UserFactory::new(new UserModel($this->db))
            ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
            ->create();
        $this->db->execute(
            'INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE role_slug = ?',
            [$userId, $role]
        );
        expect(auth()->login($email, 'password123'))->toBeTrue();

        return $userId;
    };

    $this->ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->ownerId);

    $this->publish = fn (int $blogId, int $authorId): int => PostFactory::new(new PostModel($this->db))
        ->withAttributes(['blog_id' => $blogId, 'author_id' => $authorId])
        ->published()
        ->create();

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public ?string $template = null;

        /** @var array<string, mixed> */
        public array $data = [];

        public function render(string $template, array $data = []): string
        {
            $this->template = $template;
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

    // Calls one action the way the router would, area check first.
    $this->open = function (string $action, array $query = [], string ...$arguments) {
        $controller = App::container()->get(BlogInsightsController::class);
        setupController($controller, makeRequest('/admin/insights/blogs', 'GET', [], $query), $this->viewer);
        $controller->beforeAction($action);

        return $controller->{$action}(...$arguments);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

test('someone without the platform\'s Insights is turned away', function () {
    ($this->signIn)('reader');

    ($this->open)('page', [], (string) $this->blogId, 'overview');
})->throws(UnauthorizedException::class);

test('staff read a blog they have no part in, whole and read only, without leaving the control panel', function () {
    ($this->signIn)('administrator');

    ($this->open)('page', ['range' => '30d'], (string) $this->blogId, 'overview');

    $data = $this->viewer->data;
    expect($this->viewer->template)->toBe('dashboard.insights.overview')
        ->and($data['scope'])->toBe('blog')
        ->and($data['canAll'])->toBeTrue()
        ->and($data['canConfigure'])->toBeFalse()
        ->and($data['basePath'])->toBe('/admin/insights/blogs/'.$this->blogId)
        ->and($data['pagePath'])->toBe('/admin/insights/blogs/'.$this->blogId.'/overview')
        ->and($data['staffPages'])->toContain('technical')
        ->and($data['staffPages'])->not->toContain('authors');
});

test('the Authors page opens only once two people have published on the blog', function () {
    ($this->signIn)('administrator');
    $secondAuthor = UserFactory::new(new UserModel($this->db))->create();
    ($this->publish)($this->blogId, $this->ownerId);

    expect(fn () => ($this->open)('page', [], (string) $this->blogId, 'authors'))
        ->toThrow(PageNotFoundException::class);

    ($this->publish)($this->blogId, $secondAuthor);
    ($this->open)('page', [], (string) $this->blogId, 'authors');

    expect($this->viewer->template)->toBe('dashboard.insights.authors')
        ->and($this->viewer->data['staffPages'])->toContain('authors');
});

test('a page that does not exist and a blog that does not exist are not found', function () {
    ($this->signIn)('administrator');

    expect(fn () => ($this->open)('page', [], (string) $this->blogId, 'nope'))->toThrow(PageNotFoundException::class)
        ->and(fn () => ($this->open)('page', [], '999999999', 'overview'))->toThrow(PageNotFoundException::class);
});

test('a post opens under its own blog only', function () {
    ($this->signIn)('administrator');
    $postId = ($this->publish)($this->blogId, $this->ownerId);
    $otherBlogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->ownerId);

    ($this->open)('post', [], (string) $this->blogId, (string) $postId);

    expect($this->viewer->template)->toBe('dashboard.insights.post')
        ->and($this->viewer->data['pagePath'])->toBe('/admin/insights/blogs/'.$this->blogId.'/posts/'.$postId)
        ->and(fn () => ($this->open)('post', [], (string) $otherBlogId, (string) $postId))
        ->toThrow(PageNotFoundException::class);
});

test('looking at a blog leaves the staff member\'s own dashboard blog and resume page alone', function () {
    $staffId = ($this->signIn)('administrator');

    ($this->open)('page', [], (string) $this->blogId, 'seo');

    expect(App::container()->get(UserPreferencesModel::class)->getDefaultBlogId($staffId))->toBeNull()
        ->and(App::container()->get(InsightsSavedView::class)->lastPage($staffId))->toBeNull();
});

test('staff no longer get into a blog\'s Insights through its dashboard', function () {
    ($this->signIn)('administrator');

    $controller = App::container()->get(DashboardInsightsController::class);
    setupController($controller, makeRequest('/dashboard/blog/'.$this->blogId.'/insights', 'GET'), $this->viewer);

    $controller->page((string) $this->blogId, 'overview');
})->throws(UnauthorizedException::class);
