<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Mailable;
use App\Mail\Templates\DraftEmailSource;
use App\Mail\Templates\EmailHtmlLinter;
use App\Mail\Templates\EmailSource;
use App\Mail\Templates\HtmlFragment;
use App\Mail\Templates\TemplateDataException;
use App\Models\EmailContentModel;
use App\Models\EmailLayoutModel;
use App\Models\EmailSettingModel;
use RuntimeException;
use Throwable;

/**
 * What the control panel does with emails: lists them, says what data each
 * provides, checks and saves an email's words per language and its layout
 * choice, and manages layouts.
 *
 * Nothing is saved that would stop an email from being built: every save is
 * first rendered strictly, through the same code that sends email, with the
 * change laid over what is in force. Emails are addressed by short class name,
 * checked against the registry, so nothing here ever builds an arbitrary class.
 *
 * @phpstan-type Email array{class: string, short: string, name: string, description: string, group: string, sample: string}
 * @phpstan-type Result array{ok: bool, errors: list<string>, warnings: list<string>, item: array<string, mixed>}
 *
 * @phpstan-import-type Layout from EmailSource
 * @phpstan-import-type Content from EmailSource
 */
class EmailManager
{
    /** Slugs: lowercase words joined by hyphens, 2 to 64 characters. */
    public const SLUG_PATTERN = '/^[a-z][a-z0-9-]{0,62}[a-z0-9]$/';

    private const MAX_HTML = 1_000_000;

    private const MAX_FOOTER = 4000;

    public function __construct(
        private EmailContentRepository $repository,
        private EmailTemplateRegistry $registry,
        private EmailContentModel $contentRows,
        private EmailSettingModel $settingRows,
        private EmailLayoutModel $layoutRows,
        private AuditService $audit,
    ) {}

    public function repository(): EmailContentRepository
    {
        return $this->repository;
    }

    /**
     * Every email the site sends, one entry per Mailable class, keyed by its
     * short class name (the form the control panel URLs use).
     *
     * @return array<string, Email>
     */
    public function emails(): array
    {
        $emails = [];

        foreach ($this->registry->getAll() as $key => $meta) {
            $class = (string) $meta['class'];
            $short = TemplateDataException::shortName($class);

            // The registry holds each class once (EmailTemplateRegistryTest checks it).
            $emails[$short] ??= [
                'class' => $class,
                'short' => $short,
                'name' => (string) $meta['name'],
                'description' => (string) $meta['description'],
                'group' => (string) ($meta['group'] ?? 'Other'),
                'sample' => (string) $key,
            ];
        }

        return $emails;
    }

    /**
     * @return Email|null
     */
    public function email(string $short): ?array
    {
        return $this->emails()[$short] ?? null;
    }

    /**
     * An email built from its sample data in one language.
     *
     * @param  Email  $email
     * @param  bool  $strict  false shows unfilled placeholders instead of failing, for previews
     */
    public function buildSample(array $email, string $locale, bool $strict = true, ?EmailSource $source = null): Mailable
    {
        return Mailable::withTemplateRenderer(
            new EmailRenderer($source ?? $this->repository, $strict),
            fn (): Mailable => Mailable::inLocale($locale, fn (): Mailable => $this->registry->build($email['sample']))
        );
    }

    /**
     * What an email can put in its words, as display text from its sample:
     * the email's data, then what one repetition of its repeated section gets.
     *
     * Built from the shipped files, so the list works even while a saved
     * version is broken.
     *
     * @param  Email  $email
     * @return array{data: array<string, string>, repeat: array<string, string>}
     */
    public function sampleData(array $email): array
    {
        $mailable = $this->buildSample($email, EmailRenderer::BASE_LOCALE, true, $this->repository->shipped());
        $asText = static fn (mixed $value): string => $value instanceof HtmlFragment ? $value->text : (is_scalar($value) ? (string) $value : '');

        return [
            'data' => array_map($asText, $mailable->getTemplateData()),
            'repeat' => array_map($asText, $mailable->getRepeatData()),
        ];
    }

    /**
     * What stops an email from being sent as saved, per language it has.
     * An email with a problem goes out as shipped, in English, instead.
     *
     * @param  list<string>|null  $onlyClasses  Limit the check to these Mailables
     * @return array<string, array<string, string>> Mailable class => language => problem
     */
    public function problems(?array $onlyClasses = null): array
    {
        $problems = [];

        foreach ($this->emails() as $email) {
            if ($onlyClasses !== null && !in_array($email['class'], $onlyClasses, true)) {
                continue;
            }

            foreach ($this->repository->locales($email['class']) as $locale) {
                try {
                    $this->buildSample($email, $locale);
                } catch (Throwable $e) {
                    $problems[$email['class']][$locale] = $e->getMessage();
                }
            }
        }

        return $problems;
    }

    /**
     * Each language an email is written in: where its words come from, and
     * whether the English was changed after it was last saved.
     *
     * @param  Email  $email
     * @return array<string, array{source: string, updated_at: ?string, outdated: bool}>
     */
    public function languages(array $email): array
    {
        $english = $this->repository->storedContent($email['class'], EmailRenderer::BASE_LOCALE);
        $languages = [];

        foreach ($this->repository->locales($email['class']) as $locale) {
            $content = $this->repository->content($email['class'], $locale);
            $languages[$locale] = [
                'source' => $content['source'] ?? 'built-in',
                'updated_at' => $content['updated_at'] ?? null,
                'outdated' => $locale !== EmailRenderer::BASE_LOCALE
                    && $english !== null && ($content['updated_at'] ?? null) !== null
                    && (string) $english['updated_at'] > (string) $content['updated_at'],
            ];
        }

        return $languages;
    }

    /**
     * Whether an email has a repeated section (the weekly digest, once per blog).
     *
     * @param  Email  $email
     */
    public function hasRepeat(array $email): bool
    {
        return ($this->repository->shipped()->content($email['class'], EmailRenderer::BASE_LOCALE)['repeat'] ?? null) !== null;
    }

    /**
     * An email's words in one language from the editor form, checked: what
     * would be saved, the layout chosen, and what is wrong with it.
     *
     * @param  Email  $email
     * @param  array<string, mixed>  $input  subject, preheader, body, footer_note, repeat, layout
     * @return array{0: Content, 1: string, 2: list<string>, 3: list<string>} The words, the layout slug, errors, warnings
     */
    public function normalizeContent(array $email, string $locale, array $input): array
    {
        $errors = [];
        $warnings = [];
        $text = static fn (string $key): string => str_replace("\r\n", "\n", is_string($input[$key] ?? null) ? $input[$key] : '');

        $content = [
            'subject' => trim((string) preg_replace('/\s+/', ' ', $text('subject'))),
            'preheader' => trim((string) preg_replace('/\s+/', ' ', $text('preheader'))),
            'body' => trim($text('body'), "\n"),
            'footer_note' => trim($text('footer_note')),
            'repeat' => $this->hasRepeat($email) ? trim($text('repeat'), "\n") : null,
            'source' => 'custom',
            'updated_at' => null,
        ];
        $layout = trim($text('layout'));

        if (!LocaleRegistry::instance()->isSupported($locale)) {
            $errors[] = "The site does not offer the language '{$locale}'.";
        }

        if ($content['subject'] === '') {
            $errors[] = 'The subject cannot be empty.';
        } elseif (mb_strlen($content['subject']) > 255) {
            $errors[] = 'The subject is longer than 255 characters.';
        }

        if (mb_strlen($content['preheader']) > 255) {
            $errors[] = 'The preheader is longer than 255 characters.';
        }

        if (trim($content['body']) === '') {
            $errors[] = 'The body cannot be empty.';
        } elseif (strlen($content['body']) > self::MAX_HTML) {
            $errors[] = 'The body is too long.';
        }

        if (mb_strlen($content['footer_note']) > self::MAX_FOOTER) {
            $errors[] = 'The footer note is longer than '.self::MAX_FOOTER.' characters.';
        }

        if ($content['repeat'] !== null && trim($content['repeat']) === '') {
            $errors[] = 'The repeated section cannot be empty.';
        }

        if ($this->repository->layout($layout) === null) {
            $errors[] = "There is no layout '{$layout}'.";
        }

        $appUrl = (string) env('APP_URL', '');
        $parts = ['Body' => $content['body'], 'Footer note' => $content['footer_note'], 'Repeated section' => (string) $content['repeat']];

        foreach ($parts as $label => $html) {
            $lint = EmailHtmlLinter::lintHtml($html, $appUrl);
            array_push($errors, ...array_map(static fn (string $e): string => "{$label}: {$e}", $lint['errors']));
            array_push($warnings, ...array_map(static fn (string $w): string => "{$label}: {$w}", $lint['warnings']));
        }

        foreach (['Subject' => $content['subject'], 'Preheader' => $content['preheader']] as $label => $line) {
            array_push($errors, ...array_map(static fn (string $e): string => "{$label}: {$e}", EmailHtmlLinter::placeholderProblems($line)));
        }

        // Only worth building once the parts are sound on their own.
        if ($errors === []) {
            try {
                $this->buildSample($email, $locale, true, $this->draft($email, $locale, $content, $layout));
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return [$content, $layout, array_values(array_unique($errors)), array_values(array_unique($warnings))];
    }

    /**
     * What is in force with one email's unsaved words (and layout choice) laid over it.
     *
     * @param  Email  $email
     * @param  Content  $content
     */
    public function draft(array $email, string $locale, array $content, ?string $layout = null): DraftEmailSource
    {
        return new DraftEmailSource(
            $this->repository,
            [$email['class'] => [$locale => $content]],
            [],
            $layout === null || $this->repository->layout($layout) === null ? [] : [$email['class'] => $layout],
        );
    }

    /**
     * Save an email's words in one language, and the layout it uses.
     *
     * @param  Email  $email
     * @param  array<string, mixed>  $input
     * @return Result
     */
    public function saveContent(array $email, string $locale, array $input, ?int $userId, ?string $ip = null): array
    {
        [$content, $layout, $errors, $warnings] = $this->normalizeContent($email, $locale, $input);

        if ($errors !== []) {
            return self::result($errors, $warnings, $content + ['layout' => $layout]);
        }

        $this->contentRows->upsert($email['class'], $locale, $content, $userId);

        // The layout the shipped file names needs no row.
        if ($layout === $this->repository->shipped()->layoutFor($email['class'])) {
            $this->settingRows->deleteByClass($email['class']);
        } else {
            $this->settingRows->setLayout($email['class'], $layout, $userId);
        }

        $this->repository->flush();
        $this->audit->log($userId, 'email.content_saved', 'email', null, ['email' => $email['short'], 'locale' => $locale, 'layout' => $layout], $ip);

        return self::result([], $warnings, $content + ['layout' => $layout]);
    }

    /**
     * Drop an email's saved words in one language: English goes back to the
     * shipped file, any other language is no longer written.
     *
     * @param  Email  $email
     * @return Result
     */
    public function resetContent(array $email, string $locale, ?int $userId, ?string $ip = null): array
    {
        if (!$this->contentRows->deleteOne($email['class'], $locale)) {
            return self::result(['There is nothing saved to reset.'], [], []);
        }

        $this->repository->flush();
        $this->audit->log($userId, 'email.content_reset', 'email', null, ['email' => $email['short'], 'locale' => $locale], $ip);

        return self::result([], [], []);
    }

    // ------------------------------------------------------------------
    // Layouts
    // ------------------------------------------------------------------

    /**
     * The emails sent in a layout.
     *
     * @return array<string, Email> Keyed by short class name
     */
    public function emailsUsingLayout(string $slug): array
    {
        return array_filter($this->emails(), fn (array $email): bool => $this->repository->layoutFor($email['class']) === $slug);
    }

    /**
     * A layout from the editor form, checked.
     *
     * @param  array<string, mixed>  $input  slug (new layouts only), name, html, primary_color, background_color, support_email, company_address
     * @param  string|null  $existingSlug  The layout being edited; its slug cannot change
     * @return array{0: Layout, 1: list<string>, 2: list<string>} The layout, errors, warnings
     */
    public function normalizeLayout(array $input, ?string $existingSlug): array
    {
        $errors = [];
        $field = static fn (string $key): string => is_string($input[$key] ?? null) ? trim(str_replace("\r\n", "\n", $input[$key])) : '';

        $layout = [
            'slug' => $existingSlug ?? strtolower($field('slug')),
            'name' => $field('name'),
            'html' => $field('html'),
            'primary_color' => strtoupper($field('primary_color')),
            'background_color' => strtoupper($field('background_color')),
            'support_email' => $field('support_email'),
            'company_address' => $field('company_address'),
            'source' => 'custom',
        ];

        if ($existingSlug === null) {
            if (!preg_match(self::SLUG_PATTERN, $layout['slug'])) {
                $errors[] = 'The slug must be 2 to 64 lowercase letters, numbers and hyphens, starting with a letter.';
            } elseif ($this->repository->layout($layout['slug']) !== null) {
                $errors[] = "A layout called '{$layout['slug']}' already exists.";
            }
        }

        if ($layout['name'] === '' || mb_strlen($layout['name']) > 100) {
            $errors[] = 'The name must be 1 to 100 characters.';
        }

        foreach (['primary_color' => 'Primary colour', 'background_color' => 'Background colour'] as $key => $label) {
            if (!preg_match('/^#[0-9A-F]{6}$/', $layout[$key])) {
                $errors[] = "{$label} must be a colour such as #4F46E5.";
            }
        }

        if ($layout['support_email'] !== '' && !filter_var($layout['support_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'The support email is not a valid address.';
        }

        if (mb_strlen($layout['company_address']) > 500) {
            $errors[] = 'The company address is longer than 500 characters.';
        }

        if (strlen($layout['html']) > self::MAX_HTML) {
            $errors[] = 'The HTML is too long.';
        }

        foreach (['content', 'footer_note'] as $required) {
            if (!in_array($required, EmailRenderer::placeholdersIn($layout['html']), true)) {
                $errors[] = 'The HTML must have {{ '.$required.' }}, where '.($required === 'content' ? "each email's words go." : 'each email says why it was sent.');
            }
        }

        $lint = EmailHtmlLinter::lintHtml($layout['html'], (string) env('APP_URL', ''), true);
        $errors = array_merge($errors, $lint['errors']);

        // Every email already sent in this layout has to still build with it.
        if ($errors === []) {
            $draft = new DraftEmailSource($this->repository, [], [$layout['slug'] => $layout]);

            foreach ($this->emailsUsingLayout($layout['slug']) as $email) {
                foreach ($this->repository->locales($email['class']) as $locale) {
                    try {
                        $this->buildSample($email, $locale, true, $draft);
                    } catch (RuntimeException $e) {
                        $errors[] = "{$email['name']} ({$locale}): ".$e->getMessage();
                    }
                }
            }
        }

        return [$layout, array_values(array_unique($errors)), $lint['warnings']];
    }

    /**
     * Create a layout, or save changes to one.
     *
     * @param  array<string, mixed>  $input
     * @return Result
     */
    public function saveLayout(?string $existingSlug, array $input, ?int $userId, ?string $ip = null): array
    {
        [$layout, $errors, $warnings] = $this->normalizeLayout($input, $existingSlug);

        if ($errors !== []) {
            return self::result($errors, $warnings, $layout);
        }

        $this->layoutRows->upsert($layout, $userId);
        $this->repository->flush();
        $this->audit->log($userId, $existingSlug === null ? 'email.layout_created' : 'email.layout_updated', 'email_layout', null, ['slug' => $layout['slug']], $ip);

        return self::result([], $warnings, $layout);
    }

    /**
     * Go back to the shipped file of a customized layout.
     *
     * @return Result
     */
    public function resetLayout(string $slug, ?int $userId, ?string $ip = null): array
    {
        if ($this->repository->shipped()->layout($slug) === null) {
            return self::result(['Only a layout that ships with the site can be reset. Delete this one instead.'], [], []);
        }

        if (!$this->layoutRows->deleteBySlug($slug)) {
            return self::result(['This layout has no changes to reset.'], [], []);
        }

        $this->repository->flush();
        $this->audit->log($userId, 'email.layout_reset', 'email_layout', null, ['slug' => $slug], $ip);

        return self::result([], [], []);
    }

    /**
     * Delete a layout made in the control panel, once no email uses it.
     *
     * @return Result
     */
    public function deleteLayout(string $slug, ?int $userId, ?string $ip = null): array
    {
        if ($this->repository->shipped()->layout($slug) !== null) {
            return self::result(['A layout that ships with the site cannot be deleted.'], [], []);
        }

        $users = $this->emailsUsingLayout($slug);

        if ($users !== []) {
            return self::result(['These emails use it, so move them to another layout first: '.implode(', ', array_column($users, 'name')).'.'], [], []);
        }

        if (!$this->layoutRows->deleteBySlug($slug)) {
            return self::result(['That layout no longer exists.'], [], []);
        }

        $this->repository->flush();
        $this->audit->log($userId, 'email.layout_deleted', 'email_layout', null, ['slug' => $slug], $ip);

        return self::result([], [], []);
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $item
     * @return Result
     */
    private static function result(array $errors, array $warnings, array $item): array
    {
        return ['ok' => $errors === [], 'errors' => $errors, 'warnings' => $warnings, 'item' => $item];
    }

    /**
     * Mailable classes in the codebase that the registry does not list, so
     * they have no entry under Emails.
     *
     * @return string[]
     */
    public function unregisteredClasses(): array
    {
        return $this->registry->unregisteredClasses();
    }
}
