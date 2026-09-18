<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;

/**
 * Finds images and embeds in post HTML that load from another site.
 *
 * The Content-Security-Policy only lets pages load media from this site, so an
 * outside image or embed shows up broken for every reader. New ones are refused
 * when a post is saved. Ones already stored in that post are left alone, so
 * editing an older post never forces its author to clean it up first.
 */
final class ExternalMediaGuard
{
    /** @var array<string, string[]> Tag => attributes that load a resource */
    private const LOADING_ATTRIBUTES = [
        'img' => ['src', 'srcset'],
        'source' => ['src', 'srcset'],
        'video' => ['src', 'poster'],
        'audio' => ['src'],
        'iframe' => ['src'],
        'embed' => ['src'],
        'object' => ['data'],
        'track' => ['src'],
    ];

    public function __construct(private string $siteUrl = '') {}

    /**
     * Outside URLs present in $html that were not already in $previousHtml.
     *
     * @return string[] Unique offending URLs, in document order
     */
    public function newExternalSources(string $html, string $previousHtml = ''): array
    {
        $existing = array_flip($this->externalSources($previousHtml));

        return array_values(array_filter(
            $this->externalSources($html),
            static fn (string $url): bool => !isset($existing[$url])
        ));
    }

    /**
     * The message shown on the content field when new outside media is found.
     *
     * @param  string[]  $urls
     */
    public function rejectionMessage(array $urls): string
    {
        $hosts = array_unique(array_map(
            static fn (string $url): string => (string) (parse_url($url, PHP_URL_HOST) ?: $url),
            $urls
        ));

        return 'Images and embeds must be uploaded or picked from your media library. '
            .'This post links media from '.implode(', ', $hosts).', which the site\'s security policy blocks for readers.';
    }

    /**
     * @return string[] Unique outside URLs referenced by loading tags
     */
    private function externalSources(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $found = [];

        foreach (self::LOADING_ATTRIBUTES as $tag => $attributes) {
            foreach ($document->getElementsByTagName($tag) as $element) {
                assert($element instanceof DOMElement);
                foreach ($attributes as $attribute) {
                    foreach ($this->urlsIn($attribute, $element->getAttribute($attribute)) as $url) {
                        if (!$this->isFirstParty($url)) {
                            $found[$url] = true;
                        }
                    }
                }
            }
        }

        return array_keys($found);
    }

    /**
     * @return string[]
     */
    private function urlsIn(string $attribute, string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if ($attribute !== 'srcset') {
            return [$value];
        }

        return array_values(array_filter(array_map(
            static fn (string $candidate): string => trim((string) preg_split('/\s+/', trim($candidate))[0]),
            explode(',', $value)
        )));
    }

    private function isFirstParty(string $url): bool
    {
        if (str_starts_with($url, 'data:image/')) {
            return true;
        }

        // Protocol-relative URLs look local but load from whatever host follows.
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && !str_starts_with($url, '//')) {
            return true;
        }

        $site = parse_url($this->siteUrl, PHP_URL_HOST);

        return $site !== null && $site !== false && strcasecmp((string) parse_url($url, PHP_URL_HOST), $site) === 0;
    }
}
