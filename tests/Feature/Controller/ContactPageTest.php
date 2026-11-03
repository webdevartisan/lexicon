<?php

declare(strict_types=1);

use App\Controllers\PageController;
use App\Models\PageModel;
use App\Models\SettingModel;
use Framework\Core\App;
use Framework\Database;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;

/**
 * A sent message shows its confirmation once, and that page must never reach
 * the shared page cache, since it carries no form to keep it out.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = App::container()->get(Database::class);
    $this->db->getConnection()->beginTransaction();

    $_SESSION = [];
    auth()->logout();

    $this->pages = new PageModel($this->db);
    if ($this->pages->findPublished('contact', 'en') === null) {
        $this->pages->insert(['slug' => 'contact', 'locale' => 'en', 'title' => 'Contact us', 'content' => '', 'is_published' => 1]);
    }

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public array $data = [];

        public function render(string $template, array $data = []): string
        {
            $this->data = $data;

            return 'rendered';
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

    $this->call = function (string $method, string $httpMethod = 'GET', array $post = []) {
        $controller = new PageController($this->pages, App::container()->get(SettingModel::class));
        $request = makeRequest('/contact', $httpMethod, $post);
        setupController($controller, $request, $this->viewer);

        return callController($controller, $method, $request);
    };
});

afterEach(function () {
    $_SESSION = [];
});

test('a filled hidden field answers like a sent message and the confirmation shows once', function () {
    $response = ($this->call)('sendContact', 'POST', [
        '_token' => App::container()->get(Csrf::class)->getToken(),
        'website' => 'filled',
    ]);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toBe(lurl('/contact'));

    $first = ($this->call)('contact');
    expect($this->viewer->data['sent'])->toBeTrue()
        ->and($first->getHeader('Cache-Control'))->toBe('private, no-store');

    $second = ($this->call)('contact');
    expect($this->viewer->data['sent'])->toBeFalse()
        ->and($second->getHeader('Cache-Control'))->toBeNull();
});
