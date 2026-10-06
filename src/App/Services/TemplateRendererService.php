<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Templates\CatalogTemplateSource;
use App\Mail\Templates\EmailHtmlLinter;
use App\Mail\Templates\EmailTemplateSource;
use App\Mail\Templates\HtmlFragment;
use App\Mail\Templates\HtmlToText;
use App\Mail\Templates\MailTranslator;
use App\Mail\Templates\RenderedEmail;
use App\Mail\Templates\TemplateDataException;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * Builds an email's HTML and plain text from its template.
 *
 * Three layers meet here. The Mailable supplies data: names, links, counts and
 * whatever a person wrote. The binding for that email supplies its wording,
 * one short piece of HTML per placeholder of the chosen template. The template
 * and its blocks supply the design. Each layer can only reach the one below it
 * through {{ placeholders }}, substituted once and never re-read, so a value
 * that happens to contain "{{ x }}" stays literal text.
 *
 * Escaping is decided by the code that made the value, not by the template:
 * plain strings are always escaped, and only an HtmlFragment built in code is
 * inserted as markup. Inside a tag a value is always written as escaped text,
 * and a link or image address must be http(s) or mailto.
 *
 * Strict mode (the default, used for every real send) refuses a template that
 * uses a placeholder nobody fills. Lenient mode (previews) shows the gap
 * instead. When a fallback source is given, a failure in the live templates is
 * reported and the email is rendered from the built-in ones, so a bad edit in
 * the control panel degrades the design of an email but never loses it.
 *
 * Each email is rendered in its recipient's language. The document is marked
 * with that language and its direction, and blocks lay themselves out with
 * {{ start }} and {{ end }} (left and right, swapped for right-to-left
 * languages) rather than fixed sides. Email clients ignore the CSS logical
 * properties that would flip on their own.
 *
 * The built-in wording is English, and the locale files carry its
 * translations. Wording changed in the control panel is taken to be written in
 * the site's default language. A reader in that language (or in English, for
 * built-in wording) gets the wording as it is; anyone else gets the shipped
 * translation of each placeholder, and the wording as it is only where no
 * translation exists, so an edit in one language never reaches readers of
 * another while a translation can.
 *
 * @phpstan-import-type Component from EmailTemplateSource
 * @phpstan-import-type Template from EmailTemplateSource
 * @phpstan-import-type Binding from EmailTemplateSource
 */
final class TemplateRendererService
{
    /** A placeholder: {{ name }}, lowercase snake case. */
    public const PLACEHOLDER = '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/';

    /** Placeholders every template can use without the email providing them. */
    public const GLOBALS = ['app_name', 'app_url', 'year', 'dir', 'start', 'end', 'link_fallback_text', 'open_link_text'];

    /**
     * The globals that only describe layout direction. They never count when
     * deciding whether a block is empty, or every quote box would show in
     * every email just because its border names a side.
     */
    public const DIRECTION = ['dir', 'start', 'end'];

    /**
     * Fixed words a block's design includes, in the reader's language, keyed by
     * global => phrase in the locale files. Like the direction globals they are
     * part of the block rather than its content, so they never keep it showing.
     */
    public const PHRASES = ['link_fallback_text' => 'phrases.link_fallback', 'open_link_text' => 'phrases.open_link'];

    /**
     * @param  EmailTemplateSource  $source  Where templates are read from
     * @param  bool  $strict  Refuse missing placeholders rather than showing them
     * @param  EmailTemplateSource|null  $fallback  Rendered from when $source fails
     * @param  (Closure(string, Throwable): void)|null  $onFallback  Told what failed and why, e.g. to alert admins
     */
    public function __construct(
        private EmailTemplateSource $source,
        private bool $strict = true,
        private ?EmailTemplateSource $fallback = null,
        private ?Closure $onFallback = null,
    ) {}

    public function source(): EmailTemplateSource
    {
        return $this->source;
    }

    /**
     * Render one email from the template its binding points at.
     *
     * @param  class-string  $mailable  The Mailable being rendered
     * @param  array<string, mixed>  $data  Values the Mailable provides, keyed by placeholder name
     * @param  string  $subject  The subject the Mailable set, used unless the binding overrides it
     * @param  string|null  $locale  The recipient's language; null is the site default
     *
     * @throws TemplateDataException When the email cannot be rendered and there is no fallback
     */
    public function renderEmail(string $mailable, array $data, string $subject = '', ?string $locale = null): RenderedEmail
    {
        return $this->guarded(
            TemplateDataException::shortName($mailable),
            fn (EmailTemplateSource $source): RenderedEmail => $this->composeEmail($source, $mailable, $data, $subject, self::locale($locale))
        );
    }

    /**
     * Render a single block, for content a Mailable repeats or assembles itself
     * (one row per blog in a digest).
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $locale  The recipient's language; null is the site default
     */
    public function renderComponent(string $slug, array $data, ?string $locale = null): HtmlFragment
    {
        return $this->guarded(
            "the '{$slug}' block",
            function (EmailTemplateSource $source) use ($slug, $data, $locale): HtmlFragment {
                $component = $source->component($slug)
                    ?? throw new TemplateDataException("The block '{$slug}' does not exist.");

                return $this->renderBlock($component, $this->values($data, self::locale($locale)), "The '{$slug}' block") ?? HtmlFragment::empty();
            }
        );
    }

    /**
     * A block on its own, filled with its sample values.
     *
     * @param  Component  $component
     */
    public function previewComponent(array $component): RenderedEmail
    {
        $renderer = $this->lenient();
        $values = $renderer->values($component['preview_data']);
        $block = $renderer->renderBlock($component, $values, "The '{$component['slug']}' block");

        return $renderer->finish([$component['slug'] => $component['css']], $block === null ? [] : [$block], $component['label']);
    }

    /**
     * A whole template, filled with the sample values of its blocks.
     *
     * @param  Template  $template
     */
    public function previewTemplate(array $template): RenderedEmail
    {
        $renderer = $this->lenient();
        $components = $renderer->layoutComponents($this->source, $template);

        $samples = [];
        foreach ($components as $component) {
            $samples += $component['preview_data'];
        }

        return $renderer->assemble($components, $renderer->values($samples), $template['label']);
    }

    /**
     * Every placeholder a template's blocks use, in the order they appear.
     *
     * @param  Template  $template
     * @return list<string>
     */
    public function placeholdersForTemplate(array $template): array
    {
        $names = [];

        foreach ($template['layout'] as $slug) {
            $component = $this->source->component($slug);

            if ($component !== null) {
                $names = array_merge($names, self::placeholdersOfComponent($component));
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  Component  $component
     * @return list<string>
     */
    public static function placeholdersOfComponent(array $component): array
    {
        return array_values(array_unique(array_merge(
            self::placeholdersIn($component['html']),
            self::placeholdersIn((string) $component['text'])
        )));
    }

    /**
     * @return list<string>
     */
    public static function placeholdersIn(string $template): array
    {
        preg_match_all(self::PLACEHOLDER, $template, $m);

        return array_values(array_unique($m[1]));
    }

    // ------------------------------------------------------------------
    // Composition
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    private function composeEmail(EmailTemplateSource $source, string $mailable, array $data, string $subject, string $locale): RenderedEmail
    {
        $binding = self::inLanguage($source->binding($mailable) ?? throw TemplateDataException::noBinding($mailable), $mailable, $locale);
        $who = TemplateDataException::shortName($mailable);

        $template = $source->template($binding['template'])
            ?? throw TemplateDataException::missingTemplate($binding['template'], $mailable);

        $components = $this->layoutComponents($source, $template);
        $values = $this->values($data + ['subject' => $subject], $locale);

        $needed = [];
        foreach ($components as $component) {
            $needed = array_merge($needed, self::placeholdersOfComponent($component));
        }

        // Wording is only rendered for placeholders the template still uses, so
        // switching templates does not trip over wording left from the old one.
        $mapped = [];
        foreach ($binding['mapping'] as $name => $wording) {
            if (in_array($name, $needed, true)) {
                $mapped[$name] = $this->fragment($wording, $values, null, "The wording for {{ {$name} }} in {$who}");
            }
        }

        if ($binding['subject'] !== null && trim($binding['subject']) !== '') {
            $subject = $this->plainText($binding['subject'], $values, "The subject line for {$who}");
        }

        return $this->assemble($components, $mapped + $values, $subject, " (for {$who})", $locale);
    }

    /**
     * The binding with its wording in the reader's language (see the class
     * comment). The subject override is dropped for other languages, so the
     * code's own subject, translated, is used instead.
     *
     * @param  Binding  $binding
     * @return Binding
     */
    private static function inLanguage(array $binding, string $mailable, string $locale): array
    {
        $authoredIn = $binding['source'] === 'built-in' ? 'en' : LocaleRegistry::instance()->default();

        if ($locale === $authoredIn) {
            return $binding;
        }

        // An edited binding read in English falls back to the shipped English first.
        $shipped = $locale === 'en' ? ((new CatalogTemplateSource())->binding($mailable)['mapping'] ?? []) : MailTranslator::wording($locale, $mailable);

        return ['mapping' => $shipped + $binding['mapping'], 'subject' => null] + $binding;
    }

    /**
     * @param  list<Component>  $components
     * @param  array<string, HtmlFragment>  $values
     */
    private function assemble(array $components, array $values, string $subject, string $context = '', ?string $locale = null): RenderedEmail
    {
        $blocks = [];
        $css = [];

        foreach ($components as $component) {
            $block = $this->renderBlock($component, $values, "The '{$component['slug']}' block".$context);

            if ($block !== null) {
                $blocks[] = $block;
                $css[$component['slug']] = $component['css'];
            }
        }

        return $this->finish($css, $blocks, $subject, $locale);
    }

    /**
     * @param  array<string, string>  $css  Per block, deduplicated by slug
     * @param  list<HtmlFragment>  $blocks
     */
    private function finish(array $css, array $blocks, string $subject, ?string $locale = null): RenderedEmail
    {
        $body = implode("\n", array_map(static fn (HtmlFragment $b): string => $b->html, $blocks));
        $text = implode("\n\n", array_map(static fn (HtmlFragment $b): string => $b->text, $blocks));
        $css = implode("\n", array_filter(array_map('trim', $css)));

        return new RenderedEmail($subject, $this->shell($body, $subject, $css, self::locale($locale)), HtmlToText::normalize($text));
    }

    /**
     * The document every email sits in. Kept to what every client needs, so
     * all of the look lives in blocks the control panel can edit.
     *
     * The direction goes on the content wrapper as well as <html>, because
     * webmail clients such as Gmail drop the <html> element's attributes.
     */
    private function shell(string $body, string $subject, string $css, string $locale): string
    {
        $title = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $style = $css === '' ? '' : "<style>\n{$css}\n</style>\n";
        ['dir' => $dir, 'start' => $start] = self::direction($locale);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$locale}" dir="{$dir}">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$title}</title>
        {$style}</head>
        <body style="margin:0;padding:0;background-color:#ffffff;">
        <div dir="{$dir}" style="max-width:600px;margin:0 auto;padding:20px;font-family:Arial,sans-serif;line-height:1.6;color:#333333;text-align:{$start};">
        {$body}
        </div>
        </body>
        </html>
        HTML;
    }

    /**
     * A supported language code, or the site default. Only codes from the
     * registry reach the markup, so the lang attribute needs no escaping.
     */
    private static function locale(?string $locale): string
    {
        $registry = LocaleRegistry::instance();

        return $registry->normalize($locale) ?? $registry->default();
    }

    /**
     * @return array{dir: string, start: string, end: string}
     */
    private static function direction(string $locale): array
    {
        return in_array($locale, LocaleRegistry::RTL, true)
            ? ['dir' => 'rtl', 'start' => 'right', 'end' => 'left']
            : ['dir' => 'ltr', 'start' => 'left', 'end' => 'right'];
    }

    /**
     * @param  Template  $template
     * @return list<Component>
     */
    private function layoutComponents(EmailTemplateSource $source, array $template): array
    {
        $components = [];

        foreach ($template['layout'] as $slug) {
            $component = $source->component($slug);

            if ($component === null) {
                if ($this->strict) {
                    throw TemplateDataException::missingComponent($slug, $template['slug']);
                }

                $component = [
                    'slug' => $slug, 'label' => $slug, 'category' => 'content', 'description' => '', 'css' => '',
                    'html' => '<p style="color:#b91c1c;">Missing block: '.htmlspecialchars($slug).'</p>',
                    'text' => null, 'preview_data' => [], 'source' => 'custom',
                ];
            }

            $components[] = $component;
        }

        return $components;
    }

    /**
     * One block, or null when every placeholder in it came out empty.
     *
     * The empty rule is what lets one template serve many emails: an email
     * with no quote simply maps {{ quote }} to nothing and the quote box is
     * left out. A block with no placeholders at all (a divider) always shows,
     * and so does one whose only placeholders are direction or fixed phrases.
     *
     * @param  Component  $component
     * @param  array<string, HtmlFragment>  $values
     */
    private function renderBlock(array $component, array $values, string $where): ?HtmlFragment
    {
        $names = array_diff(self::placeholdersIn($component['html']), self::DIRECTION, array_keys(self::PHRASES));

        if ($names !== []) {
            $allEmpty = true;

            foreach ($names as $name) {
                // A missing value is not empty: rendering goes ahead so it is reported.
                if (!isset($values[$name]) || !$values[$name]->isEmpty()) {
                    $allEmpty = false;
                    break;
                }
            }

            if ($allEmpty) {
                return null;
            }
        }

        return $this->fragment($component['html'], $values, $component['text'], $where);
    }

    // ------------------------------------------------------------------
    // Substitution
    // ------------------------------------------------------------------

    /**
     * Fill a piece of template markup, giving both its HTML and its plain text.
     *
     * @param  array<string, HtmlFragment>  $values
     * @param  string|null  $textTemplate  Custom plain text; null works it out from the HTML, '' leaves it out
     */
    private function fragment(string $template, array $values, ?string $textTemplate, string $where): HtmlFragment
    {
        $html = (string) preg_replace_callback(
            self::PLACEHOLDER,
            function (array $m) use ($template, $values, $where): string {
                [$name, $offset] = [$m[1][0], $m[0][1]];
                $inTag = EmailHtmlLinter::insideTag($template, $offset);

                if (!isset($values[$name])) {
                    return $this->missing($name, $where, $inTag ? 'attribute' : 'html');
                }

                if (!$inTag) {
                    return $values[$name]->html;
                }

                // Inside a tag a value is only ever text. Markup there could close
                // the attribute, and a link must not become javascript:.
                $text = $values[$name]->text;

                if (self::isUrlAttribute($template, $offset)) {
                    $text = self::safeUrl($text);
                }

                return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            },
            $template,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        );

        if ($textTemplate === '') {
            return HtmlFragment::trusted($html, '');
        }

        if ($textTemplate !== null) {
            $text = $this->plainText($textTemplate, $values, $where, false);

            return HtmlFragment::trusted($html, $text);
        }

        // Swap each placeholder for a token, read the markup as text, then put
        // the values' own plain text back. That keeps a value's line breaks and
        // links, which reading the finished HTML would lose.
        $nonce = bin2hex(random_bytes(4));
        $names = [];
        $tokenized = (string) preg_replace_callback(
            self::PLACEHOLDER,
            static function (array $m) use (&$names, $nonce): string {
                $names[] = $m[1];

                return 'zq'.$nonce.'x'.(count($names) - 1).'zq';
            },
            $template
        );

        $text = (string) preg_replace_callback(
            '/zq'.$nonce.'x(\d+)zq/i',
            fn (array $m): string => isset($values[$names[(int) $m[1]]])
                ? $values[$names[(int) $m[1]]]->text
                : $this->missing($names[(int) $m[1]], $where, 'text'),
            HtmlToText::convert($tokenized)
        );

        return HtmlFragment::trusted($html, HtmlToText::normalize($text));
    }

    /**
     * Fill a plain-text template (a subject line, a block's custom text).
     *
     * @param  array<string, HtmlFragment>  $values
     */
    private function plainText(string $template, array $values, string $where, bool $singleLine = true): string
    {
        $text = (string) preg_replace_callback(
            self::PLACEHOLDER,
            fn (array $m): string => isset($values[$m[1]]) ? $values[$m[1]]->text : $this->missing($m[1], $where, 'text'),
            $template
        );

        return $singleLine ? trim((string) preg_replace('/\s+/', ' ', $text)) : HtmlToText::normalize($text);
    }

    /**
     * What to put where a placeholder has no value: refuse when strict, show the gap when previewing.
     */
    private function missing(string $name, string $where, string $context): string
    {
        if ($this->strict) {
            throw TemplateDataException::missingPlaceholder($name, $where);
        }

        $marker = '{{ '.$name.' }}';

        return match ($context) {
            'html' => '<span style="background:#FEF3C7;color:#92400E;padding:0 2px;">'.htmlspecialchars($marker).'</span>',
            'attribute' => htmlspecialchars($marker),
            default => $marker,
        };
    }

    /**
     * Whether a placeholder at $offset starts the value of a link or image address.
     */
    private static function isUrlAttribute(string $template, int $offset): bool
    {
        return (bool) preg_match('/\b(?:href|src|background|action)\s*=\s*["\']?\s*$/i', substr($template, 0, $offset));
    }

    private static function safeUrl(string $url): string
    {
        $url = trim($url);

        return preg_match('#^(?:https?://|mailto:)#i', $url) ? $url : '#';
    }

    // ------------------------------------------------------------------
    // Values
    // ------------------------------------------------------------------

    /**
     * Normalize what a Mailable passed, with the global placeholders underneath.
     *
     * @param  array<array-key, mixed>  $data  Keys are checked here, since a list slips past the type hint
     * @param  string|null  $locale  Sets the direction globals; null is the site default
     * @return array<string, HtmlFragment>
     */
    private function values(array $data, ?string $locale = null): array
    {
        $values = $this->globals(self::locale($locale));

        foreach ($data as $name => $value) {
            if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException("Template data key '{$name}' must be lowercase snake case.");
            }

            $values[$name] = match (true) {
                $value instanceof HtmlFragment => $value,
                $value === null => HtmlFragment::empty(),
                is_bool($value) => HtmlFragment::fromText($value ? 'yes' : ''),
                is_scalar($value), $value instanceof Stringable => HtmlFragment::fromText((string) $value),
                default => throw new InvalidArgumentException("Template data '{$name}' must be text, a number or an HtmlFragment."),
            };
        }

        return $values;
    }

    /**
     * @return array<string, HtmlFragment>
     */
    private function globals(string $locale): array
    {
        return [
            'app_name' => HtmlFragment::fromText((string) env('APP_NAME', 'Lexicon')),
            'app_url' => HtmlFragment::fromText(rtrim((string) env('APP_URL', 'http://localhost'), '/')),
            'year' => HtmlFragment::fromText(date('Y')),
        ] + array_map(HtmlFragment::fromText(...), self::direction($locale))
          + array_map(static fn (string $key): HtmlFragment => HtmlFragment::fromText(MailTranslator::text($locale, $key)), self::PHRASES);
    }

    // ------------------------------------------------------------------
    // Failure handling
    // ------------------------------------------------------------------

    /**
     * Run a render against the live source, and against the fallback if that fails.
     *
     * Only runtime failures fall back: a template that does not fit its data,
     * or a database that cannot be read. A mistake in the calling code (bad
     * data types) is a bug and is left to surface.
     *
     * @template T
     *
     * @param  Closure(EmailTemplateSource): T  $render
     * @return T
     */
    private function guarded(string $what, Closure $render): mixed
    {
        try {
            return $render($this->source);
        } catch (RuntimeException $e) {
            if ($this->fallback === null) {
                throw $e;
            }

            error_log("Email template for {$what} failed, sending the built-in design instead: ".$e->getMessage());

            if ($this->onFallback !== null) {
                try {
                    ($this->onFallback)($what, $e);
                } catch (Throwable $notifyFailure) {
                    error_log('Could not report the email template failure: '.$notifyFailure->getMessage());
                }
            }

            return $render($this->fallback);
        }
    }

    private function lenient(): self
    {
        return $this->strict ? new self($this->source, false) : $this;
    }
}
