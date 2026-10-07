<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * A piece of email content in both of its forms: markup for the HTML part and
 * words for the plain-text part.
 *
 * This is how a Mailable hands over something that is already markup. Plain
 * strings are always escaped when they reach a template, so the only way to
 * put HTML into an email is to build one of these, and the code doing that is
 * the code that knows where the content came from. A template author can move
 * a value around but can never turn its escaping off.
 */
final readonly class HtmlFragment
{
    private function __construct(
        public string $html,
        public string $text,
    ) {}

    /**
     * Text written by a person or computed by code: escaped, line breaks kept.
     */
    public static function fromText(string $text): self
    {
        $html = nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'), false);

        return new self($html, $text);
    }

    /**
     * Markup that is already safe, with its plain-text reading.
     *
     * Only for content the renderer produced itself. Never pass anything a
     * person typed through here.
     *
     * @param  string|null  $text  Plain-text rendition; worked out from the markup when null
     */
    public static function trusted(string $html, ?string $text = null): self
    {
        return new self($html, $text ?? HtmlToText::convert($html));
    }

    public static function empty(): self
    {
        return new self('', '');
    }

    /**
     * Several fragments in a row, e.g. one section per blog in a digest.
     *
     * @param  list<self>  $fragments
     */
    public static function join(array $fragments, string $textSeparator = "\n"): self
    {
        $fragments = array_values(array_filter($fragments, static fn (self $f): bool => !$f->isEmpty()));

        return new self(
            implode('', array_map(static fn (self $f): string => $f->html, $fragments)),
            implode($textSeparator, array_map(static fn (self $f): string => $f->text, $fragments)),
        );
    }

    public function isEmpty(): bool
    {
        return trim($this->html) === '' && trim($this->text) === '';
    }
}
