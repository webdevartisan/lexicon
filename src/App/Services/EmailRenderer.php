<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Templates\EmailHtmlLinter;
use App\Mail\Templates\EmailSource;
use App\Mail\Templates\HtmlFragment;
use App\Mail\Templates\HtmlToText;
use App\Mail\Templates\RenderedEmail;
use App\Mail\Templates\TemplateDataException;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * Puts an email's words into its layout.
 *
 * There is no logic in a template, only {{ placeholders }}. Each is replaced
 * by a value the email's code provides, by a setting of the layout, or by one
 * of the values every email has (see GLOBALS). Text is always escaped; only
 * an HtmlFragment, which code builds, goes in as markup. Inside a tag a value
 * is always escaped text, and a link can never become javascript:.
 *
 * The body, footer note and subject are filled first, then the layout, which
 * also has {{ content }}, {{ footer_note }}, {{ preheader }} and {{ subject }}.
 * The plain-text part is read from the finished HTML.
 *
 * An email is written in one language: the recipient's if the email has words
 * in it, else the site's default language, else English, which always ships.
 *
 * @phpstan-import-type Layout from EmailSource
 * @phpstan-import-type Content from EmailSource
 */
class EmailRenderer
{
    /** {{ name }}, with or without spaces inside the braces. */
    public const PLACEHOLDER = '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/';

    /** Values every email and layout can use. */
    public const GLOBALS = ['app_name', 'app_url', 'year', 'lang', 'dir', 'start', 'end', 'preferences_url'];

    /** Values that come from the layout's settings. */
    public const LAYOUT_SETTINGS = ['primary_color', 'background_color', 'support_email', 'company_address'];

    /** Values only a layout can use: the email's own parts. */
    public const LAYOUT_ONLY = ['content', 'footer_note', 'preheader', 'subject'];

    /** The language every shipped email is written in. */
    public const BASE_LOCALE = 'en';

    /**
     * @param  bool  $strict  Refuse a placeholder nothing fills; false shows it in place, for previews
     * @param  EmailSource|null  $fallback  Used when $source cannot render an email (normally the shipped files)
     * @param  (Closure(string, Throwable): void)|null  $onFallback  Told which email fell back and why
     */
    public function __construct(
        private EmailSource $source,
        private bool $strict = true,
        private ?EmailSource $fallback = null,
        private ?Closure $onFallback = null,
    ) {}

    public function source(): EmailSource
    {
        return $this->source;
    }

    /**
     * The language an email will be written in for someone who reads $wanted.
     *
     * @param  class-string|string  $mailable
     */
    public function localeFor(string $mailable, string $wanted): string
    {
        $available = $this->source->locales($mailable);

        foreach ([$wanted, LocaleRegistry::instance()->default(), self::BASE_LOCALE] as $locale) {
            if (in_array($locale, $available, true)) {
                return $locale;
            }
        }

        return self::BASE_LOCALE;
    }

    /**
     * Render one email in one language.
     *
     * @param  class-string|string  $mailable
     * @param  array<string, mixed>  $data  Placeholder => value
     */
    public function renderEmail(string $mailable, array $data, string $locale): RenderedEmail
    {
        return $this->guarded(
            $mailable,
            fn (EmailSource $source, string $in): RenderedEmail => $this->compose($source, $mailable, $data, $in),
            $locale
        );
    }

    /**
     * The section an email repeats, once per row, e.g. one per blog in the digest.
     *
     * @param  class-string|string  $mailable
     * @param  list<array<string, mixed>>  $rows  Placeholder => value, per repetition
     */
    public function renderRepeat(string $mailable, array $rows, string $locale): HtmlFragment
    {
        return $this->guarded($mailable, function (EmailSource $source, string $in) use ($mailable, $rows): HtmlFragment {
            $content = self::contentOf($source, $mailable, $in);
            $repeat = $content['repeat'] ?? throw new TemplateDataException(TemplateDataException::shortName($mailable).' has no repeated section.');
            $layout = $this->layoutOf($source, $mailable);
            $where = TemplateDataException::shortName($mailable)." repeated section ({$in})";

            return HtmlFragment::join(array_map(
                fn (array $row): HtmlFragment => HtmlFragment::trusted($this->fill($repeat, $this->values($row, $in, $layout), $where)),
                $rows
            ));
        }, $locale);
    }

    /**
     * Every placeholder name in a piece of markup, in order, once each.
     *
     * @return list<string>
     */
    public static function placeholdersIn(string $template): array
    {
        preg_match_all(self::PLACEHOLDER, $template, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function compose(EmailSource $source, string $mailable, array $data, string $locale): RenderedEmail
    {
        $content = self::contentOf($source, $mailable, $locale);
        $layout = $this->layoutOf($source, $mailable);
        $name = TemplateDataException::shortName($mailable);
        $values = $this->values($data, $locale, $layout);

        $subject = $this->fillText($content['subject'], $values, "{$name} subject ({$locale})");
        $preheader = $this->fillText($content['preheader'], $values, "{$name} preheader ({$locale})");
        $body = $this->fill($content['body'], $values, "{$name} body ({$locale})");
        $footer = $this->fill($content['footer_note'], $values, "{$name} footer note ({$locale})");

        $parts = $values + [
            'content' => HtmlFragment::trusted($body, ''),
            'footer_note' => HtmlFragment::trusted($footer, ''),
            'subject' => HtmlFragment::fromText($subject),
        ];
        $where = "the layout '{$layout['slug']}'";

        $html = $this->fill($layout['html'], $parts + ['preheader' => HtmlFragment::fromText($preheader)], $where);

        // The preheader is hidden in the HTML part; in plain text it would be a stray first line.
        $text = HtmlToText::convert($this->fill($layout['html'], $parts + ['preheader' => HtmlFragment::empty()], $where));

        return new RenderedEmail($subject, $html, $text);
    }

    /**
     * @return Content
     */
    private static function contentOf(EmailSource $source, string $mailable, string $locale): array
    {
        return $source->content($mailable, $locale)
            ?? throw new TemplateDataException(TemplateDataException::shortName($mailable)." has no words in '{$locale}'.");
    }

    /**
     * @return Layout
     */
    private function layoutOf(EmailSource $source, string $mailable): array
    {
        $slug = $source->layoutFor($mailable);

        return $source->layout($slug)
            ?? throw new TemplateDataException(TemplateDataException::shortName($mailable)." uses the layout '{$slug}', which does not exist.");
    }

    /**
     * Fill markup: values are markup outside tags, escaped text inside them.
     *
     * @param  array<string, HtmlFragment>  $values
     */
    private function fill(string $template, array $values, string $where): string
    {
        return (string) preg_replace_callback(
            self::PLACEHOLDER,
            function (array $m) use ($template, $values, $where): string {
                [$name, $offset] = [$m[1][0], $m[0][1]];
                $inTag = EmailHtmlLinter::insideTag($template, $offset);

                if (!isset($values[$name])) {
                    return $this->missing($name, $where, $inTag);
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
    }

    /**
     * Fill a one-line text such as the subject.
     *
     * @param  array<string, HtmlFragment>  $values
     */
    private function fillText(string $template, array $values, string $where): string
    {
        $text = (string) preg_replace_callback(
            self::PLACEHOLDER,
            fn (array $m): string => isset($values[$m[1]]) ? $values[$m[1]]->text : $this->missing($m[1], $where, false, true),
            $template
        );

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * What to put where a placeholder has no value: refuse when strict, show the gap when previewing.
     */
    private function missing(string $name, string $where, bool $inTag, bool $asText = false): string
    {
        if ($this->strict) {
            throw TemplateDataException::missingPlaceholder($name, $where);
        }

        $marker = '{{ '.$name.' }}';

        if ($asText) {
            return $marker;
        }

        return $inTag
            ? htmlspecialchars($marker)
            : '<span style="background:#FEF3C7;color:#92400E;padding:0 2px;">'.htmlspecialchars($marker).'</span>';
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

    /**
     * What an email's placeholders can be filled with: the globals, the
     * layout's settings, then the email's own data, which wins on a clash.
     *
     * @param  array<array-key, mixed>  $data  Keys are checked here, since a list slips past the type hint
     * @param  Layout  $layout
     * @return array<string, HtmlFragment>
     */
    private function values(array $data, string $locale, array $layout): array
    {
        $values = array_map(HtmlFragment::fromText(...), self::globals($locale) + [
            'primary_color' => $layout['primary_color'],
            'background_color' => $layout['background_color'],
            'support_email' => $layout['support_email'] !== '' ? $layout['support_email'] : (string) env('MAIL_FROM_ADDRESS', ''),
            'company_address' => $layout['company_address'],
        ]);

        foreach ($data as $name => $value) {
            if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException("Email data key '{$name}' must be lowercase snake case.");
            }

            $values[$name] = match (true) {
                $value instanceof HtmlFragment => $value,
                $value === null => HtmlFragment::empty(),
                is_scalar($value), $value instanceof Stringable => HtmlFragment::fromText((string) $value),
                default => throw new InvalidArgumentException("Email data '{$name}' must be text, a number or an HtmlFragment."),
            };
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    public static function globals(string $locale): array
    {
        $appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
        $rtl = LocaleRegistry::instance()->isRtl($locale);

        return [
            'app_name' => (string) env('APP_NAME', 'Lexicon'),
            'app_url' => $appUrl,
            'year' => date('Y'),
            // Only codes the registry knows reach here, so lang needs no further checks.
            'lang' => $locale,
            'dir' => $rtl ? 'rtl' : 'ltr',
            'start' => $rtl ? 'right' : 'left',
            'end' => $rtl ? 'left' : 'right',
            'preferences_url' => $appUrl.'/account/notifications',
        ];
    }

    /**
     * Render from the live source, and from the fallback if that fails.
     *
     * Only runtime failures fall back: words that do not fit the email's data,
     * a missing layout, or tables that cannot be read. A mistake in the
     * calling code (bad data types) is a bug and is left to surface. The
     * fallback is in English, the one language every email ships in.
     *
     * @template T
     *
     * @param  Closure(EmailSource, string): T  $render
     * @return T
     */
    private function guarded(string $mailable, Closure $render, string $locale): mixed
    {
        try {
            return $render($this->source, $locale);
        } catch (RuntimeException $e) {
            if ($this->fallback === null) {
                throw $e;
            }

            $what = TemplateDataException::shortName($mailable);
            error_log("Email {$what} could not be built as saved, sending the shipped version instead: ".$e->getMessage());

            if ($this->onFallback !== null) {
                try {
                    ($this->onFallback)($what, $e);
                } catch (Throwable $notifyFailure) {
                    error_log('Could not report the email failure: '.$notifyFailure->getMessage());
                }
            }

            return $render($this->fallback, self::BASE_LOCALE);
        }
    }
}
