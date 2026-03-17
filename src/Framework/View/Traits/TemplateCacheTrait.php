<?php

declare(strict_types=1);

namespace Framework\View\Traits;

/**
 * Template cache block support.
 *
 * compile {% cache %} blocks into PHP that uses the fragment cache.
 * When caching is disabled, skip the fragment cache entirely and
 * output the content directly to eliminate overhead.
 */
trait TemplateCacheTrait
{
    /**
     * Replace cache blocks with fragment cache PHP code.
     *
     * Parsing {% cache %} directives and compile them into PHP.
     * The compilation changes based on whether caching is enabled.
     *
     * CACHE ENABLED:
     *   {% cache 'key' %} → fragment()->remember('key', fn() => ...)
     *
     * CACHE DISABLED:
     *   {% cache 'key' %} → Just output the content directly
     *
     * SUPPORTED KEY SYNTAXES:
     *   {% cache 'static-key' %}                              - Static string
     *   {% cache $dynamicKey %}                                - Variable only
     *   {% cache $area . ':sidebar:nav' %}                     - Concatenation with dot operator
     *   {% cache "sidebar:$area:nav" %}                        - String interpolation (double quotes)
     *   {% cache 'prefix:' . $var . ':suffix' %}              - Complex concatenation
     *
     * PARAMETERS:
     *   ttl=3600          - Custom TTL in seconds (default: 3600)
     *   localized=false   - Disable locale awareness (default: true)
     *
     * Examples:
     *   {% cache 'sidebar:nav-structure' %}
     *   {% cache $cacheKey ttl=7200 %}
     *   {% cache $area . ':sidebar' localized=false %}
     *   {% cache "post:$postId:comments" ttl=1800 %}
     *
     * @param  string  $code  Template code to process
     * @return string Compiled code with cache blocks replaced
     */
    private function replaceCacheBlocks(string $code): string
    {
        // check if fragment caching is enabled at compile time
        $cacheEnabled = $this->isCacheEnabled();

        /**
         * Regex breakdown:
         * - [\'\"](?<static_key>[^\'\"]+)[\'\"]  → Matches static quoted strings: 'key' or "key"
         * - (?<dynamic_key>[\$\w\.\s\:\-\'\"]+)  → Matches dynamic expressions: $var, $a.'text', "text$var"
         * capture whichever pattern matches as either static_key or dynamic_key
         */
        $pattern = '#\{\%\s*cache\s+(?:[\'\"](?<static_key>[^\'\"]+)[\'\"]|(?<dynamic_key>[\$\w\.\s\:\-\'\"]+))\s*(?:ttl=(?<ttl>\d+))?\s*(?:localized=(?<localized>true|false))?\s*\%\}(?<content>.*?)\{\%\s*endcache\s*\%\}#s';

        return preg_replace_callback($pattern, function (array $match) use ($cacheEnabled): string {
            // determine if the key is static or dynamic
            $isStatic = !empty($match['static_key']);
            $rawKey = $isStatic ? $match['static_key'] : $match['dynamic_key'];

            $ttl = $match['ttl'] ?? '3600';
            $localized = $match['localized'] ?? 'true';
            $content = $match['content'];

            // CACHE DISABLED: just output the content directly
            if (!$cacheEnabled) {
                $keyDisplay = $isStatic ? $rawKey : '(dynamic)';

                return '<?php /* Fragment cache disabled: '.$keyDisplay.' */ ?>'."\n".$content;
            }

            // CACHE ENABLED: compile the key expression and full fragment cache logic
            $compiledKey = $this->compileKeyExpression($rawKey, $isStatic);

            $compiled = '<?php '."\n";
            $compiled .= '// Fragment cache: '.($isStatic ? $rawKey : 'dynamic key')."\n";
            $compiled .= '// capture all variables and make them available inside the cache closure'."\n";
            $compiled .= '$__cacheKey = '.$compiledKey.';'."\n";
            $compiled .= '$__cacheVars = get_defined_vars();'."\n";
            $compiled .= 'echo fragment()->remember($__cacheKey, function() use ($__cacheVars) { '."\n";
            $compiled .= '    extract($__cacheVars, EXTR_SKIP);'."\n";
            $compiled .= '    ob_start(); '."\n";
            $compiled .= '?>';
            $compiled .= $content;
            $compiled .= '<?php'."\n";
            $compiled .= '    return ob_get_clean();'."\n";
            $compiled .= '}, '.$ttl.', '.$localized.'); ?>';

            return $compiled;
        }, $code);
    }

    /**
     * Compile a cache key expression into safe PHP code.
     *
     * convert template cache key syntax into valid PHP expressions.
     * This method handles static strings, variables, concatenation, and
     * interpolation WITHOUT using eval() to maintain security.
     *
     * Security: validate that the expression only contains safe characters
     * (alphanumeric, dots, colons, hyphens, underscores, quotes, spaces, $).
     * do NOT allow arbitrary PHP code execution.
     *
     * Examples:
     *   'static-key'                → "'static-key'"
     *   $cacheKey                   → "$cacheKey"
     *   $area . ':sidebar'          → "$area . ':sidebar'"
     *   "sidebar:$area:nav"         → '"sidebar:' . $area . ':nav"' (converted to concatenation)
     *
     * @param  string  $rawKey  The raw key expression from the template
     * @param  bool  $isStatic  Whether the key is a static quoted string
     * @return string Valid PHP expression that evaluates to the cache key
     */
    private function compileKeyExpression(string $rawKey, bool $isStatic): string
    {
        // Static keys: just wrap them in quotes
        if ($isStatic) {
            return "'".addslashes($rawKey)."'";
        }

        // Dynamic keys: need to process them carefully
        $rawKey = trim($rawKey);

        // check for string interpolation (double quotes with variables)
        // Example: "sidebar:$area:nav" becomes: 'sidebar:' . $area . ':nav'
        if (preg_match('/^"([^"]*)"$/', $rawKey, $matches)) {
            // found double-quoted string, convert interpolation to concatenation
            $interpolated = $matches[1];
            $compiled = $this->convertInterpolationToConcatenation($interpolated);

            return $compiled;
        }

        // validate that the expression only contains safe characters
        // This prevents code injection while allowing $vars, dots, colons, etc.
        if (!preg_match('/^[\$\w\.\s\:\-\'\"]+$/', $rawKey)) {
            // log a warning and fall back to a safe default
            error_log("Warning: Invalid cache key expression: {$rawKey}");

            return "'invalid_cache_key'";
        }

        // have a valid expression (variable or concatenation), return as-is
        // Examples: $key, $area . ':sidebar', 'prefix:' . $var . ':suffix'
        return $rawKey;
    }

    /**
     * Convert string interpolation to explicit concatenation.
     *
     * transform double-quoted strings with embedded variables into
     * explicit concatenation for better performance and clarity.
     *
     * Security note: only process variable names that match \$\w+ pattern.
     * This prevents injection of arbitrary code.
     *
     * Example:
     *   "sidebar:$area:nav"  →  "'sidebar:' . $area . ':nav'"
     *   "post-$id-$type"     →  "'post-' . $id . '-' . $type"
     *
     * @param  string  $interpolated  String content (without outer quotes)
     * @return string PHP concatenation expression
     */
    private function convertInterpolationToConcatenation(string $interpolated): string
    {
        // split the string by variables and rebuild as concatenation
        $parts = preg_split('/(\$\w+)/', $interpolated, -1, PREG_SPLIT_DELIM_CAPTURE);

        $compiled = [];
        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            if (str_starts_with($part, '$')) {
                // found a variable, add it without quotes
                $compiled[] = $part;
            } else {
                // found a literal string, add it with quotes
                $compiled[] = "'".addslashes($part)."'";
            }
        }

        // join all parts with the concatenation operator
        return implode(' . ', $compiled);
    }

    /**
     * Check if fragment caching is enabled.
     *
     * read the cache config at compile time to determine
     * how to compile {% cache %} blocks.
     *
     * @return bool True if caching is enabled
     */
    private function isCacheEnabled(): bool
    {
        // check the config file
        $config = require ROOT_PATH.'/config/cache.php';

        return $config['enabled'] ?? true;
    }
}
