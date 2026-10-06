<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Mailable;
use App\Mail\Templates\CatalogTemplateSource;
use App\Mail\Templates\DraftTemplateSource;
use App\Mail\Templates\EmailHtmlLinter;
use App\Mail\Templates\EmailTemplateSource;
use App\Mail\Templates\HtmlFragment;
use App\Mail\Templates\TemplateDataException;
use App\Models\EmailComponentModel;
use App\Models\EmailTemplateModel;
use App\Models\MailableTemplateBindingModel;
use Throwable;

/**
 * Every change to email blocks, templates and bindings goes through here.
 *
 * Nothing is written until every registered email has been rendered against
 * the changed version, strictly, with its sample data. That is the whole
 * validation story: rather than reasoning about which placeholder a change
 * might orphan, the change is tried on the real emails, and anything that
 * would no longer render is reported back by name. So the control panel can
 * never save a state in which an email cannot be built, and the runtime
 * fallback stays a safety net for things like a database outage.
 *
 * Results come back as ['ok' => bool, 'errors' => list, 'warnings' => list,
 * 'item' => the normalized entry], so a form can be shown again as typed.
 *
 * @phpstan-import-type Component from EmailTemplateSource
 * @phpstan-import-type Template from EmailTemplateSource
 * @phpstan-import-type Binding from EmailTemplateSource
 *
 * @phpstan-type Result array{ok: bool, errors: list<string>, warnings: list<string>, item: array<string, mixed>}
 */
class EmailTemplateManager
{
    /** Slugs: lowercase words joined by hyphens, 2 to 64 characters. */
    public const SLUG_PATTERN = '/^[a-z][a-z0-9-]{0,62}[a-z0-9]$/';

    private const MAX_HTML = 65535;

    private const MAX_CSS = 16384;

    private const MAX_WORDING = 4000;

    private const MAX_PREVIEW_VALUE = 2000;

    private const MAX_LAYOUT = 30;

    public function __construct(
        private EmailTemplateRepository $repository,
        private EmailComponentModel $componentRows,
        private EmailTemplateModel $templateRows,
        private MailableTemplateBindingModel $bindingRows,
        private EmailTemplateRegistry $registry,
        private AuditService $audit,
    ) {}

    public function repository(): EmailTemplateRepository
    {
        return $this->repository;
    }

    /**
     * The live templates with unsaved changes laid on top.
     *
     * @param  array<string, Component|null>  $components
     * @param  array<string, Template|null>  $templates
     * @param  array<string, Binding|null>  $bindings
     */
    public function draft(array $components = [], array $templates = [], array $bindings = []): DraftTemplateSource
    {
        return new DraftTemplateSource($this->repository, $components, $templates, $bindings);
    }

    // ------------------------------------------------------------------
    // Emails
    // ------------------------------------------------------------------

    /**
     * Every email the site sends, one entry per Mailable class, keyed by its
     * short class name (the form the control panel URLs use).
     *
     * @return array<string, array{class: string, short: string, name: string, description: string, group: string, samples: list<string>}>
     */
    public function emails(): array
    {
        $emails = [];

        foreach ($this->registry->getAll() as $key => $meta) {
            $class = (string) $meta['class'];
            $short = TemplateDataException::shortName($class);

            if (!isset($emails[$short])) {
                $emails[$short] = [
                    'class' => $class,
                    'short' => $short,
                    // Variants are registered as "Name — Variant"; the email itself is "Name".
                    'name' => explode(' — ', (string) $meta['name'])[0],
                    'description' => (string) $meta['description'],
                    'group' => (string) ($meta['group'] ?? 'Other'),
                    'samples' => [],
                ];
            }

            $emails[$short]['samples'][] = (string) $key;
        }

        return $emails;
    }

    /**
     * @return array{class: string, short: string, name: string, description: string, group: string, samples: list<string>}|null
     */
    public function email(string $short): ?array
    {
        return $this->emails()[$short] ?? null;
    }

    /**
     * What an email gives its template, as display text, from one of its samples.
     *
     * Built against the shipped templates, so listing an email's data works
     * even while its stored template is broken.
     *
     * @return array<string, string>
     */
    public function sampleData(string $sampleKey): array
    {
        $mailable = $this->buildSample(new CatalogTemplateSource(), $sampleKey, true);

        return array_map(
            static fn (mixed $value): string => $value instanceof HtmlFragment ? $value->text : (is_scalar($value) ? (string) $value : ''),
            $mailable->getTemplateData() + ['subject' => $mailable->getSubject()]
        );
    }

    /**
     * Build an email's sample against a given set of templates.
     *
     * @param  bool  $strict  false shows unfilled placeholders instead of failing, for previews
     *
     * @throws Throwable When the email cannot be built
     */
    public function buildSample(EmailTemplateSource $source, string $sampleKey, bool $strict): Mailable
    {
        return Mailable::withTemplateRenderer(
            new TemplateRendererService($source, $strict),
            fn (): Mailable => $this->registry->build($sampleKey)
        );
    }

    /**
     * What would stop emails from rendering against $source, one line per email.
     *
     * @param  list<string>|null  $onlyClasses  Limit the check to these Mailables
     * @return array<string, string> Mailable class => problem
     */
    public function problems(EmailTemplateSource $source, ?array $onlyClasses = null): array
    {
        $problems = [];

        foreach ($this->registry->getAll() as $key => $meta) {
            $class = (string) $meta['class'];

            if (isset($problems[$class]) || ($onlyClasses !== null && !in_array($class, $onlyClasses, true))) {
                continue;
            }

            try {
                $this->buildSample($source, (string) $key, true);
            } catch (Throwable $e) {
                $problems[$class] = $e->getMessage();
            }
        }

        return $problems;
    }

    /**
     * Emails whose template is $slug, as short class names.
     *
     * @return list<string>
     */
    public function emailsUsingTemplate(string $slug): array
    {
        $using = [];

        foreach ($this->emails() as $short => $email) {
            $live = $this->repository->binding($email['class']);
            $stored = $this->repository->storedBinding($email['class']);

            if (($live['template'] ?? null) === $slug || ($stored['template'] ?? null) === $slug) {
                $using[] = $short;
            }
        }

        return $using;
    }

    /**
     * Templates whose layout includes block $slug.
     *
     * @return list<string> Template slugs
     */
    public function templatesUsingComponent(string $slug): array
    {
        return array_keys(array_filter(
            $this->repository->templates(),
            static fn (array $template): bool => in_array($slug, $template['layout'], true)
        ));
    }

    // ------------------------------------------------------------------
    // Components
    // ------------------------------------------------------------------

    /**
     * Turn form input into a block, with what is wrong with it.
     *
     * @param  array<string, mixed>  $input  slug, label, category, description, html, text_mode (auto|custom|none), text, css, preview (name => value)
     * @param  string|null  $existingSlug  The block being edited; its slug cannot change
     * @return array{0: Component, 1: list<string>, 2: list<string>} The block, errors, warnings
     */
    public function normalizeComponent(array $input, ?string $existingSlug): array
    {
        $errors = [];
        $warnings = [];
        $appUrl = (string) env('APP_URL', '');

        $slug = $existingSlug ?? strtolower(trim((string) ($input['slug'] ?? '')));

        if ($existingSlug === null) {
            if (!preg_match(self::SLUG_PATTERN, $slug)) {
                $errors[] = 'The slug must be 2 to 64 lowercase letters, numbers and hyphens, starting with a letter.';
            } elseif ($this->repository->component($slug) !== null) {
                $errors[] = "A block called '{$slug}' already exists.";
            }
        }

        $label = trim((string) ($input['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 100) {
            $errors[] = 'Give the block a name of up to 100 characters.';
        }

        $category = (string) ($input['category'] ?? '');
        if (!isset(CatalogTemplateSource::COMPONENT_CATEGORIES[$category])) {
            $errors[] = 'Choose a category.';
        }

        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 255) {
            $errors[] = 'Keep the description under 255 characters.';
        }

        $html = trim(str_replace("\r\n", "\n", (string) ($input['html'] ?? '')));
        if ($html === '') {
            $errors[] = 'The block needs some HTML.';
        } elseif (strlen($html) > self::MAX_HTML) {
            $errors[] = 'The HTML is too long.';
        } else {
            $lint = EmailHtmlLinter::lintHtml($html, $appUrl);
            $errors = array_merge($errors, $lint['errors']);
            $warnings = array_merge($warnings, $lint['warnings']);
        }

        $text = match ((string) ($input['text_mode'] ?? 'auto')) {
            'none' => '',
            'custom' => str_replace("\r\n", "\n", trim((string) ($input['text'] ?? ''))),
            default => null,
        };
        if ($text !== null && ($input['text_mode'] ?? '') === 'custom') {
            if ($text === '') {
                $errors[] = 'Write the plain text, or let it be worked out from the HTML.';
            }
            $errors = array_merge($errors, array_map(
                static fn (string $e): string => 'Plain text: '.$e,
                array_filter(EmailHtmlLinter::placeholderProblems($text), static fn (string $e): bool => !str_contains($e, 'inside a tag'))
            ));
        }

        $css = trim(str_replace("\r\n", "\n", (string) ($input['css'] ?? '')));
        if (strlen($css) > self::MAX_CSS) {
            $errors[] = 'The CSS is too long.';
        } elseif ($css !== '') {
            $lint = EmailHtmlLinter::lintCss($css, $appUrl);
            $errors = array_merge($errors, $lint['errors']);
            $warnings = array_merge($warnings, $lint['warnings']);
        }

        $component = [
            'slug' => $slug,
            'label' => $label,
            'category' => $category,
            'description' => $description,
            'html' => $html,
            'text' => $text,
            'css' => $css,
            'preview_data' => [],
            'source' => $existingSlug !== null && $this->repository->builtIn()->component($slug) !== null ? 'customized' : 'custom',
        ];

        // Sample values are kept only for placeholders the block still has.
        $preview = is_array($input['preview'] ?? null) ? $input['preview'] : [];
        foreach (TemplateRendererService::placeholdersOfComponent($component) as $name) {
            if (isset($preview[$name]) && is_scalar($preview[$name])) {
                $component['preview_data'][$name] = mb_substr((string) $preview[$name], 0, self::MAX_PREVIEW_VALUE);
            }
        }

        return [$component, array_values(array_unique($errors)), array_values(array_unique($warnings))];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Result
     */
    public function saveComponent(?string $existingSlug, array $input, ?int $userId, ?string $ip = null): array
    {
        if ($existingSlug !== null && $this->repository->component($existingSlug) === null) {
            return self::result(['That block no longer exists.'], [], $input);
        }

        [$component, $errors, $warnings] = $this->normalizeComponent($input, $existingSlug);

        if ($errors === []) {
            $errors = self::breakage($this->problems($this->draft([$component['slug'] => $component])));
        }

        if ($errors !== []) {
            return self::result($errors, $warnings, $component);
        }

        $this->componentRows->upsert($component, $userId);
        $this->repository->flush();
        $this->audit->log($userId, $existingSlug === null ? 'email_template.component_created' : 'email_template.component_updated', 'email_component', null, ['slug' => $component['slug']], $ip);

        return self::result([], $warnings, $component);
    }

    /**
     * Go back to the shipped version of a customized block.
     *
     * @return Result
     */
    public function resetComponent(string $slug, ?int $userId, ?string $ip = null): array
    {
        $builtIn = $this->repository->builtIn()->component($slug);
        $current = $this->repository->component($slug);

        if ($builtIn === null || $current === null || $current['source'] !== 'customized') {
            return self::result(['Only a customized built-in block can be reset.'], [], ['slug' => $slug]);
        }

        $errors = self::breakage($this->problems($this->draft([$slug => $builtIn])));
        if ($errors !== []) {
            return self::result($errors, [], $current);
        }

        $this->componentRows->deleteBySlug($slug);
        $this->repository->flush();
        $this->audit->log($userId, 'email_template.component_reset', 'email_component', null, ['slug' => $slug], $ip);

        return self::result([], [], $builtIn);
    }

    /**
     * @return Result
     */
    public function deleteComponent(string $slug, ?int $userId, ?string $ip = null): array
    {
        $current = $this->repository->component($slug);

        if ($current === null || $current['source'] !== 'custom') {
            return self::result(['Built-in blocks cannot be deleted, only reset.'], [], ['slug' => $slug]);
        }

        $usedBy = $this->templatesUsingComponent($slug);
        if ($usedBy !== []) {
            return self::result(['This block is used by '.self::names($usedBy).'. Take it out of those templates first.'], [], $current);
        }

        $this->componentRows->deleteBySlug($slug);
        $this->repository->flush();
        $this->audit->log($userId, 'email_template.component_deleted', 'email_component', null, ['slug' => $slug], $ip);

        return self::result([], [], $current);
    }

    // ------------------------------------------------------------------
    // Templates
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input  slug, label, category, description, layout (list of block slugs)
     * @return array{0: Template, 1: list<string>}
     */
    public function normalizeTemplate(array $input, ?string $existingSlug): array
    {
        $errors = [];
        $slug = $existingSlug ?? strtolower(trim((string) ($input['slug'] ?? '')));

        if ($existingSlug === null) {
            if (!preg_match(self::SLUG_PATTERN, $slug)) {
                $errors[] = 'The slug must be 2 to 64 lowercase letters, numbers and hyphens, starting with a letter.';
            } elseif ($this->repository->template($slug) !== null) {
                $errors[] = "A template called '{$slug}' already exists.";
            }
        }

        $label = trim((string) ($input['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 100) {
            $errors[] = 'Give the template a name of up to 100 characters.';
        }

        $category = (string) ($input['category'] ?? '');
        if (!isset(CatalogTemplateSource::TEMPLATE_CATEGORIES[$category])) {
            $errors[] = 'Choose a category.';
        }

        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 255) {
            $errors[] = 'Keep the description under 255 characters.';
        }

        $layout = array_values(array_filter(
            array_map(static fn ($s): string => is_scalar($s) ? (string) $s : '', is_array($input['layout'] ?? null) ? $input['layout'] : []),
            static fn (string $s): bool => $s !== ''
        ));

        if ($layout === []) {
            $errors[] = 'Add at least one block.';
        } elseif (count($layout) > self::MAX_LAYOUT) {
            $errors[] = 'A template can hold at most '.self::MAX_LAYOUT.' blocks.';
        }

        foreach (array_unique($layout) as $component) {
            if ($this->repository->component($component) === null) {
                $errors[] = "There is no block called '{$component}'.";
            }
        }

        $template = [
            'slug' => $slug,
            'label' => $label,
            'category' => $category,
            'description' => $description,
            'layout' => $layout,
            'source' => $existingSlug !== null && $this->repository->builtIn()->template($slug) !== null ? 'customized' : 'custom',
        ];

        return [$template, $errors];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Result
     */
    public function saveTemplate(?string $existingSlug, array $input, ?int $userId, ?string $ip = null): array
    {
        if ($existingSlug !== null && $this->repository->template($existingSlug) === null) {
            return self::result(['That template no longer exists.'], [], $input);
        }

        [$template, $errors] = $this->normalizeTemplate($input, $existingSlug);

        if ($errors === []) {
            $errors = self::breakage($this->problems($this->draft([], [$template['slug'] => $template])));
        }

        if ($errors !== []) {
            return self::result($errors, [], $template);
        }

        $this->templateRows->upsert($template, $userId);
        $this->repository->flush();
        $this->audit->log($userId, $existingSlug === null ? 'email_template.template_created' : 'email_template.template_updated', 'email_template', null, ['slug' => $template['slug'], 'layout' => $template['layout']], $ip);

        return self::result([], [], $template);
    }

    /**
     * @return Result
     */
    public function resetTemplate(string $slug, ?int $userId, ?string $ip = null): array
    {
        $builtIn = $this->repository->builtIn()->template($slug);
        $current = $this->repository->template($slug);

        if ($builtIn === null || $current === null || $current['source'] !== 'customized') {
            return self::result(['Only a customized built-in template can be reset.'], [], ['slug' => $slug]);
        }

        $errors = self::breakage($this->problems($this->draft([], [$slug => $builtIn])));
        if ($errors !== []) {
            return self::result($errors, [], $current);
        }

        $this->templateRows->deleteBySlug($slug);
        $this->repository->flush();
        $this->audit->log($userId, 'email_template.template_reset', 'email_template', null, ['slug' => $slug], $ip);

        return self::result([], [], $builtIn);
    }

    /**
     * @return Result
     */
    public function deleteTemplate(string $slug, ?int $userId, ?string $ip = null): array
    {
        $current = $this->repository->template($slug);

        if ($current === null || $current['source'] !== 'custom') {
            return self::result(['Built-in templates cannot be deleted, only reset.'], [], ['slug' => $slug]);
        }

        $usedBy = $this->emailsUsingTemplate($slug);
        if ($usedBy !== []) {
            return self::result(['These emails use this template: '.self::names($usedBy).'. Move them to another template first.'], [], $current);
        }

        $this->templateRows->deleteBySlug($slug);
        $this->repository->flush();
        $this->audit->log($userId, 'email_template.template_deleted', 'email_template', null, ['slug' => $slug], $ip);

        return self::result([], [], $current);
    }

    // ------------------------------------------------------------------
    // Bindings
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input  template, subject, mapping (placeholder => wording), is_active
     * @return array{0: Binding, 1: list<string>, 2: list<string>}
     */
    public function normalizeBinding(string $mailable, array $input): array
    {
        $errors = [];
        $warnings = [];
        $appUrl = (string) env('APP_URL', '');

        $templateSlug = (string) ($input['template'] ?? '');
        $template = $this->repository->template($templateSlug);
        if ($template === null) {
            $errors[] = 'Choose a template.';
        }

        $subject = trim((string) preg_replace('/\s+/', ' ', (string) ($input['subject'] ?? '')));
        if (mb_strlen($subject) > 255) {
            $errors[] = 'Keep the subject line under 255 characters.';
        }
        foreach (EmailHtmlLinter::placeholderProblems($subject) as $problem) {
            $errors[] = 'Subject line: '.$problem;
        }

        $posted = is_array($input['mapping'] ?? null) ? $input['mapping'] : [];
        $mapping = [];
        $renderer = new TemplateRendererService($this->repository);

        foreach ($template === null ? [] : $renderer->placeholdersForTemplate($template) as $name) {
            if (!array_key_exists($name, $posted) || !is_scalar($posted[$name])) {
                continue;
            }

            $wording = trim(str_replace("\r\n", "\n", (string) $posted[$name]));

            if (strlen($wording) > self::MAX_WORDING) {
                $errors[] = "The wording for {{ {$name} }} is too long.";

                continue;
            }

            $lint = EmailHtmlLinter::lintHtml($wording, $appUrl);
            foreach ($lint['errors'] as $problem) {
                $errors[] = "Wording for {{ {$name} }}: {$problem}";
            }
            foreach ($lint['warnings'] as $problem) {
                $warnings[] = "Wording for {{ {$name} }}: {$problem}";
            }

            $mapping[$name] = $wording;
        }

        $binding = [
            'mailable' => $mailable,
            'template' => $templateSlug,
            'subject' => $subject === '' ? null : $subject,
            'mapping' => $mapping,
            'is_active' => !empty($input['is_active']),
            'source' => 'customized',
        ];

        return [$binding, array_values(array_unique($errors)), array_values(array_unique($warnings))];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Result
     */
    public function saveBinding(string $mailable, array $input, ?int $userId, ?string $ip = null): array
    {
        [$binding, $errors, $warnings] = $this->normalizeBinding($mailable, $input);

        // Checked as if switched on, so a binding kept switched off still works the day it is switched on.
        if ($errors === []) {
            $errors = self::breakage($this->problems($this->draft([], [], [$mailable => ['is_active' => true] + $binding]), [$mailable]));
        }

        if ($errors !== []) {
            return self::result($errors, $warnings, $binding);
        }

        $this->bindingRows->upsert($binding, $userId);
        $this->repository->flush();
        $this->audit->log($userId, 'email_template.binding_updated', 'mailable_template_binding', null, [
            'mailable' => $mailable,
            'template' => $binding['template'],
            'is_active' => $binding['is_active'],
        ], $ip);

        return self::result([], $warnings, $binding);
    }

    /**
     * Drop an email's saved binding so it is sent with the shipped one again.
     *
     * @return Result
     */
    public function resetBinding(string $mailable, ?int $userId, ?string $ip = null): array
    {
        if ($this->repository->storedBinding($mailable) === null) {
            return self::result(['This email already uses its built-in template.'], [], ['mailable' => $mailable]);
        }

        $this->bindingRows->deleteByClass($mailable);
        $this->repository->flush();
        $this->audit->log($userId, 'email_template.binding_reset', 'mailable_template_binding', null, ['mailable' => $mailable], $ip);

        return self::result([], [], ['mailable' => $mailable]);
    }

    // ------------------------------------------------------------------

    /**
     * @param  array<string, string>  $problems  Mailable class => problem
     * @return list<string>
     */
    private static function breakage(array $problems): array
    {
        $lines = [];

        foreach ($problems as $class => $problem) {
            $lines[] = TemplateDataException::shortName($class).': '.$problem;
        }

        return $lines === [] ? [] : array_merge(['Saving this would stop these emails from being built:'], $lines);
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
     * @param  list<string>  $names
     */
    private static function names(array $names): string
    {
        return implode(', ', $names);
    }
}
