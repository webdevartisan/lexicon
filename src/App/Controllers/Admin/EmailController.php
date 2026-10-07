<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Mail\Templates\RenderedEmail;
use App\Services\EmailManager;
use App\Services\EmailRenderer;
use App\Services\LocaleRegistry;
use App\Services\MailService;
use App\Traits\SandboxesPreviewHtml;
use Framework\Core\Response;
use Throwable;

/**
 * Email Templates: every email the site sends, and its editor.
 *
 * The editor has a tab per language the site offers. Each tab edits that
 * language's subject, preheader, body and footer note; the layout is shared
 * by every language. What is typed is previewed live and can be sent as a
 * test before it is saved, so this is the one place to write, see and try an
 * email. Emails are addressed by short class name in URLs, checked against
 * the registry, so nothing here ever builds an arbitrary class.
 *
 * @phpstan-import-type Email from EmailManager
 */
class EmailController extends AppController
{
    use SandboxesPreviewHtml;

    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageEmailTemplates';

    private const BASE = '/admin/email-templates';

    public function __construct(
        protected Response $response,
        private EmailManager $manager,
        private MailService $mailService,
    ) {}

    public function index(): Response
    {
        $repository = $this->manager->repository();
        $problems = $this->manager->problems();
        $groups = [];

        foreach ($this->manager->emails() as $short => $email) {
            $layout = $repository->layout($repository->layoutFor($email['class']));
            $languages = [];

            foreach ($this->manager->languages($email) as $locale => $language) {
                $languages[$locale] = $language + ['problem' => $problems[$email['class']][$locale] ?? null];
            }

            $groups[$email['group']][$short] = $email + [
                'layout' => $layout['name'] ?? $repository->layoutFor($email['class']),
                'languages' => $languages,
            ];
        }

        return $this->view('areas/admin/Email/index.lex.php', [
            'groups' => $groups,
            'siteLocales' => LocaleRegistry::instance()->supported(),
            'unregistered' => $this->manager->unregisteredClasses(),
            'search' => trim((string) $this->request->getParam('q', '')),
        ]);
    }

    /**
     * The editor, on one language's tab.
     */
    public function show(string $name): Response
    {
        $email = $this->manager->email($name);

        if ($email === null) {
            $this->flash('error', 'There is no such email.');

            return $this->redirect(self::BASE);
        }

        $locale = $this->locale((string) $this->request->getParam('locale', ''));
        $repository = $this->manager->repository();
        $content = $repository->content($email['class'], $locale);

        return $this->editor($email, $locale, [
            'subject' => $content['subject'] ?? '',
            'preheader' => $content['preheader'] ?? '',
            'body' => $content['body'] ?? '',
            'footer_note' => $content['footer_note'] ?? '',
            'repeat' => $content['repeat'] ?? '',
            'layout' => $repository->layoutFor($email['class']),
        ]);
    }

    public function save(string $name): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $email = $this->manager->email($name);

        if ($email === null) {
            $this->flash('error', 'There is no such email.');

            return $this->redirect(self::BASE);
        }

        $locale = $this->locale((string) $this->request->postParam('locale', ''));
        $input = $this->request->postParams();
        $result = $this->manager->saveContent($email, $locale, $input, $this->actorId(), $this->request->ip());

        if (!$result['ok']) {
            return $this->editor($email, $locale, self::fields($input), $result['errors'], $result['warnings'], 422);
        }

        $this->flash('success', $email['name'].' is saved in '.LocaleRegistry::instance()->nativeName($locale).'.');

        foreach ($result['warnings'] as $warning) {
            $this->flash('warning', $warning);
        }

        return $this->redirect(self::BASE.'/'.$name.'?locale='.$locale);
    }

    /**
     * Drop one language's saved words: English goes back to its shipped file,
     * any other language is no longer written.
     */
    public function reset(string $name): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $email = $this->manager->email($name);
        $locale = $this->locale((string) $this->request->postParam('locale', ''));

        if ($email !== null) {
            $result = $this->manager->resetContent($email, $locale, $this->actorId(), $this->request->ip());
            $this->flash($result['ok'] ? 'success' : 'error', $result['ok']
                ? ($locale === EmailRenderer::BASE_LOCALE ? "{$email['name']} is back to its shipped English." : 'The '.LocaleRegistry::instance()->nativeName($locale)." version of {$email['name']} was deleted.")
                : $result['errors'][0]);
        }

        return $this->redirect(self::BASE.'/'.$name.'?locale='.$locale);
    }

    /**
     * Live preview of what is typed in the editor. The editor posts its own
     * form here into a sandboxed frame, so the draft is rendered exactly as it
     * would be sent. Nothing is saved.
     */
    public function preview(string $name): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $email = $this->manager->email($name);

        if ($email === null) {
            return $this->sandboxedHtml('<p>There is no such email.</p>', 404);
        }

        $locale = $this->locale((string) $this->request->postParam('locale', ''));
        $input = $this->request->postParams();

        try {
            [$content, $layout, $errors] = $this->manager->normalizeContent($email, $locale, $input);
            $mail = $this->manager->buildSample($email, $locale, false, $this->manager->draft($email, $locale, $content, $layout));
            $rendered = new RenderedEmail($mail->getSubject(), $mail->getBody(), (string) $mail->getTextBody());
        } catch (Throwable $e) {
            return $this->sandboxedHtml(self::page('<p style="color:#b91c1c;">This cannot be previewed: '.e($e->getMessage()).'</p>'));
        }

        return $this->sandboxedHtml(($input['preview_format'] ?? '') === 'text' ? self::textPage($rendered) : self::withBanner($rendered, $errors));
    }

    /**
     * Send what is typed in the editor, built from the email's sample data,
     * to one address. Answers in JSON so the form keeps what was typed.
     */
    public function sendTest(string $name): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $email = $this->manager->email($name);
        $recipient = trim((string) $this->request->postParam('test_recipient', ''));

        if ($email === null) {
            return $this->json(['ok' => false, 'message' => 'There is no such email.'], 404);
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['ok' => false, 'message' => 'Enter a valid email address to send the test to.'], 422);
        }

        $locale = $this->locale((string) $this->request->postParam('locale', ''));
        [$content, $layout, $errors] = $this->manager->normalizeContent($email, $locale, $this->request->postParams());

        if ($errors !== []) {
            return $this->json(['ok' => false, 'message' => 'Fix this first: '.$errors[0]], 422);
        }

        try {
            $this->mailService->sendTest($this->manager->buildSample($email, $locale, true, $this->manager->draft($email, $locale, $content, $layout)), $recipient);
        } catch (Throwable $e) {
            // The transport's own complaint is what an admin needs to fix delivery.
            error_log('Email test send failed: '.$e->getMessage());

            return $this->json(['ok' => false, 'message' => 'The test could not be sent: '.$e->getMessage()], 502);
        }

        return $this->json(['ok' => true, 'message' => "Test sent to {$recipient}. Its subject starts with [TEST]."]);
    }

    /**
     * @param  Email  $email
     * @param  array{subject: string, preheader: string, body: string, footer_note: string, repeat: ?string, layout: string}  $fields
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function editor(array $email, string $locale, array $fields, array $errors = [], array $warnings = [], int $status = 200): Response
    {
        $repository = $this->manager->repository();
        $registry = LocaleRegistry::instance();
        $languages = $this->manager->languages($email);
        $english = $repository->content($email['class'], EmailRenderer::BASE_LOCALE);

        return $this->view('areas/admin/Email/edit.lex.php', [
            'email' => $email,
            'locale' => $locale,
            'localeName' => $registry->nativeName($locale),
            'rtl' => $registry->isRtl($locale),
            'tabs' => array_map(static fn (string $code): array => [
                'code' => $code,
                'name' => $registry->nativeName($code),
                'language' => $languages[$code] ?? null,
            ], $registry->supported()),
            'written' => isset($languages[$locale]),
            'stored' => $repository->storedContent($email['class'], $locale) !== null,
            'outdated' => $languages[$locale]['outdated'] ?? false,
            'fields' => $fields,
            'english' => $english === null ? null : [
                'subject' => $english['subject'], 'preheader' => $english['preheader'], 'body' => $english['body'],
                'footer_note' => $english['footer_note'], 'repeat' => (string) $english['repeat'],
            ],
            'hasRepeat' => $this->manager->hasRepeat($email),
            'layouts' => $repository->layouts(),
            'sample' => $this->manager->sampleData($email),
            'globals' => array_merge(EmailRenderer::GLOBALS, EmailRenderer::LAYOUT_SETTINGS),
            'problem' => $this->manager->problems([$email['class']])[$email['class']][$locale] ?? null,
            'testRecipient' => (string) (auth()->user()['email'] ?? ''),
            'formErrors' => $errors,
            'formWarnings' => $warnings,
        ])->setStatusCode($status);
    }

    /**
     * The editor's fields as posted, for showing a refused save again as typed.
     *
     * @param  array<string, mixed>  $input
     * @return array{subject: string, preheader: string, body: string, footer_note: string, repeat: ?string, layout: string}
     */
    private static function fields(array $input): array
    {
        $field = static fn (string $key): string => is_string($input[$key] ?? null) ? $input[$key] : '';

        return [
            'subject' => $field('subject'),
            'preheader' => $field('preheader'),
            'body' => $field('body'),
            'footer_note' => $field('footer_note'),
            'repeat' => $field('repeat'),
            'layout' => $field('layout'),
        ];
    }

    /**
     * A language the site offers, or the site default.
     */
    private function locale(string $wanted): string
    {
        $registry = LocaleRegistry::instance();

        return $registry->isSupported($wanted) ? $wanted : $registry->default();
    }

    /**
     * A strip above the preview with the subject and anything the draft gets wrong.
     *
     * @param  list<string>  $notes
     */
    private static function withBanner(RenderedEmail $email, array $notes): string
    {
        $banner = '<div dir="ltr" style="text-align:left;font:13px/1.5 Arial,sans-serif;background:#f1f5f9;color:#334155;padding:8px 12px;border-bottom:1px solid #e2e8f0;"><strong>Subject:</strong> <span dir="auto">'.e($email->subject).'</span></div>';

        foreach ($notes as $note) {
            $banner .= '<div dir="ltr" style="text-align:left;font:13px/1.5 Arial,sans-serif;background:#fef2f2;color:#991b1b;padding:6px 12px;border-bottom:1px solid #fecaca;">'.e($note).'</div>';
        }

        $html = (string) preg_replace('#(<body\b[^>]*>)#i', '$1'.str_replace('$', '\\$', $banner), $email->html, 1, $count);

        return $count === 1 ? $html : $banner.$email->html;
    }

    private static function textPage(RenderedEmail $email): string
    {
        return self::page(
            '<pre dir="auto" style="white-space:pre-wrap;word-break:break-word;font:13px/1.6 ui-monospace,Menlo,Consolas,monospace;margin:0;">'
            .e('Subject: '.$email->subject."\n\n".$email->text).'</pre>'
        );
    }

    private static function page(string $body): string
    {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:16px;font-family:Arial,sans-serif;">'.$body.'</body></html>';
    }

    private function actorId(): ?int
    {
        $id = auth()->user()['id'] ?? null;

        return $id === null ? null : (int) $id;
    }
}
