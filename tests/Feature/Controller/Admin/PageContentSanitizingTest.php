<?php

declare(strict_types=1);

use App\Controllers\Admin\PageController;
use App\Models\PageModel;
use App\Models\UserModel;
use App\Services\LocaleRegistry;
use App\Services\PostContentSanitizer;
use App\Services\PublicCacheInvalidator;
use App\Services\UploadService;
use Framework\Core\App;
use Framework\Database;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\UserFactory;

/**
 * Page bodies are printed unescaped on the public site, so a save that carries
 * scripting must come out of the database without it.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = App::container()->get(Database::class);
    $this->db->getConnection()->beginTransaction();

    $_SESSION = [];
    auth()->logout();

    $email = faker()->unique()->safeEmail();
    UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->pages = new PageModel($this->db);
    $this->pages->insert([
        'slug' => 'sanitize-check',
        'locale' => 'en',
        'title' => 'Sanitize check',
        'content' => '<p>Original</p>',
        'is_published' => 1,
    ]);
    $this->pageId = (int) $this->pages->getInsertID();
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('strips scripting from a saved page', function () {
    $container = App::container();
    $request = makeRequest('/admin/pages/'.$this->pageId.'/update', 'POST', [
        '_token' => $container->get(Csrf::class)->getToken(),
        'title' => 'Sanitize check',
        'content' => '<p>Real words</p><script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">x</a>',
        'meta_description' => '',
    ]);

    $controller = new PageController(
        $this->pages,
        $container->get(PublicCacheInvalidator::class),
        $container->get(UploadService::class),
        $container->get(LocaleRegistry::class),
        new PostContentSanitizer()
    );
    setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());
    callController($controller, 'update', $request, (string) $this->pageId);

    $stored = $this->pages->find($this->pageId)['content'];

    expect($stored)->toContain('Real words')
        ->and($stored)->not->toContain('<script')
        ->and($stored)->not->toContain('javascript:')
        ->and($stored)->not->toContain('onclick');
});
