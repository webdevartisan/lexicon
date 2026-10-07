<?php

declare(strict_types=1);

use App\Controllers\Admin\EmailController;
use App\Mail\Templates\ShippedEmailSource;
use App\Models\EmailContentModel;
use App\Models\EmailLayoutModel;
use App\Models\EmailSettingModel;
use App\Services\EmailContentRepository;
use App\Services\EmailManager;
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
        new EmailManager(test()->repository, new EmailTemplateRegistry()),
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

it('previews a language the email is written in, sandboxed, and the default for any other', function () {
    $this->db->execute("INSERT INTO email_contents (mailable_class, locale, subject, preheader, body, footer_note) VALUES ('App\\\\Mail\\\\NewPostMail', 'ar', 'جديد', '', '<tr><td>مرحبا</td></tr>', '')");

    $arabic = emailController('/admin/email-templates/NewPostMail/render', 'GET', [], ['locale' => 'ar'])->render('NewPostMail');
    $other = emailController('/admin/email-templates/NewPostMail/render', 'GET', [], ['locale' => 'el'])->render('NewPostMail');
    $text = emailController('/admin/email-templates/NewPostMail/render', 'GET', [], ['format' => 'text'])->render('NewPostMail');

    expect((string) $arabic->getHeader('Content-Security-Policy'))->toStartWith('sandbox')
        ->and($arabic->getBody())->toContain('<html lang="ar" dir="rtl"')
        ->and($arabic->getBody())->toContain('مرحبا')
        ->and($other->getBody())->toContain('<html lang="en"')
        ->and($text->getBody())->toContain('Subject: New on Travel Stories');
});

it('sends a test of the chosen language to any valid address', function () {
    $mail = Mockery::mock(MailService::class);
    $mail->shouldReceive('sendTest')->once()
        ->withArgs(fn (App\Mail\Mailable $m, string $to): bool => $to === 'me@example.test' && $m->getSubject() === 'New on Travel Stories: Ten Hidden Beaches in Crete')
        ->andReturn(true);

    $response = emailController('/admin/email-templates/NewPostMail/send-test', 'POST', ['_token' => csrf()->getToken(), 'test_recipient' => 'me@example.test', 'locale' => 'en'], [], $mail)
        ->sendTest('NewPostMail');

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getBody(), true)['ok'])->toBeTrue();
});

it('refuses a test to an invalid address or of an unknown email, and passes on what the mail server said', function () {
    $quiet = Mockery::mock(MailService::class);
    $quiet->shouldNotReceive('sendTest');
    $failing = Mockery::mock(MailService::class);
    $failing->shouldReceive('sendTest')->andThrow(new Exception('Connection refused by smtp.example.test'));
    $post = ['_token' => csrf()->getToken(), 'test_recipient' => 'me@example.test'];

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
