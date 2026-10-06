<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Mail\Templates\CatalogTemplateSource;
use App\Mail\Templates\RenderedEmail;
use App\Services\EmailTemplateManager;
use App\Services\TemplateRendererService;
use App\Traits\SandboxesPreviewHtml;
use Framework\Core\Response;
use Throwable;

/**
 * Email templates: blocks arranged in order, which emails are then bound to.
 *
 * Also serves the live preview every editor in this area uses. The editors
 * post their unsaved form here into a sandboxed frame, so what an admin sees
 * is rendered by the same code that renders the real email.
 */
class EmailTemplateController extends AppController
{
    use SandboxesPreviewHtml;

    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageEmailTemplates';

    private const BASE = '/admin/email-templates';

    public function __construct(
        protected Response $response,
        private EmailTemplateManager $manager,
    ) {}

    public function index(): Response
    {
        $usage = [];
        foreach ($this->manager->repository()->templates() as $slug => $template) {
            $usage[$slug] = $this->manager->emailsUsingTemplate($slug);
        }

        return $this->view('areas/admin/EmailTemplate/index.lex.php', [
            'templates' => $this->manager->repository()->templates(),
            'usage' => $usage,
            'categories' => CatalogTemplateSource::TEMPLATE_CATEGORIES,
            'search' => trim((string) $this->request->getParam('q', '')),
            'categoryFilter' => (string) $this->request->getParam('category', ''),
        ]);
    }

    public function create(): Response
    {
        return $this->form([
            'slug' => '', 'label' => '', 'category' => 'transactional', 'description' => '',
            'layout' => ['header', 'heading', 'intro', 'button', 'footer'], 'source' => 'custom',
        ], null);
    }

    public function edit(string $slug): Response
    {
        $template = $this->manager->repository()->template($slug);

        if ($template === null) {
            $this->flash('error', 'That template no longer exists.');

            return $this->redirect(self::BASE);
        }

        return $this->form($template, $slug);
    }

    public function store(): Response
    {
        return $this->save(null);
    }

    public function update(string $slug): Response
    {
        return $this->save($slug);
    }

    public function reset(string $slug): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->manager->resetTemplate($slug, $this->actorId(), $this->request->ip());
        $this->flashResult($result, "Template '{$slug}' is back to its built-in version.");

        return $this->redirect(self::BASE.'/'.$slug.'/edit');
    }

    public function destroy(string $slug): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->manager->deleteTemplate($slug, $this->actorId(), $this->request->ip());
        $this->flashResult($result, "Template '{$slug}' deleted.");

        return $this->redirect($result['ok'] ? self::BASE : self::BASE.'/'.$slug.'/edit');
    }

    /**
     * Read-only preview: the whole template with its sample values, its plain
     * text and the placeholders an email has to fill.
     */
    public function show(string $slug): Response
    {
        $template = $this->manager->repository()->template($slug);

        if ($template === null) {
            $this->flash('error', 'That template no longer exists.');

            return $this->redirect(self::BASE);
        }

        $renderer = new TemplateRendererService($this->manager->repository());

        return $this->view('areas/admin/EmailTemplate/show.lex.php', [
            'template' => $template,
            'rendered' => $renderer->previewTemplate($template),
            'placeholders' => $renderer->placeholdersForTemplate($template),
            'globals' => TemplateRendererService::GLOBALS,
            'usedBy' => $this->manager->emailsUsingTemplate($slug),
            'components' => $this->manager->repository()->components(),
        ]);
    }

    /**
     * The frame on the preview page. Sandboxed: see SandboxesPreviewHtml.
     */
    public function render(string $slug): Response
    {
        $template = $this->manager->repository()->template($slug);

        if ($template === null) {
            return $this->sandboxedHtml('<p>Template not found.</p>', 404);
        }

        return $this->sandboxedHtml((new TemplateRendererService($this->manager->repository()))->previewTemplate($template)->html);
    }

    /**
     * Live preview of an unsaved block, template or email binding.
     *
     * The editor submits its own form here, targeted at the preview frame, so
     * the draft is rendered exactly as it would be saved. Nothing is stored.
     */
    public function preview(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $input = $this->request->postParams();
        $kind = (string) ($input['preview_kind'] ?? '');
        $asText = ($input['preview_format'] ?? '') === 'text';
        $original = trim((string) ($input['original'] ?? ''));
        $original = $original === '' ? null : $original;
        $notes = [];

        try {
            switch ($kind) {
                case 'component':
                    [$component, $errors] = $this->manager->normalizeComponent($input, $original);
                    $component['slug'] = $component['slug'] !== '' ? $component['slug'] : 'new-block';
                    $notes = array_values(array_filter($errors, static fn (string $e): bool => !str_contains($e, 'already exists') && !str_contains($e, 'slug')));
                    $rendered = (new TemplateRendererService($this->manager->repository()))->previewComponent($component);
                    break;

                case 'template':
                    [$template] = $this->manager->normalizeTemplate($input, $original);
                    $rendered = (new TemplateRendererService($this->manager->repository()))->previewTemplate($template);
                    break;

                case 'email':
                    $rendered = $this->previewEmail($input, $notes);
                    break;

                default:
                    return $this->sandboxedHtml('<p>Nothing to preview.</p>', 400);
            }
        } catch (Throwable $e) {
            return $this->sandboxedHtml(self::page('<p style="color:#b91c1c;">This cannot be previewed: '.e($e->getMessage()).'</p>', ''));
        }

        return $this->sandboxedHtml($asText ? self::textPage($rendered) : self::withBanner($rendered, $notes));
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $notes  Filled with problems worth showing above the preview
     */
    private function previewEmail(array $input, array &$notes): RenderedEmail
    {
        $email = $this->manager->email((string) ($input['email'] ?? ''))
            ?? throw new \RuntimeException('Unknown email.');

        [$binding, $errors] = $this->manager->normalizeBinding($email['class'], $input);
        $notes = $errors;

        $sample = in_array($input['sample'] ?? null, $email['samples'], true) ? (string) $input['sample'] : $email['samples'][0];
        $draft = $this->manager->draft([], [], [$email['class'] => ['is_active' => true] + $binding]);
        $mailable = $this->manager->buildSample($draft, $sample, false);

        return new RenderedEmail($mailable->getSubject(), $mailable->getBody(), (string) $mailable->getTextBody());
    }

    private function save(?string $slug): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->manager->saveTemplate($slug, $this->request->postParams(), $this->actorId(), $this->request->ip());

        if (!$result['ok']) {
            $draft = $result['item'] + ['slug' => (string) $slug, 'label' => '', 'category' => 'transactional', 'description' => '', 'layout' => [], 'source' => 'custom'];

            return $this->form($draft, $slug, $result['errors'], 422);
        }

        $this->flash('success', "Template '{$result['item']['slug']}' saved.");

        return $this->redirect(self::BASE.'/'.$result['item']['slug'].'/edit');
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  list<string>  $errors
     */
    private function form(array $template, ?string $slug, array $errors = [], int $status = 200): Response
    {
        $components = $this->manager->repository()->components();
        $placeholders = [];

        foreach ($components as $componentSlug => $component) {
            $placeholders[$componentSlug] = array_values(array_diff(
                TemplateRendererService::placeholdersOfComponent($component),
                TemplateRendererService::GLOBALS
            ));
        }

        return $this->view('areas/admin/EmailTemplate/form.lex.php', [
            'template' => $template,
            'isNew' => $slug === null,
            'components' => $components,
            'componentPlaceholders' => $placeholders,
            'componentCategories' => CatalogTemplateSource::COMPONENT_CATEGORIES,
            'categories' => CatalogTemplateSource::TEMPLATE_CATEGORIES,
            'usedBy' => $slug === null ? [] : $this->manager->emailsUsingTemplate($slug),
            'formErrors' => $errors,
        ])->setStatusCode($status);
    }

    /**
     * A strip above the preview with the subject and anything the draft gets wrong.
     *
     * @param  list<string>  $notes
     */
    private static function withBanner(RenderedEmail $email, array $notes): string
    {
        $banner = '<div style="font:13px/1.5 Arial,sans-serif;background:#f1f5f9;color:#334155;padding:8px 12px;border-bottom:1px solid #e2e8f0;"><strong>Subject:</strong> '.e($email->subject).'</div>';

        foreach ($notes as $note) {
            $banner .= '<div style="font:13px/1.5 Arial,sans-serif;background:#fef2f2;color:#991b1b;padding:6px 12px;border-bottom:1px solid #fecaca;">'.e($note).'</div>';
        }

        $html = (string) preg_replace('#(<body\b[^>]*>)#i', '$1'.str_replace('$', '\\$', $banner), $email->html, 1, $count);

        return $count === 1 ? $html : $banner.$email->html;
    }

    private static function textPage(RenderedEmail $email): string
    {
        return self::page(
            '<pre style="white-space:pre-wrap;word-break:break-word;font:13px/1.6 ui-monospace,Menlo,Consolas,monospace;margin:0;">'
            .e('Subject: '.$email->subject."\n\n".$email->text).'</pre>',
            'padding:16px;'
        );
    }

    private static function page(string $body, string $style): string
    {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;'.$style.'font-family:Arial,sans-serif;">'.$body.'</body></html>';
    }

    /**
     * @param  array{ok: bool, errors: list<string>, warnings: list<string>, item: array<string, mixed>}  $result
     */
    private function flashResult(array $result, string $success): void
    {
        if ($result['ok']) {
            $this->flash('success', $success);

            return;
        }

        foreach ($result['errors'] as $error) {
            $this->flash('error', $error);
        }
    }

    private function actorId(): ?int
    {
        $id = auth()->user()['id'] ?? null;

        return $id === null ? null : (int) $id;
    }
}
