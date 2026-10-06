<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * The words emails use that are not editable wording: default subjects,
 * phrases code assembles, plurals, numbers and dates, plus the translations of
 * the built-in wording. All of it lives in the "mail" section of
 * locales/{code}.json, beside the rest of the site's strings.
 *
 * English is the fallback for any key a language is missing, so a half
 * translated language still sends complete emails.
 *
 * Plurals use ICU message syntax, because two forms are not enough for every
 * language (Arabic has six):
 *
 *     "{count, plural, one {# comment} other {# comments}}"
 *
 * Anything else is plain text with {name} placeholders, the same as the site's
 * own strings.
 *
 * The parsed section is kept per language for the whole process, since a fan-out
 * to every subscriber builds thousands of emails in a row.
 */
final class MailTranslator
{
    /** @var array<string, array<string, mixed>> Language => its "mail" section */
    private static array $sections = [];

    /**
     * A phrase in a language, e.g. text('el', 'subjects.PostApprovedMail', ['post_title' => $title]).
     *
     * @param  array<string, string|int|float>  $params
     */
    public static function text(string $locale, string $key, array $params = []): string
    {
        $pattern = self::lookup($locale, $key) ?? self::lookup('en', $key) ?? $key;

        if (preg_match('/\{\s*\w+\s*,\s*(?:plural|select|selectordinal)\s*,/', $pattern)) {
            $formatted = \MessageFormatter::formatMessage($locale, $pattern, $params);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return (string) preg_replace_callback(
            '/\{(\w+)\}/',
            static fn (array $m): string => array_key_exists($m[1], $params) ? (string) $params[$m[1]] : $m[0],
            $pattern
        );
    }

    /**
     * Whether a language has its own text for a key, not counting the English fallback.
     */
    public static function has(string $locale, string $key): bool
    {
        return self::lookup($locale, $key) !== null;
    }

    /**
     * The translated built-in wording of one email, placeholder => wording.
     * Empty for English, whose wording is the catalog itself.
     *
     * @param  class-string|string  $mailable
     * @return array<string, string>
     */
    public static function wording(string $locale, string $mailable): array
    {
        $short = TemplateDataException::shortName($mailable);
        $wording = self::section($locale)['wording'][$short] ?? [];

        return is_array($wording) ? array_filter($wording, 'is_string') : [];
    }

    public static function number(string $locale, int|float $value): string
    {
        return (string) (new \NumberFormatter($locale, \NumberFormatter::DECIMAL))->format($value);
    }

    /**
     * A date or time as the language writes it, from an ICU skeleton such as
     * 'MMMd' (day and short month) or 'yMMMdjm' (full date and time). The
     * skeleton says what to show; the language decides the order and words.
     */
    public static function date(string $locale, \DateTimeInterface $date, string $skeleton): string
    {
        $pattern = (new \IntlDatePatternGenerator($locale))->getBestPattern($skeleton);
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $date->getTimezone(), null, (string) $pattern);

        return (string) $formatter->format($date);
    }

    /**
     * Forget what was read, for tests that change the files.
     */
    public static function flush(): void
    {
        self::$sections = [];
    }

    private static function lookup(string $locale, string $key): ?string
    {
        $value = self::section($locale);

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function section(string $locale): array
    {
        if (!isset(self::$sections[$locale])) {
            $path = dirname(__DIR__, 4).'/locales/'.$locale.'.json';
            $all = preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $locale) && is_file($path)
                ? json_decode((string) file_get_contents($path), true)
                : null;

            self::$sections[$locale] = is_array($all) && is_array($all['mail'] ?? null) ? $all['mail'] : [];
        }

        return self::$sections[$locale];
    }
}
