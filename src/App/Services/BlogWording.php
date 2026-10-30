<?php

declare(strict_types=1);

namespace App\Services;

/**
 * A theme's own texts as one blog shows them in one language: the owner's
 * wording where they wrote some, the theme's everywhere else.
 */
final class BlogWording
{
    /**
     * @param  array<string, string>  $own  Translation key => the owner's wording
     * @param  string[]  $editable  Translation keys the active theme lets an owner reword
     */
    public function __construct(private array $own = [], private array $editable = []) {}

    /**
     * Wording for a page that has no blog behind it: the theme's own, nothing editable.
     */
    public static function none(): self
    {
        return new self();
    }

    /**
     * The same wording with nothing offered for editing.
     */
    public function fixed(): self
    {
        return new self($this->own);
    }

    /**
     * The text as HTML, ready for the page.
     *
     * @param  string  $key  Translation key, such as nocturne::home.edition
     * @param  array<string, string|int>  $params  Placeholder => HTML the caller already made safe
     */
    public function html(string $key, array $params = []): string
    {
        $own = $this->own[$key] ?? '';
        if ($own === '') {
            return translate_html($key, $params);
        }

        $pairs = [];
        foreach ($params as $name => $value) {
            $pairs['{'.$name.'}'] = (string) $value;
        }

        return strtr(e($own), $pairs);
    }

    /**
     * Attributes that let an owner reword this text on the page, or '' when the theme keeps it fixed.
     *
     * @param  string  $key  Translation key
     * @param  array<string, string|int>  $params  The same placeholders passed to html()
     */
    public function attrs(string $key, array $params = []): string
    {
        if (!in_array($key, $this->editable, true)) {
            return '';
        }

        $tokens = [];
        foreach ($params as $name => $value) {
            $tokens[$name] = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $attrs = edit_text_attrs($key, translate_html($key, $params))
            .' data-edit-template="'.e(chrome_translate($key)).'"';

        return $tokens === []
            ? $attrs
            : $attrs.' data-edit-tokens="'.e((string) json_encode($tokens, JSON_UNESCAPED_UNICODE)).'"';
    }

    /**
     * Each rewordable text as the owner stored it, '' while it follows the theme.
     *
     * @return array<string, array{value: string, fallback: string, wording: true}>
     */
    public function forEditing(): array
    {
        $fields = [];
        foreach ($this->editable as $key) {
            $fields[$key] = ['value' => $this->own[$key] ?? '', 'fallback' => '', 'wording' => true];
        }

        return $fields;
    }
}
