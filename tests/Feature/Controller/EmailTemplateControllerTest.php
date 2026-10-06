<?php

declare(strict_types=1);

use App\Controllers\Admin\EmailBindingController;
use App\Controllers\Admin\EmailTemplateController;
use App\Services\EmailTemplateManager;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * The live preview renders whatever an admin is typing, so it is the one
 * place unsaved, untrusted markup becomes a page on the admin origin. These
 * prove it is always sandboxed, never writes anything, and shows the draft
 * rather than what is saved.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $this->manager = App::container()->get(EmailTemplateManager::class);
    $this->manager->repository()->flush();
    $this->viewer = new class() implements TemplateViewerInterface
    {
        /** @var array<string, mixed> */
        public array $data = [];

        public function render(string $template, array $data = []): string
        {
            $this->data = $data;

            return 'view:'.$template;
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

/**
 * @param  array<string, mixed>  $post
 */
function previewRequest(array $post): \Framework\Core\Response
{
    $post['_token'] = csrf()->getToken();
    $controller = new EmailTemplateController(new \Framework\Core\Response(), App::container()->get(EmailTemplateManager::class));
    setupController($controller, makeRequest('/admin/email-templates/preview', 'POST', $post), test()->viewer);

    return $controller->preview();
}

it('previews a draft block, sandboxed, with a script inside left inert', function () {
    $response = previewRequest([
        'preview_kind' => 'component',
        'slug' => 'draft', 'label' => 'Draft', 'category' => 'content',
        'html' => '<p>{{ body }}</p><script>alert(1)</script>',
        'preview' => ['body' => 'Sample <b>text</b>'],
    ]);

    expect((string) $response->getHeader('Content-Security-Policy'))->toStartWith('sandbox')
        ->and($response->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getBody())->toContain('<p>Sample &lt;b&gt;text&lt;/b&gt;</p>')
        // The linter's complaint is shown above the preview...
        ->and($response->getBody())->toContain('&lt;script&gt; tags are not allowed')
        // ...and nothing was saved.
        ->and((int) $this->db->query('SELECT COUNT(*) FROM email_components')->fetchColumn())->toBe(0);
});

it('previews an unsaved email wording against its sample data, as html or plain text', function () {
    $binding = $this->manager->repository()->binding(\App\Mail\NewPostMail::class);
    $post = [
        'preview_kind' => 'email', 'email' => 'NewPostMail', 'template' => 'notification',
        'mapping' => ['heading' => 'Draft heading for {{ blog_name }}'] + $binding['mapping'],
    ];

    $html = previewRequest($post)->getBody();
    $text = previewRequest($post + ['preview_format' => 'text'])->getBody();

    expect($html)->toContain('Draft heading for Travel Stories')
        ->and($html)->toContain('<strong>Subject:</strong> New on Travel Stories')
        ->and($text)->toContain('<pre')
        ->and($text)->toContain('Draft heading for Travel Stories')
        ->and($this->manager->repository()->storedBinding(\App\Mail\NewPostMail::class))->toBeNull();
});

it('refuses a preview without a valid CSRF token', function () {
    $controller = new EmailTemplateController(new \Framework\Core\Response(), $this->manager);
    setupController($controller, makeRequest('/admin/email-templates/preview', 'POST', ['preview_kind' => 'component', '_token' => 'nope']), $this->viewer);

    $controller->preview();
})->throws(Framework\Exceptions\CsrfTokenException::class);

it('lists every registered email on its own row, each comment email included', function () {
    $controller = new EmailBindingController(new \Framework\Core\Response(), $this->manager);
    setupController($controller, makeRequest('/admin/email-templates/emails'), $this->viewer);

    $controller->index();
    $listed = array_merge(...array_map('array_keys', array_values($this->viewer->data['groups'])));

    expect($listed)->toHaveCount(count(App::container()->get(\App\Services\EmailTemplateRegistry::class)->getAll()))
        ->and($this->viewer->data['groups']['Comments'])->toHaveKeys(['CommentReplyMail', 'PostCommentMail', 'CommentModerationMail', 'BlogCommentMail'])
        ->and(array_column($this->viewer->data['groups']['Comments'], 'problem'))->each->toBeNull();
});

it('only edits emails the registry knows, never an arbitrary class', function () {
    $controller = new EmailBindingController(new \Framework\Core\Response(), $this->manager);
    setupController($controller, makeRequest('/admin/email-templates/emails/Mailable/edit'), $this->viewer);

    $response = $controller->edit('Mailable');

    expect($response->getStatusCode())->toBe(302);
});

it('re-shows a refused binding as typed, with the reason, and saves nothing', function () {
    $controller = new EmailBindingController(new \Framework\Core\Response(), $this->manager);
    setupController($controller, makeRequest('/admin/email-templates/emails/NewPostMail/update', 'POST', [
        '_token' => csrf()->getToken(),
        'template' => 'notification',
        'mapping' => ['heading' => 'Hi {{ first_name }}'],
        'is_active' => '1',
    ]), $this->viewer);

    $response = $controller->update('NewPostMail');

    expect($response->getStatusCode())->toBe(422)
        ->and($this->viewer->data['binding']['mapping']['heading'])->toBe('Hi {{ first_name }}')
        ->and(implode(' ', $this->viewer->data['formErrors']))->toContain('first_name')
        ->and($this->manager->repository()->storedBinding(\App\Mail\NewPostMail::class))->toBeNull();
});
