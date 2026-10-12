<?php

declare(strict_types=1);

namespace App\Services\Archive;

use App\Services\MediaService;

/**
 * Finds and rewrites the media references inside a post, for export and import alike.
 *
 * The editor stores the same uploaded file in several spellings: TinyMCE writes
 * paths relative to the editor page (../../../uploads/...), the library writes
 * /uploads/..., and pasted links can carry the site address or a locale prefix.
 * Everything here reduces those to one canonical /uploads/... form first.
 */
final class ArchiveMediaUrls
{
    private const ATTRIBUTE_PATTERN = '/(\s)(src|href|poster|srcset)(\s*=\s*)(?:"([^"]*)"|\'([^\']*)\')/i';

    private string $siteUrl;

    public function __construct(?string $siteUrl = null)
    {
        $this->siteUrl = rtrim($siteUrl ?? (string) base_url(), '/');
    }

    /**
     * The canonical /uploads/... form of a reference, or null when it is not a local upload.
     */
    public function canonical(string $value): ?string
    {
        $value = trim($value);

        if ($this->siteUrl !== '' && str_starts_with($value, $this->siteUrl.'/')) {
            $value = substr($value, strlen($this->siteUrl));
        }

        $value = (string) preg_replace('#^(?:\.\./)+(?=uploads/)#', '/', $value);
        $value = (string) preg_replace('#^/[a-z]{2}(?:-[A-Za-z]{2})?(?=/uploads/)#', '', $value);
        $value = strtok($value, '?#') ?: '';

        return MediaService::isLocalUploadUrl($value) ? $value : null;
    }

    /**
     * The blog a canonical upload URL is filed under, read from its folder.
     */
    public function blogIdOf(string $canonicalUrl): ?int
    {
        return preg_match('#^/uploads/users/\d+/blogs/(\d+)/#', $canonicalUrl, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Whether a value looks like an absolute web address outside this site.
     */
    public function isExternal(string $value): bool
    {
        return preg_match('#^https?://#i', trim($value)) === 1 && $this->canonical($value) === null;
    }

    /**
     * Every value in a loading or linking attribute of the HTML, srcset entries split out.
     *
     * @return string[]
     */
    public function references(string $html): array
    {
        $found = [];
        $this->rewrite($html, static function (string $value) use (&$found): ?string {
            $found[] = $value;

            return null;
        });

        return $found;
    }

    /**
     * Replace attribute values through $map, which returns the new value or null to keep it.
     *
     * @param  callable(string): ?string  $map
     */
    public function rewrite(string $html, callable $map): string
    {
        return (string) preg_replace_callback(self::ATTRIBUTE_PATTERN, function (array $m) use ($map): string {
            $quote = isset($m[5]) ? "'" : '"';
            $value = isset($m[5]) ? $m[5] : $m[4];

            $new = strtolower($m[2]) === 'srcset'
                ? $this->rewriteSrcset($value, $map)
                : ($map($value) ?? $value);

            return $m[1].$m[2].$m[3].$quote.$new.$quote;
        }, $html);
    }

    /**
     * Drop every img or source element that loads one of $values, and every link to one.
     *
     * Used for files that cannot be brought in, so nothing is left pointing at a
     * file that does not exist.
     *
     * @param  string[]  $values  Exact attribute values to remove
     * @return array{0: string, 1: int} The HTML and how many references were removed
     */
    public function remove(string $html, array $values): array
    {
        if ($values === []) {
            return [$html, 0];
        }

        $alternatives = implode('|', array_map(static fn (string $v): string => preg_quote($v, '#'), $values));
        $count = 0;

        $html = (string) preg_replace(
            '#<(?:img|source)\b[^>]*?\s(?:src|srcset)\s*=\s*["\'][^"\']*(?:'.$alternatives.')[^"\']*["\'][^>]*>#i',
            '',
            $html,
            -1,
            $removed
        );
        $count += $removed;

        $html = (string) preg_replace('#\shref\s*=\s*["\'](?:'.$alternatives.')["\']#i', '', $html, -1, $removed);

        return [$html, $count + $removed];
    }

    /**
     * @param  callable(string): ?string  $map
     */
    private function rewriteSrcset(string $value, callable $map): string
    {
        $parts = array_map(static function (string $candidate) use ($map): string {
            $candidate = trim($candidate);
            $url = strtok($candidate, " \t") ?: '';
            $descriptor = trim(substr($candidate, strlen($url)));
            $new = $map($url) ?? $url;

            return $descriptor === '' ? $new : $new.' '.$descriptor;
        }, explode(',', $value));

        return implode(', ', $parts);
    }
}
