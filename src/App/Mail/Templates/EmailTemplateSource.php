<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * Where the renderer looks up blocks, templates and the per-email bindings.
 *
 * Shapes, all plain arrays so drafts and stored rows are interchangeable:
 *
 * Component: slug, label, category, description, html, text (null = worked out
 *   from the HTML, '' = left out of the plain-text part), css, preview_data, source
 * Template: slug, label, category, description, layout (component slugs in order), source
 * Binding: mailable (class), template (slug), subject (null = the email's own),
 *   mapping (placeholder => wording), is_active, source
 *
 * source is 'built-in' (shipped in code), 'customized' (a built-in with a
 * saved override) or 'custom' (made in the control panel).
 *
 * @phpstan-type Component array{slug: string, label: string, category: string, description: string, html: string, text: ?string, css: string, preview_data: array<string, string>, source: string, updated_at?: ?string}
 * @phpstan-type Template array{slug: string, label: string, category: string, description: string, layout: list<string>, source: string, updated_at?: ?string}
 * @phpstan-type Binding array{mailable: string, template: string, subject: ?string, mapping: array<string, string>, is_active: bool, source: string, updated_at?: ?string}
 */
interface EmailTemplateSource
{
    /**
     * @return Component|null
     */
    public function component(string $slug): ?array;

    /**
     * @return array<string, Component>
     */
    public function components(): array;

    /**
     * @return Template|null
     */
    public function template(string $slug): ?array;

    /**
     * @return array<string, Template>
     */
    public function templates(): array;

    /**
     * The binding in force for an email, or null when none is set up.
     *
     * @return Binding|null
     */
    public function binding(string $mailable): ?array;

    /**
     * @return array<string, Binding> Keyed by Mailable class
     */
    public function bindings(): array;
}
