<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * Where emails come from: layouts, which layout each email uses, and each
 * email's words per language.
 *
 * A layout is a whole HTML document with {{ content }} where the email goes.
 * An email's content is a subject, a preheader, a body and a footer note,
 * plus a repeated section for the few emails that repeat one.
 *
 * @phpstan-type Layout array{slug: string, name: string, html: string, primary_color: string, background_color: string, support_email: string, company_address: string, source: string}
 * @phpstan-type Content array{subject: string, preheader: string, body: string, footer_note: string, repeat: ?string, source: string, updated_at: ?string}
 */
interface EmailSource
{
    /** The layout every email falls back to. */
    public const DEFAULT_LAYOUT = 'default';

    /**
     * @return Layout|null
     */
    public function layout(string $slug): ?array;

    /**
     * @return array<string, Layout> Keyed by slug
     */
    public function layouts(): array;

    /**
     * The slug of the layout an email is sent in.
     *
     * @param  class-string|string  $mailable
     */
    public function layoutFor(string $mailable): string;

    /**
     * An email's words in one language, or null when it has none in that language.
     *
     * @param  class-string|string  $mailable
     * @return Content|null
     */
    public function content(string $mailable, string $locale): ?array;

    /**
     * The languages an email has words in.
     *
     * @param  class-string|string  $mailable
     * @return list<string>
     */
    public function locales(string $mailable): array;
}
