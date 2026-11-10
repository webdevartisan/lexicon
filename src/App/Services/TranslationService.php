<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Interfaces\TranslatorInterface;

/**
 * TranslationService loads locale JSON and resolves translations by exact key (flat dotted)
 * or by traversing nested arrays using a dot path (structured), with simple {name} interpolation.
 *
 * A key written as "offset::nav.home" belongs to a theme. Themes keep their own text in
 * themes/{name}/locales/{locale}.json so a theme stays self-contained, and a theme string
 * that is missing falls back to that theme's English, never to the platform files.
 * A module key reads the same way from modules/{key}/locales/{locale}.json.
 */
class TranslationService implements TranslatorInterface
{
    /** @var array<string,mixed> Parsed translations for the active locale. */
    private array $translations = [];

    /** @var array<string,mixed> English strings, loaded only when locale !== 'en'. */
    private array $fallback = [];

    /**
     * Theme or module strings and their English, by name.
     *
     * @var array<string, array{0: array<string,mixed>, 1: array<string,mixed>}>
     */
    private array $themes = [];

    /** @var string Separator used to split a path like "sidebar.menu.title" into segments. */
    private string $separator;

    private string $locale;

    /**
     * Constructor: reads locales/{locale}.json and decodes it to an associative array.
     * Keeping all strings in JSON files helps isolate content from code for easier localization.
     *
     * @param  string  $modulesPath  Folder holding the modules, read for "<key>::" keys
     */
    public function __construct(
        string $locale,
        string $separator = '.',
        private string $modulesPath = ROOT_PATH.'/modules'
    ) {
        $this->separator = $separator;
        $this->locale = $locale;
        $this->translations = $this->readJson(ROOT_PATH.'/locales/'.$locale.'.json');

        // A missing key must degrade to English for a reader, not to the raw key.
        // Only non-English visitors pay the second read; an en visitor's fallback
        // is the same file already loaded above, so it stays empty here.
        if ($locale !== 'en') {
            $this->fallback = $this->readJson(ROOT_PATH.'/locales/en.json');
        }
    }

    /**
     * translate: resolves a key via two steps—(1) exact match for flat dotted JSON,
     * then (2) nested traversal for structured JSON using the configured separator.
     * Supports {placeholder} interpolation for basic variable substitution.
     *
     * @param  string|string[]  $key  Dotted key or pre-split path segments
     * @param  array<string, mixed>  $params  Placeholder name => value pairs
     */
    public function translate(string|array $key, array $params = []): string
    {
        $fullKey = $key;
        [$translations, $fallback] = [$this->translations, $this->fallback];
        if (is_string($key) && str_contains($key, '::')) {
            [$theme, $key] = explode('::', $key, 2);
            [$translations, $fallback] = $this->themeStrings($theme);
        }

        $value = $this->lookup($translations, $key);

        // A miss in the active locale degrades to English rather than to the raw
        // key, so a Greek reader never sees "account.changePassword" on the page.
        if (!is_string($value) && $fallback !== []) {
            $fallbackValue = $this->lookup($fallback, $key);
            if (is_string($fallbackValue)) {
                if (env('APP_DEBUG')) {
                    // error_log is the idiom already used in CacheMiddleware; a
                    // logger for one call site would be overkill.
                    $shown = is_array($fullKey) ? implode($this->separator, $fullKey) : $fullKey;
                    error_log('i18n miss, fell back to en: '.$shown);
                }
                $value = $fallbackValue;
            }
        }

        // Only when both the active locale and English miss do we surface the raw
        // key, which keeps a genuinely undefined string visible during development.
        if (!is_string($value)) {
            $value = is_array($fullKey) ? implode($this->separator, $fullKey) : $fullKey; // Human-friendly fallback.
        }

        // Interpolate {name} style placeholders for simple runtime values.
        if ($params) {
            $value = $this->interpolate($value, $params); // Replace tokens like {count} with values.
        }

        return $value; // Return the final translated string ready for rendering.
    }

    /**
     * A theme's or module's strings for this locale and its English, read once per name.
     * A module folder wins over a theme of the same name.
     *
     * @param  string  $theme  Theme directory name or module key, from the part of the key before "::"
     * @return array{0: array<string,mixed>, 1: array<string,mixed>} Strings, then the English fallback
     */
    private function themeStrings(string $theme): array
    {
        // The name becomes part of a file path, so anything but a plain theme slug finds nothing.
        if (preg_match('/^[a-z0-9-]+$/', $theme) !== 1) {
            return [[], []];
        }

        $moduleDir = $this->modulesPath.'/'.$theme.'/locales/';
        $dir = is_dir($moduleDir) ? $moduleDir : ROOT_PATH.'/themes/'.$theme.'/locales/';

        return $this->themes[$theme] ??= [
            $this->readJson($dir.$this->locale.'.json'),
            $this->locale === 'en' ? [] : $this->readJson($dir.'en.json'),
        ];
    }

    /**
     * Decode a locale file, treating a missing or broken file as empty.
     *
     * @param  string  $path  Absolute path to a JSON file
     * @return array<string,mixed> The decoded strings
     */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Two-step lookup against one map: exact flat key, then nested traversal.
     *
     * @param  array<string,mixed>  $map  Translations tree to search
     * @param  string|string[]  $key  Dotted key or pre-split path segments
     * @return mixed String leaf on a hit, null or a non-string on a miss
     */
    private function lookup(array $map, string|array $key): mixed
    {
        // Step 1: exact-key lookup supports flat JSON like {"nav.dashboard": "Dashboard"}.
        if (is_string($key) && array_key_exists($key, $map)) {
            return $map[$key]; // Direct hit—no traversal needed.
        }

        // Step 2: traverse nested arrays for structured JSON like {"nav": {"dashboard": "Dashboard"}}.
        $segments = is_array($key) ? $key : explode($this->separator, (string) $key); // Split "a.b.c" => ["a","b","c"].

        return $this->getByPath($map, $segments); // Walk the nested arrays safely.
    }

    /**
     * getByPath: iteratively traverse an array using segments, e.g., ["sidebar","menu","title"].
     * Returns null if any segment is missing to avoid PHP notices and simplify fallback behavior.
     *
     * @param  array<string, mixed>  $array  Translations tree to walk
     * @param  string[]  $segments  Path segments in traversal order
     * @return mixed String leaf, nested array on mis-key, or null when not found
     */
    private function getByPath(array $array, array $segments): mixed
    {
        $current = $array; // Start at the root of the translations array.
        foreach ($segments as $seg) { // Visit each segment in order to drill into nested keys.
            if (!is_array($current) || !array_key_exists($seg, $current)) {
                return null; // Missing segment stops traversal and signals "not found".
            }
            $current = $current[$seg]; // Descend one level deeper in the structure.
        }

        return $current; // Final value may be a string (expected) or nested array (mis-key).
    }

    /**
     * interpolate: replace tokens like {name} with corresponding values from $params.
     * This keeps templates clean and localizes message formatting in one place
     *
     * @param  array<string, mixed>  $params  Placeholder name => value pairs
     */
    private function interpolate(string $text, array $params): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($params) { // Match {token}.
            return array_key_exists($m[1], $params) ? (string) $params[$m[1]] : $m[0]; // Leave unknown tokens intact.
        }, $text); // Return the formatted string with substitutions applied.
    }
}
