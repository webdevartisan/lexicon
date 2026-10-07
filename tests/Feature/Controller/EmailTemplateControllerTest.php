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

function bindingController(?\App\Services\MailService $mail = null): EmailBindingController
{
    return new EmailBindingController(new \Framework\Core\Response(), App::container()->get(EmailTemplateManager::class), $mail ?? Mockery::mock(\App\Services\MailService::class));
}

/**
 * @param  array<string, mixed>  $post
 */
function sendTestRequest(string $email, array $post, \App\Services\MailService $mail): \Framework\Core\Response
{
    $controller = bindingController($mail);
    setupController($controller, makeRequest('/admin/email-templates/emails/'.$email.'/send-test', 'POST', $post + ['_token' => csrf()->getToken()]), test()->viewer);

    return $controller->sendTest($email);
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
    $controller = bindingController();
    setupController($controller, makeRequest('/admin/email-templates/emails'), $this->viewer);

    $controller->index();
    $listed = array_merge(...array_map('array_keys', array_values($this->viewer->data['groups'])));

    expect($listed)->toHaveCount(count(App::container()->get(\App\Services\EmailTemplateRegistry::class)->getAll()))
        ->and($this->viewer->data['groups']['Comments'])->toHaveKeys(['CommentReplyMail', 'PostCommentMail', 'CommentModerationMail', 'BlogCommentMail'])
        ->and(array_column($this->viewer->data['groups']['Comments'], 'problem'))->each->toBeNull();
});

it('only edits emails the registry knows, never an arbitrary class', function () {
    $controller = bindingController();
    setupController($controller, makeRequest('/admin/email-templates/emails/Mailable/edit'), $this->viewer);

    $response = $controller->edit('Mailable');

    expect($response->getStatusCode())->toBe(302);
});

it('re-shows a refused binding as typed, with the reason, and saves nothing', function () {
    $controller = bindingController();
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

it('sends the unsaved draft as a test, not the saved version', function () {
    $mail = Mockery::mock(\App\Services\MailService::class);
    $mail->shouldReceive('sendTest')->once()
        ->withArgs(fn (\App\Mail\Mailable $m, string $to): bool => $to === 'me@example.test' && str_contains($m->getBody(), 'Draft heading only'))
        ->andReturn(true);

    $response = sendTestRequest('NewPostMail', [
        'test_recipient' => 'me@example.test',
        'template' => 'notification',
        'mapping' => ['heading' => 'Draft heading only', 'intro' => '{{ post_title }}', 'quote' => '', 'callout' => '', 'button_label' => '', 'button_url' => '', 'details' => '', 'footer_note' => ''],
    ], $mail);

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getBody(), true)['ok'])->toBeTrue()
        ->and($this->manager->repository()->storedBinding(\App\Mail\NewPostMail::class))->toBeNull();
});

it('refuses a test to an invalid address or of a draft that cannot be built, sending nothing', function () {
    $mail = Mockery::mock(\App\Services\MailService::class);
    $mail->shouldNotReceive('sendTest');

    $badAddress = sendTestRequest('NewPostMail', ['test_recipient' => 'not-an-address', 'template' => 'notification'], $mail);
    $badDraft = sendTestRequest('NewPostMail', ['test_recipient' => 'me@example.test', 'template' => 'notification', 'mapping' => ['heading' => 'Hi {{ no_such_value }}']], $mail);
    $unknown = sendTestRequest('Mailable', ['test_recipient' => 'me@example.test'], $mail);

    expect($badAddress->getStatusCode())->toBe(422)
        ->and($badDraft->getStatusCode())->toBe(422)
        ->and(json_decode($badDraft->getBody(), true)['message'])->toContain('no_such_value')
        ->and($unknown->getStatusCode())->toBe(404);
});

it('passes on what the mail server said when a test cannot be sent', function () {
    $mail = Mockery::mock(\App\Services\MailService::class);
    $mail->shouldReceive('sendTest')->andThrow(new Exception('Connection refused by smtp.example.test'));

    $response = sendTestRequest('WelcomeEmail', ['test_recipient' => 'me@example.test'] + bindingInput(\App\Mail\WelcomeEmail::class), $mail);

    expect($response->getStatusCode())->toBe(502)
        ->and(json_decode($response->getBody(), true)['message'])->toContain('Connection refused');
});

/**
 * The editor form as it would be posted for an email's built-in version.
 *
 * @return array<string, mixed>
 */
function bindingInput(string $class): array
{
    $binding = (new \App\Mail\Templates\CatalogTemplateSource())->binding($class);

    return ['template' => $binding['template'], 'mapping' => $binding['mapping']];
}
