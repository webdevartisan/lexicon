<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Services\EmailTemplateManager;
use App\Services\TemplateRendererService;
use Framework\Core\Response;

/**
 * Emails: which template each email the site sends uses, and its wording.
 *
 * One entry per Mailable class. The wording of each placeholder is HTML that
 * may refer to the data that email provides, listed beside the form with
 * sample values. Emails are addressed by short class name in URLs, checked
 * against the registry, so nothing here ever names an arbitrary class.
 */
class EmailBindingController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageEmailTemplates';

    private const BASE = '/admin/email-templates/emails';

    public function __construct(
        protected Response $response,
        private EmailTemplateManager $manager,
    ) {}

    public function index(): Response
    {
        $repository = $this->manager->repository();
        $problems = $this->manager->problems($repository);
        $rows = [];

        foreach ($this->manager->emails() as $short => $email) {
            $live = $repository->binding($email['class']);
            $stored = $repository->storedBinding($email['class']);

            $rows[$email['group']][$short] = $email + [
                'template' => $live === null ? null : $repository->template($live['template']),
                'state' => $stored === null ? 'built-in' : ($stored['is_active'] ? 'customized' : 'off'),
                'problem' => $problems[$email['class']] ?? null,
            ];
        }

        return $this->view('areas/admin/EmailBinding/index.lex.php', [
            'groups' => $rows,
            'search' => trim((string) $this->request->getParam('q', '')),
        ]);
    }

    public function edit(string $name): Response
    {
        $email = $this->manager->email($name);

        if ($email === null) {
            $this->flash('error', 'There is no such email.');

            return $this->redirect(self::BASE);
        }

        $repository = $this->manager->repository();
        $binding = $repository->storedBinding($email['class'])
            ?? $repository->binding($email['class'])
            ?? ['mailable' => $email['class'], 'template' => 'notification', 'subject' => null, 'mapping' => [], 'is_active' => true, 'source' => 'custom'];

        return $this->form($email, $binding);
    }

    public function update(string $name): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $email = $this->manager->email($name);

        if ($email === null) {
            $this->flash('error', 'There is no such email.');

            return $this->redirect(self::BASE);
        }

        $result = $this->manager->saveBinding($email['class'], $this->request->postParams(), $this->actorId(), $this->request->ip());

        if (!$result['ok']) {
            /** @var array{mailable: string, template: string, subject: ?string, mapping: array<string, string>, is_active: bool, source: string} $draft */
            $draft = $result['item'];

            return $this->form($email, $draft, $result['errors'], $result['warnings'], 422);
        }

        $this->flash('success', $result['item']['is_active']
            ? "{$email['name']} now uses your version."
            : "Your version of {$email['name']} is saved but switched off; the built-in one is still sent.");

        foreach ($result['warnings'] as $warning) {
            $this->flash('warning', $warning);
        }

        return $this->redirect(self::BASE.'/'.$name.'/edit');
    }

    public function reset(string $name): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $email = $this->manager->email($name);

        if ($email !== null) {
            $result = $this->manager->resetBinding($email['class'], $this->actorId(), $this->request->ip());
            $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? "{$email['name']} is back to its built-in version." : $result['errors'][0]);
        }

        return $this->redirect(self::BASE.'/'.$name.'/edit');
    }

    /**
     * @param  array{class: string, short: string, name: string, description: string, group: string, sample: string}  $email
     * @param  array{mailable: string, template: string, subject: ?string, mapping: array<string, string>, is_active: bool, source: string}  $binding
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function form(array $email, array $binding, array $errors = [], array $warnings = [], int $status = 200): Response
    {
        $repository = $this->manager->repository();
        $renderer = new TemplateRendererService($repository);
        $data = $this->manager->sampleData($email['sample']);

        // Every placeholder of every template, so switching template in the
        // form only shows and hides fields rather than reloading.
        $templatePlaceholders = [];
        $allPlaceholders = [];
        $feeds = [];
        foreach ($repository->templates() as $slug => $template) {
            $names = array_values(array_diff($renderer->placeholdersForTemplate($template), TemplateRendererService::GLOBALS));
            $templatePlaceholders[$slug] = $names;
            $allPlaceholders = array_merge($allPlaceholders, $names);

            // Which block of this template each placeholder ends up in, as a hint beside its field.
            foreach ($template['layout'] as $componentSlug) {
                $component = $repository->component($componentSlug);
                foreach ($component === null ? [] : TemplateRendererService::placeholdersOfComponent($component) as $name) {
                    $feeds[$slug][$name] ??= $component['label'];
                }
            }
        }

        // A field with no wording yet starts by passing through the email's
        // value of the same name, when it has one.
        $wording = [];
        foreach (array_unique($allPlaceholders) as $name) {
            $wording[$name] = $binding['mapping'][$name] ?? (isset($data[$name]) ? '{{ '.$name.' }}' : '');
        }

        $stored = $repository->storedBinding($email['class']);
        $builtIn = $repository->builtIn()->binding($email['class']);

        return $this->view('areas/admin/EmailBinding/form.lex.php', [
            'email' => $email,
            'binding' => $binding,
            'wording' => $wording,
            'templates' => $repository->templates(),
            'templatePlaceholders' => $templatePlaceholders,
            'feeds' => $feeds,
            'data' => $data,
            'globals' => TemplateRendererService::GLOBALS,
            'isStored' => $stored !== null,
            'builtInTemplate' => $builtIn['template'] ?? null,
            'problem' => $this->manager->problems($repository, [$email['class']])[$email['class']] ?? null,
            'formErrors' => $errors,
            'formWarnings' => $warnings,
        ])->setStatusCode($status);
    }

    private function actorId(): ?int
    {
        $id = auth()->user()['id'] ?? null;

        return $id === null ? null : (int) $id;
    }
}
