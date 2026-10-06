<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * The blocks, templates and bindings that ship with the code.
 *
 * Read from resources/mail/catalog.php. These are the defaults every install
 * starts from and what "Reset to default" goes back to. They are also what an
 * email falls back to when a saved customization cannot render, so a mistake
 * made in the control panel never stops a password reset from going out.
 *
 * @phpstan-import-type Component from EmailTemplateSource
 * @phpstan-import-type Template from EmailTemplateSource
 * @phpstan-import-type Binding from EmailTemplateSource
 */
final class CatalogTemplateSource implements EmailTemplateSource
{
    /** Block categories, in the order the library lists them. */
    public const COMPONENT_CATEGORIES = [
        'layout' => 'Layout',
        'content' => 'Content',
        'callout' => 'Callouts',
        'action' => 'Actions',
        'data' => 'Data rows',
    ];

    public const TEMPLATE_CATEGORIES = [
        'transactional' => 'Transactional',
        'alert' => 'Alert',
        'digest' => 'Digest',
        'promotional' => 'Promotional',
    ];

    /** @var array<string, mixed>|null The shipped file, read once per process */
    private static ?array $shipped = null;

    /** @var array<string, Component> */
    private array $components = [];

    /** @var array<string, Template> */
    private array $templates = [];

    /** @var array<string, Binding> */
    private array $bindings = [];

    /**
     * @param  array<string, mixed>|null  $catalog  Definitions to use instead of the shipped file (tests)
     */
    public function __construct(?array $catalog = null)
    {
        $catalog ??= self::$shipped ??= require dirname(__DIR__, 4).'/resources/mail/catalog.php';

        foreach ($catalog['components'] ?? [] as $slug => $c) {
            $this->components[$slug] = [
                'slug' => $slug,
                'label' => (string) $c['label'],
                'category' => (string) ($c['category'] ?? 'content'),
                'description' => (string) ($c['description'] ?? ''),
                'html' => (string) $c['html'],
                'text' => isset($c['text']) ? (string) $c['text'] : null,
                'css' => (string) ($c['css'] ?? ''),
                'preview_data' => array_map('strval', $c['preview_data'] ?? []),
                'source' => 'built-in',
            ];
        }

        foreach ($catalog['templates'] ?? [] as $slug => $t) {
            $this->templates[$slug] = [
                'slug' => $slug,
                'label' => (string) $t['label'],
                'category' => (string) ($t['category'] ?? 'transactional'),
                'description' => (string) ($t['description'] ?? ''),
                'layout' => array_values(array_map('strval', $t['layout'])),
                'source' => 'built-in',
            ];
        }

        foreach ($catalog['bindings'] ?? [] as $mailable => $b) {
            $this->bindings[$mailable] = [
                'mailable' => $mailable,
                'template' => (string) $b['template'],
                'subject' => isset($b['subject']) ? (string) $b['subject'] : null,
                'mapping' => array_map('strval', $b['mapping'] ?? []),
                'is_active' => true,
                'source' => 'built-in',
            ];
        }
    }

    public function component(string $slug): ?array
    {
        return $this->components[$slug] ?? null;
    }

    public function components(): array
    {
        return $this->components;
    }

    public function template(string $slug): ?array
    {
        return $this->templates[$slug] ?? null;
    }

    public function templates(): array
    {
        return $this->templates;
    }

    public function binding(string $mailable): ?array
    {
        return $this->bindings[$mailable] ?? null;
    }

    public function bindings(): array
    {
        return $this->bindings;
    }
}
