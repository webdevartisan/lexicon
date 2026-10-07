<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Services\EmailManager;
use App\Services\EmailRenderer;
use App\Services\LocaleRegistry;
use App\Services\MailService;
use App\Traits\SandboxesPreviewHtml;
use Framework\Core\Response;
use Throwable;

/**
 * Email Templates: every email the site sends, the layout it uses and the
 * languages it is written in, with a preview of each and a test send.
 *
 * This is the one place to see and try an email. Emails are addressed by
 * short class name in URLs, checked against the registry, so nothing here
 * ever builds an arbitrary class.
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

            foreach ($repository->locales($email['class']) as $locale) {
                $languages[$locale] = [
                    'source' => $repository->content($email['class'], $locale)['source'] ?? 'built-in',
                    'problem' => $problems[$email['class']][$locale] ?? null,
                ];
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

    public function show(string $name): Response
    {
        $email = $this->manager->email($name);

        if ($email === null) {
            $this->flash('error', 'There is no such email.');

            return $this->redirect(self::BASE);
        }

        $repository = $this->manager->repository();
        $available = $repository->locales($email['class']);
        $registry = LocaleRegistry::instance();
        $locale = $this->requestedLocale($available);
        $layoutSlug = $repository->layoutFor($email['class']);

        return $this->view('areas/admin/Email/show.lex.php', [
            'email' => $email,
            'locale' => $locale,
            'languages' => array_map(static fn (string $code): array => [
                'code' => $code,
                'name' => $registry->nativeName($code),
                'written' => in_array($code, $available, true),
                'source' => $repository->content($email['class'], $code)['source'] ?? null,
            ], $registry->supported()),
            'layout' => $repository->layout($layoutSlug) ?? ['slug' => $layoutSlug, 'name' => $layoutSlug],
            'sample' => $this->manager->sampleData($email),
            'globals' => array_merge(EmailRenderer::GLOBALS, EmailRenderer::LAYOUT_SETTINGS),
            'problem' => $this->manager->problems([$email['class']])[$email['class']][$locale] ?? null,
            'testRecipient' => (string) (auth()->user()['email'] ?? ''),
        ]);
    }

    /**
     * The preview frame: the email as saved, in one language, with its sample
     * data. Sandboxed: see SandboxesPreviewHtml.
     */
    public function render(string $name): Response
    {
        $email = $this->manager->email($name);

        if ($email === null) {
            return $this->sandboxedHtml('<p>There is no such email.</p>', 404);
        }

        $locale = $this->requestedLocale($this->manager->repository()->locales($email['class']));

        try {
            $mail = $this->manager->buildSample($email, $locale, false);
        } catch (Throwable $e) {
            return $this->sandboxedHtml(self::page('<p style="color:#b91c1c;">This email cannot be built: '.e($e->getMessage()).'</p>', 'padding:16px;'));
        }

        if ($this->request->getParam('format', 'html') === 'text') {
            return $this->sandboxedHtml(self::page(
                '<pre style="white-space:pre-wrap;word-break:break-word;font:13px/1.6 ui-monospace,Menlo,Consolas,monospace;margin:0;">'
                .e('Subject: '.$mail->getSubject()."\n\n".$mail->getTextBody()).'</pre>',
                'padding:16px;'
            ));
        }

        $banner = '<div dir="ltr" style="text-align:left;font:13px/1.5 Arial,sans-serif;background:#f1f5f9;color:#334155;padding:8px 12px;border-bottom:1px solid #e2e8f0;"><strong>Subject:</strong> '.e($mail->getSubject()).'</div>';
        $html = (string) preg_replace('#(<body\b[^>]*>)#i', '$1'.str_replace('$', '\\$', $banner), $mail->getBody(), 1, $count);

        return $this->sandboxedHtml($count === 1 ? $html : $banner.$mail->getBody());
    }

    /**
     * Send the email as saved, in one language, built from its sample data,
     * to one address. Answers in JSON so the page stays as it is.
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

        $locale = (string) $this->request->postParam('locale', EmailRenderer::BASE_LOCALE);

        try {
            $mail = $this->manager->buildSample($email, $locale);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'message' => 'This email cannot be built: '.$e->getMessage()], 422);
        }

        try {
            $this->mailService->sendTest($mail, $recipient);
        } catch (Throwable $e) {
            // The transport's own complaint is what an admin needs to fix delivery.
            error_log('Email test send failed: '.$e->getMessage());

            return $this->json(['ok' => false, 'message' => 'The test could not be sent: '.$e->getMessage()], 502);
        }

        return $this->json(['ok' => true, 'message' => "Test sent to {$recipient}. Its subject starts with [TEST]."]);
    }

    /**
     * The language asked for in the query string when the email is written
     * in it, else the one it would be sent in to a reader of the site default.
     *
     * @param  list<string>  $available
     */
    private function requestedLocale(array $available): string
    {
        $wanted = (string) $this->request->getParam('locale', '');

        if (in_array($wanted, $available, true)) {
            return $wanted;
        }

        $default = LocaleRegistry::instance()->default();

        return in_array($default, $available, true) ? $default : EmailRenderer::BASE_LOCALE;
    }

    private static function page(string $body, string $style): string
    {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;'.$style.'font-family:Arial,sans-serif;">'.$body.'</body></html>';
    }
}
