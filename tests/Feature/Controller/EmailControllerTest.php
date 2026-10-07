<?php

declare(strict_types=1);

use App\Controllers\Admin\EmailController;
use App\Mail\Templates\ShippedEmailSource;
use App\Models\EmailContentModel;
use App\Models\EmailLayoutModel;
use App\Models\EmailSettingModel;
use App\Services\EmailContentRepository;
use App\Services\EmailTemplateRegistry;
use App\Services\MailService;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * Email Templates in the control panel: every email listed once with its
 * languages, a sandboxed preview of each language, and test sends.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';

    $this->repository = new EmailContentRepository(
        new ShippedEmailSource(),
        new EmailLayoutModel($this->db),
        new EmailSettingModel($this->db),
        new EmailContentModel($this->db),
    );
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
 * @param  array<string, mixed>  $query
 * @param  array<string, mixed>  $post
 */
function emailController(string $path, string $method = 'GET', array $post = [], array $query = [], ?MailService $mail = null): EmailController
{
    $controller = new EmailController(
        new \Framework\Core\Response(),
        emailManagerFor(test()->repository, test()->db),
        $mail ?? Mockery::mock(MailService::class)
    );
    setupController($controller, makeRequest($path, $method, $post, $query), test()->viewer);

    return $controller;
}

it('lists every registered email once, grouped, with the languages it is written in', function () {
    $this->db->execute("INSERT INTO email_contents (mailable_class, locale, subject, preheader, body, footer_note) VALUES ('App\\\\Mail\\\\NewPostMail', 'el', 'Νέο', '', '<tr><td>x</td></tr>', '')");

    emailController('/admin/email-templates')->index();
    $groups = $this->viewer->data['groups'];
    $listed = array_merge(...array_map('array_keys', array_values($groups)));

    expect($listed)->toHaveCount(count((new EmailTemplateRegistry())->getAll()))
        ->and($groups['Comments'])->toHaveKeys(['CommentReplyMail', 'CommentReplyPendingMail', 'PostCommentPendingMail', 'BlogCommentPendingMail', 'CommentModerationMail'])
        ->and(array_keys($groups['Subscribers']['NewPostMail']['languages']))->toBe(['el', 'en'])
        ->and($groups['Subscribers']['NewPostMail']['languages']['el']['source'])->toBe('custom')
        ->and($groups['Subscribers']['NewPostMail']['layout'])->toBe('Default');
});

it('only shows emails the registry knows, never an arbitrary class', function () {
    expect(emailController('/admin/email-templates/Mailable')->show('Mailable')->getStatusCode())->toBe(302);
});

/**
 * The editor form for NewPostMail as shipped, with any fields replaced.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function newPostForm(array $overrides = []): array
{
    $english = (new ShippedEmailSource())->content(App\Mail\NewPostMail::class, 'en') ?? [];

    return $overrides + [
        '_token' => csrf()->getToken(),
        'locale' => 'en',
        'layout' => 'default',
        'subject' => $english['subject'],
        'preheader' => $english['preheader'],
        'body' => $english['body'],
        'footer_note' => $english['footer_note'],
    ];
}

it('opens a language tab with its words, and a language not written yet empty', function () {
    emailController('/admin/email-templates/NewPostMail', 'GET', [], ['locale' => 'en'])->show('NewPostMail');
    $english = $this->viewer->data;

    emailController('/admin/email-templates/NewPostMail', 'GET', [], ['locale' => 'el'])->show('NewPostMail');
    $greek = $this->viewer->data;

    expect($english['fields']['subject'])->toBe('New on {{ blog_name }}: {{ post_title }}')
        ->and($english['written'])->toBeTrue()
        ->and($greek['written'])->toBeFalse()
        ->and($greek['fields']['subject'])->toBe('')
        ->and($greek['english']['subject'])->toBe('New on {{ blog_name }}: {{ post_title }}')
        ->and($greek['fields']['layout'])->toBe('default');
});

it('saves a language and goes back to its tab', function () {
    $response = emailController('/x', 'POST', newPostForm(['locale' => 'ar', 'subject' => 'جديد على {{ blog_name }}']))->save('NewPostMail');

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toContain('/admin/email-templates/NewPostMail?locale=ar')
        ->and($this->repository->storedContent(App\Mail\NewPostMail::class, 'ar')['subject'])->toBe('جديد على {{ blog_name }}');
});

it('shows a refused save again as typed, with the reason, and saves nothing', function () {
    $response = emailController('/x', 'POST', newPostForm(['body' => '<tr><td>{{ author_name }}</td></tr>']))->save('NewPostMail');

    expect($response->getStatusCode())->toBe(422)
        ->and($this->viewer->data['fields']['body'])->toBe('<tr><td>{{ author_name }}</td></tr>')
        ->and(implode(' ', $this->viewer->data['formErrors']))->toContain('{{ author_name }}')
        ->and($this->repository->storedContent(App\Mail\NewPostMail::class, 'en'))->toBeNull();
});

it('resets a language to what ships', function () {
    emailController('/x', 'POST', newPostForm(['subject' => 'Changed {{ post_title }}']))->save('NewPostMail');
    emailController('/x', 'POST', ['_token' => csrf()->getToken(), 'locale' => 'en'])->reset('NewPostMail');

    expect($this->repository->storedContent(App\Mail\NewPostMail::class, 'en'))->toBeNull();
});

it('previews what is typed, sandboxed, in the language of the tab, without saving it', function () {
    $html = emailController('/x', 'POST', newPostForm(['locale' => 'ar', 'body' => '<tr><td><script>alert(1)</script>مرحبا {{ post_title }}</td></tr>']))->preview('NewPostMail');
    $text = emailController('/x', 'POST', newPostForm(['preview_format' => 'text']))->preview('NewPostMail');

    expect((string) $html->getHeader('Content-Security-Policy'))->toStartWith('sandbox')
        ->and($html->getBody())->toContain('<html lang="ar" dir="rtl"')
        ->and($html->getBody())->toContain('مرحبا Ten Hidden Beaches in Crete')
        // The linter's complaint is shown above the preview, and nothing was saved.
        ->and($html->getBody())->toContain('&lt;script&gt; tags are not allowed')
        ->and($this->repository->storedContent(App\Mail\NewPostMail::class, 'ar'))->toBeNull()
        ->and($text->getBody())->toContain('Subject: New on Travel Stories');
});

it('sends what is typed as a test, not what is saved', function () {
    $mail = Mockery::mock(MailService::class);
    $mail->shouldReceive('sendTest')->once()
        ->withArgs(fn (App\Mail\Mailable $m, string $to): bool => $to === 'me@example.test' && $m->getSubject() === 'Draft: Ten Hidden Beaches in Crete')
        ->andReturn(true);

    $response = emailController('/x', 'POST', newPostForm(['subject' => 'Draft: {{ post_title }}', 'test_recipient' => 'me@example.test']), [], $mail)
        ->sendTest('NewPostMail');

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getBody(), true)['ok'])->toBeTrue()
        ->and($this->repository->storedContent(App\Mail\NewPostMail::class, 'en'))->toBeNull();
});

it('refuses a test to an invalid address or of an unknown email, and passes on what the mail server said', function () {
    $quiet = Mockery::mock(MailService::class);
    $quiet->shouldNotReceive('sendTest');
    $failing = Mockery::mock(MailService::class);
    $failing->shouldReceive('sendTest')->andThrow(new Exception('Connection refused by smtp.example.test'));
    $post = newPostForm(['test_recipient' => 'me@example.test']);

    $badAddress = emailController('/x', 'POST', ['test_recipient' => 'nope'] + $post, [], $quiet)->sendTest('NewPostMail');
    $unknown = emailController('/x', 'POST', $post, [], $quiet)->sendTest('Mailable');
    $refused = emailController('/x', 'POST', $post, [], $failing)->sendTest('NewPostMail');

    expect($badAddress->getStatusCode())->toBe(422)
        ->and($unknown->getStatusCode())->toBe(404)
        ->and($refused->getStatusCode())->toBe(502)
        ->and(json_decode($refused->getBody(), true)['message'])->toContain('Connection refused');
});

it('refuses a test send without a valid CSRF token', function () {
    emailController('/x', 'POST', ['_token' => 'nope', 'test_recipient' => 'me@example.test'])->sendTest('NewPostMail');
})->throws(Framework\Exceptions\CsrfTokenException::class);
