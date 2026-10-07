<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * The emails and layouts that ship with the site, read from files:
 *
 * - resources/mail/layouts/{slug}.html: a whole HTML document with fields at
 *   the top for its name, colours, support email and company address
 * - resources/mail/emails/{Mailable}.html: the English version of one email,
 *   with fields at the top for its subject, preheader, footer note and layout
 * - resources/mail/emails/{Mailable}.repeat.html: the section an email repeats,
 *   for the few that repeat one
 *
 * Only English ships. Every other language is written in the control panel.
 * These files are what "Reset to default" goes back to, and what an email is
 * sent with when a saved version cannot be used.
 *
 * Files are read once per process: a fan-out builds thousands of emails in a row.
 *
 * @phpstan-import-type Layout from EmailSource
 * @phpstan-import-type Content from EmailSource
 */
class ShippedEmailSource implements EmailSource
{
    public const LOCALE = 'en';

    /** @var array<string, array{layouts: array<string, Layout>, emails: array<string, array{content: Content, layout: string}>}> Directory => what was read */
    private static array $read = [];

    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? dirname(__DIR__, 4).'/resources/mail', '/');
    }

    public function layout(string $slug): ?array
    {
        return $this->load()['layouts'][$slug] ?? null;
    }

    public function layouts(): array
    {
        return $this->load()['layouts'];
    }

    public function layoutFor(string $mailable): string
    {
        return $this->load()['emails'][TemplateDataException::shortName($mailable)]['layout'] ?? self::DEFAULT_LAYOUT;
    }

    public function content(string $mailable, string $locale): ?array
    {
        return $locale === self::LOCALE ? ($this->load()['emails'][TemplateDataException::shortName($mailable)]['content'] ?? null) : null;
    }

    public function locales(string $mailable): array
    {
        return isset($this->load()['emails'][TemplateDataException::shortName($mailable)]) ? [self::LOCALE] : [];
    }

    /**
     * The names of the emails that have a shipped file.
     *
     * @return list<string> Short class names
     */
    public function emailNames(): array
    {
        return array_keys($this->load()['emails']);
    }

    /**
     * Forget what was read, for tests that write files.
     */
    public static function flush(): void
    {
        self::$read = [];
    }

    /**
     * @return array{layouts: array<string, Layout>, emails: array<string, array{content: Content, layout: string}>}
     */
    private function load(): array
    {
        if (isset(self::$read[$this->directory])) {
            return self::$read[$this->directory];
        }

        $layouts = [];

        foreach (glob($this->directory.'/layouts/*.html') ?: [] as $file) {
            $slug = basename($file, '.html');
            [$fields, $html] = FrontMatter::parse((string) file_get_contents($file));

            $layouts[$slug] = [
                'slug' => $slug,
                'name' => $fields['name'] ?? ucfirst($slug),
                'html' => $html,
                'primary_color' => $fields['primary_color'] ?? '#4F46E5',
                'background_color' => $fields['background_color'] ?? '#F3F4F6',
                'support_email' => $fields['support_email'] ?? '',
                'company_address' => $fields['company_address'] ?? '',
                'source' => 'built-in',
            ];
        }

        $emails = [];

        foreach (glob($this->directory.'/emails/*.html') ?: [] as $file) {
            $name = basename($file, '.html');

            if (str_contains($name, '.')) {
                continue;
            }

            [$fields, $body] = FrontMatter::parse((string) file_get_contents($file));
            $repeatFile = $this->directory.'/emails/'.$name.'.repeat.html';

            $emails[$name] = [
                'content' => [
                    'subject' => $fields['subject'] ?? '',
                    'preheader' => $fields['preheader'] ?? '',
                    'body' => $body,
                    'footer_note' => $fields['footer_note'] ?? '',
                    'repeat' => is_file($repeatFile) ? (string) file_get_contents($repeatFile) : null,
                    'source' => 'built-in',
                    'updated_at' => null,
                ],
                'layout' => ($fields['layout'] ?? '') !== '' ? $fields['layout'] : self::DEFAULT_LAYOUT,
            ];
        }

        ksort($layouts);
        ksort($emails);

        return self::$read[$this->directory] = ['layouts' => $layouts, 'emails' => $emails];
    }
}
