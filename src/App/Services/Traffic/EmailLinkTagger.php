<?php

declare(strict_types=1);

namespace App\Services\Traffic;

/**
 * Adds campaign tags to links in an email that point at a page the Traffic
 * pages count, so readers arriving from mail show up as email rather than as
 * direct visits: mail apps strip the referrer.
 *
 * Only public reading pages are tagged. Links with tokens (confirmations,
 * unsubscribes, password resets) lead elsewhere and are left exactly as they are.
 */
final class EmailLinkTagger
{
    /** Paths whose views are counted, with or without a locale prefix. */
    private const COUNTED = '#^(/[a-z]{2})?(/blog/|/discover|/getting-started|/about|/?$)#';

    public function __construct(private string $appUrl) {}

    /**
     * @param  string  $campaign  What the email is, e.g. new-post
     * @return array{0: string, 1: ?string} The HTML and text bodies
     */
    public function tag(string $html, ?string $text, string $campaign): array
    {
        $host = strtolower((string) parse_url($this->appUrl, PHP_URL_HOST));

        if ($host === '') {
            return [$html, $text];
        }

        $html = (string) preg_replace_callback(
            '/href="([^"]+)"/i',
            fn (array $m): string => 'href="'.htmlspecialchars(
                $this->tagUrl(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), $host, $campaign),
                ENT_QUOTES
            ).'"',
            $html
        );

        if ($text !== null) {
            $text = (string) preg_replace_callback(
                // A full stop or comma after an address ends the sentence, not the address.
                '#https?://[^\s<>"\')]+(?<![.,;:!?])#',
                fn (array $m): string => $this->tagUrl($m[0], $host, $campaign),
                $text
            );
        }

        return [$html, $text];
    }

    /**
     * The campaign name for a Mailable class: NewPostMail becomes new-post.
     */
    public static function campaignFor(string $mailableClass): string
    {
        $name = preg_replace('/Mail$/', '', substr((string) strrchr('\\'.$mailableClass, '\\'), 1)) ?? '';

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }

    private function tagUrl(string $url, string $host, string $campaign): string
    {
        $parts = parse_url($url);

        if ($parts === false || strtolower($parts['host'] ?? '') !== $host || !preg_match(self::COUNTED, $parts['path'] ?? '/')) {
            return $url;
        }

        parse_str($parts['query'] ?? '', $query);

        // A link someone tagged on purpose keeps its own tags.
        if (isset($query['utm_source']) || isset($query['utm_medium']) || isset($query['utm_campaign'])) {
            return $url;
        }

        $query += ['utm_source' => 'lexicon', 'utm_medium' => 'email', 'utm_campaign' => $campaign];

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '/').'?'.http_build_query($query)
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
