<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * What the templates would look like with an unsaved change applied.
 *
 * Wraps the live source and swaps in edited entries, so a draft can be
 * previewed, and every email that would be affected can be rendered against
 * it, before anything is written. A null override means "gone": the entry is
 * treated as deleted.
 *
 * @phpstan-import-type Component from EmailTemplateSource
 * @phpstan-import-type Template from EmailTemplateSource
 * @phpstan-import-type Binding from EmailTemplateSource
 */
final class DraftTemplateSource implements EmailTemplateSource
{
    /**
     * @param  array<string, Component|null>  $components
     * @param  array<string, Template|null>  $templates
     * @param  array<string, Binding|null>  $bindings
     */
    public function __construct(
        private EmailTemplateSource $base,
        private array $components = [],
        private array $templates = [],
        private array $bindings = [],
    ) {}

    public function component(string $slug): ?array
    {
        return array_key_exists($slug, $this->components) ? $this->components[$slug] : $this->base->component($slug);
    }

    public function components(): array
    {
        return array_filter(array_replace($this->base->components(), $this->components));
    }

    public function template(string $slug): ?array
    {
        return array_key_exists($slug, $this->templates) ? $this->templates[$slug] : $this->base->template($slug);
    }

    public function templates(): array
    {
        return array_filter(array_replace($this->base->templates(), $this->templates));
    }

    public function binding(string $mailable): ?array
    {
        return array_key_exists($mailable, $this->bindings) ? $this->bindings[$mailable] : $this->base->binding($mailable);
    }

    public function bindings(): array
    {
        return array_filter(array_replace($this->base->bindings(), $this->bindings));
    }
}
