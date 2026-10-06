<?php

declare(strict_types=1);

use App\Controllers\Admin\InsightsController;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Database;
use Framework\Exceptions\PageNotFoundException;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;

/**
 * The control panel's Insights pages: which page and scope a request opens, and
 * how the Insights link brings an administrator back to where they left off.
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

    $email = faker()->unique()->safeEmail();
    UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    expect(auth()->login($email, 'password123'))->toBeTrue();

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

    // Calls one action the way the router would, with these query parameters.
    $this->open = function (string $action, array $query = [], string ...$arguments) {
        $controller = App::container()->get(InsightsController::class);
        setupController($controller, makeRequest('/admin/insights', 'GET', [], $query), $this->viewer);

        return $controller->{$action}(...$arguments);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

test('Overview has its own address, and a page that does not exist is not found', function () {
    ($this->open)('page', [], 'overview');

    expect($this->viewer->template)->toBe('insights.overview')
        ->and($this->viewer->data['scope'])->toBe('site')
        ->and($this->viewer->data['pagePath'])->toBe('/admin/insights/overview');

    ($this->open)('page', [], 'nope');
})->throws(PageNotFoundException::class);

test('the scope switch opens the platform pages, and every link the page makes keeps it', function () {
    ($this->open)('page', ['scope' => 'platform'], 'audience');

    expect($this->viewer->data['scope'])->toBe('platform')
        ->and($this->viewer->data['extraQuery'])->toBe(['scope' => 'platform']);
});

test('the Insights link opens the page looked at last, unless the link asks for a view of its own', function () {
    ($this->open)('page', [], 'seo');

    $resumed = ($this->open)('index');

    expect($resumed->getStatusCode())->toBe(302)
        ->and($resumed->getHeader('Location'))->toContain('/admin/insights/seo');

    ($this->open)('index', ['range' => '7d']);

    expect($this->viewer->template)->toBe('insights.overview');
});

test('a page opened without a range shows the range picked last', function () {
    ($this->open)('page', ['range' => '90d'], 'content');
    ($this->open)('page', [], 'audience');

    expect($this->viewer->data['range']->preset)->toBe('90d');
});
