<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Templates\CatalogTemplateSource;
use App\Mail\Templates\EmailTemplateSource;
use App\Models\EmailComponentModel;
use App\Models\EmailTemplateModel;
use App\Models\MailableTemplateBindingModel;

/**
 * The email templates in force: what was saved in the control panel, laid
 * over the built-in catalog.
 *
 * A stored row with a built-in slug (or Mailable class) replaces that entry
 * and is reported as 'customized'; one with a new slug is 'custom'; anything
 * with no row is 'built-in'. Deleting the row is how an entry is reset.
 *
 * Everything is read in one go on first use and kept for the life of the
 * process. A subscriber fan-out renders the same template thousands of times,
 * and the tables are small. Writes go through EmailTemplateManager, which
 * calls flush().
 *
 * @phpstan-import-type Component from EmailTemplateSource
 * @phpstan-import-type Template from EmailTemplateSource
 * @phpstan-import-type Binding from EmailTemplateSource
 */
class EmailTemplateRepository implements EmailTemplateSource
{
    /** @var array{components: array<string, Component>, templates: array<string, Template>, bindings: array<string, Binding>, stored: array<string, Binding>}|null */
    private ?array $loaded = null;

    public function __construct(
        private CatalogTemplateSource $catalog,
        private EmailComponentModel $componentRows,
        private EmailTemplateModel $templateRows,
        private MailableTemplateBindingModel $bindingRows,
    ) {}

    /**
     * The shipped definitions on their own, for resets and as the fallback.
     */
    public function builtIn(): CatalogTemplateSource
    {
        return $this->catalog;
    }

    /**
     * Forget what was read, so the next lookup sees a write just made.
     */
    public function flush(): void
    {
        $this->loaded = null;
    }

    public function component(string $slug): ?array
    {
        return $this->load()['components'][$slug] ?? null;
    }

    public function components(): array
    {
        return $this->load()['components'];
    }

    public function template(string $slug): ?array
    {
        return $this->load()['templates'][$slug] ?? null;
    }

    public function templates(): array
    {
        return $this->load()['templates'];
    }

    /**
     * The binding an email is sent with: a stored one when switched on, else the built-in one.
     */
    public function binding(string $mailable): ?array
    {
        return $this->load()['bindings'][$mailable] ?? null;
    }

    public function bindings(): array
    {
        return $this->load()['bindings'];
    }

    /**
     * The saved binding for an email, switched on or not, or null when it was never changed.
     *
     * @return Binding|null
     */
    public function storedBinding(string $mailable): ?array
    {
        return $this->load()['stored'][$mailable] ?? null;
    }

    /**
     * @return array{components: array<string, Component>, templates: array<string, Template>, bindings: array<string, Binding>, stored: array<string, Binding>}
     */
    private function load(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $components = $this->catalog->components();
        foreach ($this->componentRows->allRows() as $row) {
            $slug = (string) $row['slug'];
            $components[$slug] = [
                'slug' => $slug,
                'label' => (string) $row['label'],
                'category' => (string) $row['category'],
                'description' => (string) $row['description'],
                'html' => (string) $row['html_template'],
                'text' => $row['text_template'] === null ? null : (string) $row['text_template'],
                'css' => (string) ($row['css'] ?? ''),
                'preview_data' => self::decodeMap($row['preview_data'] ?? null),
                'source' => $this->catalog->component($slug) !== null ? 'customized' : 'custom',
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }

        $templates = $this->catalog->templates();
        foreach ($this->templateRows->allWithLayout() as $row) {
            $slug = (string) $row['slug'];
            $templates[$slug] = [
                'slug' => $slug,
                'label' => (string) $row['label'],
                'category' => (string) $row['category'],
                'description' => (string) $row['description'],
                'layout' => $row['layout'],
                'source' => $this->catalog->template($slug) !== null ? 'customized' : 'custom',
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }

        $bindings = $this->catalog->bindings();
        $stored = [];
        foreach ($this->bindingRows->allRows() as $row) {
            $class = (string) $row['mailable_class'];
            $stored[$class] = [
                'mailable' => $class,
                'template' => (string) $row['template_slug'],
                'subject' => $row['subject_template'] === null ? null : (string) $row['subject_template'],
                'mapping' => self::decodeMap($row['placeholder_mapping']),
                'is_active' => (bool) $row['is_active'],
                'source' => 'customized',
                'updated_at' => $row['updated_at'] ?? null,
            ];

            if ($stored[$class]['is_active']) {
                $bindings[$class] = $stored[$class];
            }
        }

        ksort($components);
        ksort($templates);

        return $this->loaded = [
            'components' => $components,
            'templates' => $templates,
            'bindings' => $bindings,
            'stored' => $stored,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function decodeMap(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $decoded) : [];
    }
}
