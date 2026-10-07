<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * Checks markup an admin wrote before it can reach every inbox on the site.
 *
 * Email clients do not run scripts, but the control panel previews this markup
 * and a stolen admin session could otherwise turn a template into a phishing
 * kit sent from the site's own address. So anything that executes, submits or
 * re-points the page is refused outright.
 *
 * Remote images and stylesheets are only warned about: a logo on a CDN is
 * legitimate, but every load tells that host when and where the email was
 * opened, which is worth saying out loud before it goes to every reader.
 */
final class EmailHtmlLinter
{
    private const FORBIDDEN_TAGS = 'script|iframe|frame|frameset|object|embed|applet|form|input|textarea|select|button|base|meta|link|svg|math|template|portal|style';

    /** Tags only the head of a whole document needs, so only a layout may use them. */
    private const DOCUMENT_TAGS = ['meta', 'style'];

    /**
     * @param  string  $appUrl  Where the site lives; resources from its own host are not reported
     * @param  bool  $document  A whole HTML document (a layout), which may also have <meta> and <style>
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public static function lintHtml(string $html, string $appUrl = '', bool $document = false): array
    {
        $errors = [];
        $warnings = [];

        if (preg_match_all('#<\s*('.self::FORBIDDEN_TAGS.')\b#i', $html, $m)) {
            foreach (array_unique(array_map('strtolower', $m[1])) as $tag) {
                if ($document && in_array($tag, self::DOCUMENT_TAGS, true)) {
                    continue;
                }

                $errors[] = in_array($tag, self::DOCUMENT_TAGS, true)
                    ? "<{$tag}> belongs in the layout's head, not in an email's words."
                    : "<{$tag}> tags are not allowed in emails.";
            }
        }

        if ($document) {
            $errors = array_merge($errors, self::lintCss(implode("\n", self::styleBlocks($html)), $appUrl)['errors']);
        }

        if (preg_match('#<[^>]*\son[a-z]+\s*=#i', $html)) {
            $errors[] = 'Event handler attributes such as onclick are not allowed.';
        }

        if (preg_match('#(?:javascript|vbscript)\s*:|data\s*:\s*text/html#i', $html)) {
            $errors[] = 'Script links (javascript:) are not allowed.';
        }

        $errors = array_merge($errors, self::placeholderProblems($html));

        foreach (self::remoteHosts($html, $appUrl) as $host) {
            $warnings[] = "Loads content from {$host} each time the email is opened, which tells that site when and where it was read.";
        }

        if (preg_match('#<img\b(?![^>]*\balt\s*=)[^>]*>#i', $html)) {
            $warnings[] = 'An image has no alt text, so screen readers and clients that block images show nothing.';
        }

        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public static function lintCss(string $css, string $appUrl = ''): array
    {
        $errors = [];
        $warnings = [];

        if (str_contains($css, '<')) {
            $errors[] = 'CSS cannot contain the < character.';
        }

        if (preg_match('/expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', $css)) {
            $errors[] = 'CSS cannot run script (expression(), behavior or javascript: URLs).';
        }

        if (preg_match('/@import/i', $css)) {
            $errors[] = 'CSS cannot @import other stylesheets.';
        }

        if (str_contains($css, '{{')) {
            $errors[] = 'Placeholders cannot be used in CSS.';
        }

        foreach (self::remoteHosts($css, $appUrl) as $host) {
            $warnings[] = "The CSS loads content from {$host} each time the email is opened, which tells that site when and where it was read.";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * The contents of every <style> element.
     *
     * @return list<string>
     */
    private static function styleBlocks(string $html): array
    {
        preg_match_all('#<style\b[^>]*>(.*?)</style\s*>#is', $html, $m);

        return $m[1];
    }

    /**
     * Placeholders that are malformed or sit somewhere a value could change the markup itself.
     *
     * @return list<string>
     */
    public static function placeholderProblems(string $html): array
    {
        $errors = [];

        if (preg_match_all('/\{\{(.*?)\}\}/s', $html, $all, PREG_OFFSET_CAPTURE)) {
            foreach ($all[1] as $index => [$inner]) {
                $name = trim($inner);

                if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                    $errors[] = 'Placeholder {{'.$inner.'}} is not valid: use lowercase letters, numbers and underscores, starting with a letter.';

                    continue;
                }

                $offset = $all[0][$index][1];

                if (self::insideTag($html, $offset) && !self::insideQuotedValue($html, $offset)) {
                    $errors[] = "Placeholder {{ {$name} }} is inside a tag but not inside a quoted attribute value. Write attr=\"{{ {$name} }}\".";
                }
            }
        }

        if (substr_count($html, '{{') !== substr_count($html, '}}')) {
            $errors[] = 'There is an unclosed {{ placeholder.';
        }

        return $errors;
    }

    /**
     * Whether $offset falls between a < and its closing >.
     */
    public static function insideTag(string $html, int $offset): bool
    {
        $before = substr($html, 0, $offset);
        $open = strrpos($before, '<');
        $close = strrpos($before, '>');

        // No > yet at all still counts: the first tag of a fragment is a tag.
        return $open !== false && ($close === false || $open > $close);
    }

    private static function insideQuotedValue(string $html, int $offset): bool
    {
        $before = substr($html, 0, $offset);
        $tag = substr($before, (int) strrpos($before, '<'));

        // An odd number of quotes since the tag opened means one is still open.
        return substr_count($tag, '"') % 2 === 1 || substr_count($tag, "'") % 2 === 1;
    }

    /**
     * Hosts other than the site's own that src, background or url() point at.
     *
     * @return list<string>
     */
    private static function remoteHosts(string $markup, string $appUrl): array
    {
        $ownHost = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        $hosts = [];

        preg_match_all('#(?:\b(?:src|background)\s*=\s*["\']?|url\(\s*["\']?)\s*(https?:)?//([^/"\'\s)>]+)#i', $markup, $m);

        foreach ($m[2] as $host) {
            $host = strtolower($host);

            if ($host !== $ownHost) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }
}
