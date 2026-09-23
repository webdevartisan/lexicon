<?php

declare(strict_types=1);

use App\Controllers\Admin\EmailTestController;
use App\Mail\Mailable;
use App\Services\EmailTemplateRegistry;
use App\Services\MailService;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * Feature tests for the email template preview.
 *
 * renderHtml() serves a Mailable's rendered body as its own page so the email's
 * styles cannot collide with the control panel's. That body is rendered markup,
 * not trusted markup, and the page is meant to be framed, so opening its URL
 * directly leaves the iframe's own sandbox attribute out of the picture. These
 * prove every way out of the action still carries the headers that keep the
 * body inert.
 */
beforeEach(function () {
    $container = \Framework\Core\App::container();

    $this->mailService = $container->get(MailService::class);
    $this->registry = $container->get(EmailTemplateRegistry::class);

    $this->mockViewer = new class() implements TemplateViewerInterface
    {
        public function render(string $template, array $data = []): string
        {
            return 'mocked view';
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

/** Stands in for the escaping bug the sandbox exists to contain. */
function leakyRegistry(): EmailTemplateRegistry
{
    return new class() extends EmailTemplateRegistry
    {
        public function instantiate(string $templateKey): Mailable
        {
            return new class() extends Mailable
            {
                public function build(): void
                {
                    $this->to('admin@example.test')
                        ->subject('Leaky template')
                        ->html('<script>alert(1)</script>');
                }
            };
        }
    };
}

it('sandboxes the rendered HTML so a template script cannot run even on direct navigation', function () {
    $controller = new EmailTestController($this->mailService, leakyRegistry());
    setupController($controller, makeRequest('/admin/email-test/render-html', 'GET', [], ['template' => 'welcome']), $this->mockViewer);

    $response = $controller->renderHtml();

    // The preview iframe's sandbox attribute only applies when framed; this
    // header is what stops the same script running if the URL is opened alone.
    expect((string) $response->getHeader('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getBody())->toBe('<script>alert(1)</script>');
});

it('sandboxes the error message when a template cannot be rendered', function () {
    $controller = new EmailTestController($this->mailService, $this->registry);
    setupController($controller, makeRequest('/admin/email-test/render-html', 'GET', [], ['template' => 'no-such-template']), $this->mockViewer);

    $response = $controller->renderHtml();

    expect((string) $response->getHeader('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getBody())->toContain('Error:');
});

it('sandboxes the reply when no template is named at all', function () {
    $controller = new EmailTestController($this->mailService, $this->registry);
    setupController($controller, makeRequest('/admin/email-test/render-html', 'GET'), $this->mockViewer);

    $response = $controller->renderHtml();

    expect((string) $response->getHeader('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getBody())->toBe('<p>Template not specified</p>');
});

it('escapes the exception text it echoes back', function () {
    $controller = new EmailTestController($this->mailService, $this->registry);
    $key = '<script>alert(2)</script>';
    setupController($controller, makeRequest('/admin/email-test/render-html', 'GET', [], ['template' => $key]), $this->mockViewer);

    $response = $controller->renderHtml();

    expect($response->getBody())->not->toContain('<script>')
        ->and($response->getBody())->toContain('&lt;script&gt;');
});
