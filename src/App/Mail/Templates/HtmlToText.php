<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * Turns email markup into the plain-text part of the same email.
 *
 * Deliberately small: email markup is simple, and the output only has to
 * read well in a text-only client. Blocks become paragraphs, line breaks stay
 * line breaks, list items get a dash and every link keeps its address, since
 * a link you cannot see is a link you cannot follow.
 */
final class HtmlToText
{
    /** Elements that start and end a paragraph of their own. */
    private const BLOCKS = 'p|div|h[1-6]|blockquote|ul|ol|table|tr|section|header|footer|article|center|pre';

    public static function convert(string $html): string
    {
        // Nothing in these is ever read as text.
        $text = (string) preg_replace('#<(head|style|script|title)\b[^>]*>.*?</\1\s*>#is', '', $html);
        $text = (string) preg_replace('#<!--.*?-->#s', '', $text);

        // Whitespace in markup source carries no meaning; the tags say where lines break.
        $text = (string) preg_replace('/\s+/', ' ', $text);

        $text = (string) preg_replace_callback(
            '#<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a\s*>#is',
            static fn (array $m): string => self::link($m[2], $m[3]),
            $text
        );

        $text = (string) preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = (string) preg_replace('#<li\b[^>]*>#i', "\n- ", $text);
        $text = (string) preg_replace('#<hr\b[^>]*>#i', "\n\n----\n\n", $text);
        $text = (string) preg_replace('#</?(?:'.self::BLOCKS.')\b[^>]*>#i', "\n\n", $text);
        $text = (string) preg_replace('#</t[dh]\s*>#i', ' ', $text);

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return self::normalize($text);
    }

    /**
     * Tidy finished plain text: no trailing spaces, at most one blank line in a row.
     */
    public static function normalize(string $text): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        $lines = array_map(static fn (string $line): string => trim((string) preg_replace('/[ \t]+/', ' ', $line)), $lines);

        $text = (string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));

        // "https://x (https://x)" happens when a link's label is its own address.
        $text = (string) preg_replace('/(\S+) \(\1\)/', '$1', $text);

        return trim($text);
    }

    private static function link(string $href, string $label): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $label = trim(html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (str_starts_with(strtolower($href), 'mailto:')) {
            $address = substr($href, 7);

            return $label === '' || str_contains($label, $address) ? ($label ?: $address) : "{$label} ({$address})";
        }

        if ($label === '' || $label === $href) {
            return $href;
        }

        return "{$label} ({$href})";
    }
}
