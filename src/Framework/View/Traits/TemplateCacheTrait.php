<?php

declare(strict_types=1);

namespace Framework\View\Traits;

/**
 * Template cache block support.
 *
 * Compile {% cache %} blocks into PHP that uses the fragment cache.
 * When caching is disabled, output the content directly to avoid overhead.
 */
trait TemplateCacheTrait
{
    /**
     * Replace cache blocks with fragment cache PHP code.
     *
     * Supported key syntaxes:
     * - {% cache 'static-key' %}
     * - {% cache $dynamicKey %}
     * - {% cache $area . ':sidebar:nav' %}
     * - {% cache "sidebar:$area:nav" %}
     * - {% cache 'prefix:' . $var . ':suffix' %}
     *
     * Supported parameters:
     * - ttl=3600
     * - localized=true|false
     *
     * @param  string  $code  Template code to process.
     */
    private function replaceCacheBlocks(string $code): string
    {
        $cacheEnabled = $this->isCacheEnabled();

        $pattern = '#\{\%\s*cache\s+(?<header>.*?)\s*\%\}(?<content>.*?)\{\%\s*endcache\s*\%\}#s';

        return preg_replace_callback($pattern, function (array $match) use ($cacheEnabled): string {
            $parsed = $this->parseCacheHeader($match['header']);

            $rawKey = $parsed['rawKey'];
            $ttl = $parsed['ttl'];
            $localized = $parsed['localized'];
            $content = $match['content'];

            if (!$cacheEnabled) {
                return '<?php /* Fragment cache disabled: '.addslashes($rawKey).' */ ?>'."\n".$content;
            }

            $compiledKey = $this->compileKeyExpression($rawKey);

            $compiled = '<?php '."\n";
            $compiled .= '// Fragment cache'."\n";
            $compiled .= '$__cacheKey = '.$compiledKey.';'."\n";
            $compiled .= '$__cacheVars = get_defined_vars();'."\n";
            $compiled .= 'echo fragment()->remember($__cacheKey, function () use ($__cacheVars) {'."\n";
            $compiled .= '    extract($__cacheVars, EXTR_SKIP);'."\n";
            $compiled .= '    ob_start();'."\n";
            $compiled .= '?>';
            $compiled .= $content;
            $compiled .= '<?php'."\n";
            $compiled .= '    return ob_get_clean();'."\n";
            $compiled .= '}, '.$ttl.', '.($localized ? 'true' : 'false').'); ?>';

            return $compiled;
        }, $code);
    }

    /**
     * Parse a cache directive header into key and options.
     *
     * Examples:
     * - 'static-key'
     * - $dynamicKey
     * - $area . ':sidebar:nav' ttl=7200
     * - "sidebar:$area:nav" localized=false
     *
     * @return array{rawKey:string, ttl:int, localized:bool}
     */
    private function parseCacheHeader(string $header): array
    {
        $header = trim($header);

        $ttl = 3600;
        $localized = true;

        if (preg_match('/\s+ttl=(\d+)\b/', $header, $ttlMatch) === 1) {
            $ttl = (int) $ttlMatch[1];
            $header = preg_replace('/\s+ttl=\d+\b/', '', $header, 1) ?? $header;
        }

        if (preg_match('/\s+localized=(true|false)\b/', $header, $localizedMatch) === 1) {
            $localized = $localizedMatch[1] === 'true';
            $header = preg_replace('/\s+localized=(true|false)\b/', '', $header, 1) ?? $header;
        }

        $rawKey = trim($header);

        if ($rawKey === '') {
            error_log('Warning: Empty cache key expression.');

            return [
                'rawKey' => "'invalid_cache_key'",
                'ttl' => $ttl,
                'localized' => $localized,
            ];
        }

        return [
            'rawKey' => $rawKey,
            'ttl' => $ttl,
            'localized' => $localized,
        ];
    }

    /**
     * Compile a cache key expression into safe PHP code.
     *
     * Supported:
     * - 'static-key'
     * - $dynamicKey
     * - $area . ':sidebar:nav'
     * - "sidebar:$area:nav"
     * - 'prefix:' . $var . ':suffix'
     */
    private function compileKeyExpression(string $rawKey): string
    {
        $rawKey = trim($rawKey);

        if ($this->isSingleQuotedLiteral($rawKey)) {
            return $rawKey;
        }

        if ($this->isDoubleQuotedLiteral($rawKey)) {
            $inner = substr($rawKey, 1, -1);

            return $this->convertInterpolationToConcatenation($inner);
        }

        if (!$this->isSafeCacheExpression($rawKey)) {
            error_log("Warning: Invalid cache key expression: {$rawKey}");

            return "'invalid_cache_key'";
        }

        return $rawKey;
    }

    /**
     * Determine whether the expression is a single-quoted literal.
     */
    private function isSingleQuotedLiteral(string $value): bool
    {
        return preg_match("/^'[^']*'$/", $value) === 1;
    }

    /**
     * Determine whether the expression is a double-quoted literal.
     */
    private function isDoubleQuotedLiteral(string $value): bool
    {
        return preg_match('/^"[^"]*"$/', $value) === 1;
    }

    /**
     * Validate that a cache key expression contains only allowed syntax.
     *
     * Allowed:
     * - Variables: $key
     * - Concatenation: .
     * - Quotes: ' and "
     * - Characters used in cache keys: letters, digits, underscore, colon, hyphen, spaces
     */
    private function isSafeCacheExpression(string $expression): bool
    {
        return preg_match('/^[\$\w\.\s:\-\'"]+$/', $expression) === 1;
    }

    /**
     * Convert a double-quoted interpolated string into PHP concatenation.
     *
     * Example:
     * "sidebar:$area:nav" => 'sidebar:' . $area . ':nav'
     */
    private function convertInterpolationToConcatenation(string $interpolated): string
    {
        $parts = preg_split('/(\$\w+)/', $interpolated, -1, PREG_SPLIT_DELIM_CAPTURE);

        $compiled = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (str_starts_with($part, '$')) {
                $compiled[] = $part;
                continue;
            }

            $compiled[] = "'".addslashes($part)."'";
        }

        return implode(' . ', $compiled);
    }

    /**
     * Check if fragment caching is enabled.
     */
    private function isCacheEnabled(): bool
    {
        $config = require ROOT_PATH.'/config/cache.php';

        return $config['enabled'] ?? true;
    }
}
