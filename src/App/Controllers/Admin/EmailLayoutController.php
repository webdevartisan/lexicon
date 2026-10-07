<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Mail\Templates\DraftEmailSource;
use App\Mail\Templates\EmailSource;
use App\Services\EmailManager;
use App\Services\EmailRenderer;
use App\Traits\SandboxesPreviewHtml;
use Framework\Core\Response;
use Throwable;

/**
 * Email layouts: the whole HTML document every email goes into, with its
 * colours and footer details.
 *
 * A layout that ships with the site can be edited and reset but not deleted;
 * one made here can be deleted once no email uses it. The editor previews a
 * real email in the draft layout as it is typed.
 *
 * @phpstan-import-type Layout from EmailSource
 */
class EmailLayoutController extends AppController
{
    use SandboxesPreviewHtml;

    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageEmailTemplates';

    private const BASE = '/admin/email-templates/layouts';

    public function __construct(
        protected Response $response,
        private EmailManager $manager,
    ) {}

    public function index(): Response
    {
        $usage = [];
        foreach (array_keys($this->manager->repository()->layouts()) as $slug) {
            $usage[$slug] = $this->manager->emailsUsingLayout($slug);
        }

        return $this->view('areas/admin/Email/layouts.lex.php', [
            'layouts' => $this->manager->repository()->layouts(),
            'usage' => $usage,
        ]);
    }

    /**
     * A new layout starts as a copy of the default one.
     */
    public function create(): Response
    {
        $default = $this->manager->repository()->layout(EmailSource::DEFAULT_LAYOUT);

        return $this->form(['slug' => '', 'name' => '', 'source' => 'custom'] + ($default ?? [
            'html' => '', 'primary_color' => '#4F46E5', 'background_color' => '#F3F4F6', 'support_email' => '', 'company_address' => '',
        ]), null);
    }

    public function edit(string $slug): Response
    {
        $layout = $this->manager->repository()->layout($slug);

        if ($layout === null) {
            $this->flash('error', 'That layout no longer exists.');

            return $this->redirect(self::BASE);
        }

        return $this->form($layout, $slug);
    }

    public function store(): Response
    {
        return $this->save(null);
    }

    public function update(string $slug): Response
    {
        if ($this->manager->repository()->layout($slug) === null) {
            $this->flash('error', 'That layout no longer exists.');

            return $this->redirect(self::BASE);
        }

        return $this->save($slug);
    }

    public function reset(string $slug): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->manager->resetLayout($slug, $this->actorId(), $this->request->ip());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'The layout is back to the version that ships with the site.' : $result['errors'][0]);

        return $this->redirect(self::BASE.'/'.$slug.'/edit');
    }

    public function destroy(string $slug): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->manager->deleteLayout($slug, $this->actorId(), $this->request->ip());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Layout deleted.' : $result['errors'][0]);

        return $this->redirect($result['ok'] ? self::BASE : self::BASE.'/'.$slug.'/edit');
    }

    /**
     * Live preview: one email, in English with its sample data, in the layout
     * as typed. Nothing is saved.
     */
    public function preview(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $input = $this->request->postParams();
        $original = trim((string) ($input['original'] ?? ''));
        $email = $this->manager->email((string) ($input['preview_email'] ?? '')) ?? $this->manager->email('WelcomeEmail');

        if ($email === null) {
            return $this->sandboxedHtml('<p>There is no email to preview with.</p>', 404);
        }

        [$layout, $errors] = $this->manager->normalizeLayout($input, $original === '' ? null : $original);
        $slug = $original !== '' ? $original : '__draft';
        $draft = new DraftEmailSource($this->manager->repository(), [], [$slug => ['slug' => $slug] + $layout], [$email['class'] => $slug]);

        try {
            $mail = $this->manager->buildSample($email, EmailRenderer::BASE_LOCALE, false, $draft);
        } catch (Throwable $e) {
            return $this->sandboxedHtml('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;padding:16px;"><p style="color:#b91c1c;">This cannot be previewed: '.e($e->getMessage()).'</p></body></html>');
        }

        // A new layout's slug is not chosen yet; that is not worth a warning in the preview.
        $notes = array_values(array_filter($errors, static fn (string $e): bool => !str_contains($e, 'slug') && !str_contains($e, 'already exists')));
        $banner = '';
        foreach ($notes as $note) {
            $banner .= '<div dir="ltr" style="text-align:left;font:13px/1.5 Arial,sans-serif;background:#fef2f2;color:#991b1b;padding:6px 12px;border-bottom:1px solid #fecaca;">'.e($note).'</div>';
        }

        $html = (string) preg_replace('#(<body\b[^>]*>)#i', '$1'.str_replace('$', '\\$', $banner), $mail->getBody(), 1, $count);

        return $this->sandboxedHtml($count === 1 ? $html : $banner.$mail->getBody());
    }

    private function save(?string $slug): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->manager->saveLayout($slug, $this->request->postParams(), $this->actorId(), $this->request->ip());

        if (!$result['ok']) {
            /** @var Layout $draft */
            $draft = $result['item'];

            return $this->form($draft, $slug, $result['errors'], $result['warnings'], 422);
        }

        $this->flash('success', "Layout '{$result['item']['name']}' saved.");

        foreach ($result['warnings'] as $warning) {
            $this->flash('warning', $warning);
        }

        return $this->redirect(self::BASE.'/'.$result['item']['slug'].'/edit');
    }

    /**
     * @param  Layout  $layout
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function form(array $layout, ?string $slug, array $errors = [], array $warnings = [], int $status = 200): Response
    {
        $repository = $this->manager->repository();
        $usedBy = $slug === null ? [] : $this->manager->emailsUsingLayout($slug);

        return $this->view('areas/admin/Email/layout-form.lex.php', [
            'layout' => $layout,
            'isNew' => $slug === null,
            'isShipped' => $slug !== null && $repository->shipped()->layout($slug) !== null,
            'isStored' => $slug !== null && ($repository->layout($slug)['source'] ?? 'built-in') !== 'built-in',
            'usedBy' => $usedBy,
            'emails' => $this->manager->emails(),
            'previewEmail' => array_key_first($usedBy) ?? 'WelcomeEmail',
            'placeholders' => array_merge(EmailRenderer::LAYOUT_ONLY, EmailRenderer::GLOBALS, EmailRenderer::LAYOUT_SETTINGS),
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
