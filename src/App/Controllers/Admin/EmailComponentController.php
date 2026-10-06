<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Mail\Templates\CatalogTemplateSource;
use App\Services\EmailTemplateManager;
use App\Services\TemplateRendererService;
use Framework\Core\Response;

/**
 * The block library: the reusable pieces email templates are built from.
 *
 * Built-in blocks can be edited (which saves a customized copy) and reset,
 * but not deleted. Blocks made here can be deleted once no template uses
 * them. Every save is checked against every email first; see
 * EmailTemplateManager.
 */
class EmailComponentController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageEmailTemplates';

    private const BASE = '/admin/email-templates/components';

    public function __construct(
        protected Response $response,
        private EmailTemplateManager $manager,
    ) {}

    public function index(): Response
    {
        $repository = $this->manager->repository();
        $usage = [];

        foreach ($repository->components() as $slug => $component) {
            $usage[$slug] = $this->manager->templatesUsingComponent($slug);
        }

        return $this->view('areas/admin/EmailComponent/index.lex.php', [
            'components' => $repository->components(),
            'usage' => $usage,
            'categories' => CatalogTemplateSource::COMPONENT_CATEGORIES,
            'search' => trim((string) $this->request->getParam('q', '')),
            'categoryFilter' => (string) $this->request->getParam('category', ''),
        ]);
    }

    public function create(): Response
    {
        $component = [
            'slug' => '', 'label' => '', 'category' => 'content', 'description' => '',
            'html' => '<div style="margin:0 0 16px;">{{ body }}</div>', 'text' => null, 'css' => '',
            'preview_data' => ['body' => 'Sample text'], 'source' => 'custom',
        ];

        return $this->form($component, null);
    }

    public function edit(string $slug): Response
    {
        $component = $this->manager->repository()->component($slug);

        if ($component === null) {
            $this->flash('error', 'That block no longer exists.');

            return $this->redirect(self::BASE);
        }

        return $this->form($component, $slug);
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

        $result = $this->manager->resetComponent($slug, $this->actorId(), $this->request->ip());
        $this->flashResult($result, "Block '{$slug}' is back to its built-in version.");

        return $this->redirect(self::BASE.'/'.$slug.'/edit');
    }

    public function destroy(string $slug): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->manager->deleteComponent($slug, $this->actorId(), $this->request->ip());
        $this->flashResult($result, "Block '{$slug}' deleted.");

        return $this->redirect($result['ok'] ? self::BASE : self::BASE.'/'.$slug.'/edit');
    }

    private function save(?string $slug): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->manager->saveComponent($slug, $this->request->postParams(), $this->actorId(), $this->request->ip());

        if (!$result['ok']) {
            /** @var array{slug: string, label: string, category: string, description: string, html: string, text: ?string, css: string, preview_data: array<string, string>, source: string} $draft */
            $draft = $result['item'] + ['source' => 'custom', 'preview_data' => [], 'text' => null, 'css' => '', 'description' => '', 'html' => '', 'label' => '', 'category' => 'content', 'slug' => (string) $slug];

            return $this->form($draft, $slug, $result['errors'], $result['warnings'], 422);
        }

        $this->flash('success', "Block '{$result['item']['slug']}' saved.");
        foreach ($result['warnings'] as $warning) {
            $this->flash('warning', $warning);
        }

        return $this->redirect(self::BASE.'/'.$result['item']['slug'].'/edit');
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function form(array $component, ?string $slug, array $errors = [], array $warnings = [], int $status = 200): Response
    {
        $response = $this->view('areas/admin/EmailComponent/form.lex.php', [
            'component' => $component,
            'isNew' => $slug === null,
            'usedBy' => $slug === null ? [] : $this->manager->templatesUsingComponent($slug),
            'placeholders' => TemplateRendererService::placeholdersOfComponent($component),
            'globals' => TemplateRendererService::GLOBALS,
            'categories' => CatalogTemplateSource::COMPONENT_CATEGORIES,
            'formErrors' => $errors,
            'formWarnings' => $warnings,
        ]);

        return $response->setStatusCode($status);
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
