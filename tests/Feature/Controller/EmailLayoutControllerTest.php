<?php

declare(strict_types=1);

use App\Controllers\Admin\EmailLayoutController;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * Email layouts in the control panel: listed with what uses them, edited
 * with a live preview of a real email, and saved only when every email in
 * the layout still builds.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';

    $this->repository = emailRepositoryFor($this->db);
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
function layoutController(string $path, string $method = 'GET', array $post = []): EmailLayoutController
{
    $controller = new EmailLayoutController(new Framework\Core\Response(), emailManagerFor(test()->repository, test()->db));
    setupController($controller, makeRequest($path, $method, $post), test()->viewer);

    return $controller;
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function layoutForm(array $overrides = []): array
{
    $default = (new App\Mail\Templates\ShippedEmailSource())->layout('default') ?? [];

    return $overrides + [
        '_token' => csrf()->getToken(),
        'name' => 'Default',
        'html' => $default['html'],
        'primary_color' => '#16A34A',
        'background_color' => '#FFFFFF',
        'support_email' => 'help@example.test',
        'company_address' => '',
    ];
}

it('lists every layout with the emails that use it', function () {
    layoutController('/admin/email-templates/layouts')->index();

    expect(array_keys($this->viewer->data['layouts']))->toBe(['default'])
        ->and($this->viewer->data['usage']['default'])->toHaveCount(32);
});

it('saves changes to the shipped layout, and reset brings the file back', function () {
    $saved = layoutController('/x', 'POST', layoutForm())->update('default');

    expect($saved->getStatusCode())->toBe(302)
        ->and($this->repository->layout('default')['primary_color'])->toBe('#16A34A');

    layoutController('/x', 'POST', ['_token' => csrf()->getToken()])->reset('default');

    expect($this->repository->layout('default')['source'])->toBe('built-in');
});

it('shows a refused layout again as typed, with the reason', function () {
    $response = layoutController('/x', 'POST', layoutForm(['html' => '<html><body>{{ content }}<script>x</script></body></html>']))->update('default');

    expect($response->getStatusCode())->toBe(422)
        ->and($this->viewer->data['layout']['html'])->toContain('<script>x</script>')
        ->and(implode(' ', $this->viewer->data['formErrors']))->toContain('{{ footer_note }}')
        ->and(implode(' ', $this->viewer->data['formErrors']))->toContain('<script>')
        ->and($this->repository->layout('default')['source'])->toBe('built-in');
});

it('creates a new layout from a free slug, and deletes it while nothing uses it', function () {
    $created = layoutController('/x', 'POST', layoutForm(['slug' => 'newsletter', 'name' => 'Newsletter']))->store();

    expect($created->getHeader('Location'))->toContain('/admin/email-templates/layouts/newsletter/edit')
        ->and($this->repository->layout('newsletter')['source'])->toBe('custom');

    layoutController('/x', 'POST', ['_token' => csrf()->getToken()])->destroy('newsletter');

    expect($this->repository->layout('newsletter'))->toBeNull();
});

it('previews a real email in the layout as typed, sandboxed, without saving', function () {
    $response = layoutController('/x', 'POST', layoutForm([
        'original' => 'default',
        'preview_email' => 'PasswordResetEmail',
        'html' => '<html><body class="draft" style="color:{{ primary_color }}">{{ content }}{{ footer_note }}</body></html>',
    ]))->preview();

    expect((string) $response->getHeader('Content-Security-Policy'))->toStartWith('sandbox')
        ->and($response->getBody())->toContain('<body class="draft" style="color:#16A34A">')
        ->and($response->getBody())->toContain('Reset your password')
        ->and($this->repository->layout('default')['source'])->toBe('built-in');
});
