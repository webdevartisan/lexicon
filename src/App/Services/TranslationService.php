<?php

declare(strict_types=1);

namespace App\Services;

/**
 * TranslationService loads locale JSON and resolves translations by exact key (flat dotted)
 * or by traversing nested arrays using a dot path (structured), with simple {name} interpolation.
 */
class TranslationService
{
    /** @var array<string,mixed> Parsed translations for the active locale. */
    private array $translations = [];

    /** @var string Separator used to split a path like "sidebar.menu.title" into segments. */
    private string $separator;

    /**
     * Constructor: reads locales/{locale}.json and decodes it to an associative array.
     * Keeping all strings in JSON files helps isolate content from code for easier localization.
     */
    public function __construct(string $locale, string $separator = '.')
    {
        $this->separator = $separator; // Allow custom separators if needed in the future.
        $path = ROOT_PATH.'/locales/'.$locale.'.json'; // Conventional per-locale resource file.
        if (file_exists($path)) { // Avoid warnings and allow empty/missing locales gracefully.
            $json = file_get_contents($path); // Read file once per request; upstream caching can optimize further.
            $this->translations = json_decode($json, true) ?? []; // Decode to associative array for key access.
        }
    }

    /**
     * translate: resolves a key via two steps—(1) exact match for flat dotted JSON,
     * then (2) nested traversal for structured JSON using the configured separator.
     * Supports {placeholder} interpolation for basic variable substitution.
     */
    public function translate(string|array $key, array $params = []): string
    {
        // Step 1: exact-key lookup supports flat JSON like {"nav.dashboard": "Dashboard"}.
        if (is_string($key) && array_key_exists($key, $this->translations)) {
            $value = $this->translations[$key]; // Direct hit—no traversal needed.
        } else {
            // Step 2: traverse nested arrays for structured JSON like {"nav": {"dashboard": "Dashboard"}}.
            $segments = is_array($key) ? $key : explode($this->separator, (string) $key); // Split "a.b.c" => ["a","b","c"].
            $value = $this->getByPath($this->translations, $segments); // Walk the nested arrays safely.
        }

        // Fallback: if not found or not a string, return the key so missing strings are visible.
        if (!is_string($value)) {
            $value = is_array($key) ? implode($this->separator, $key) : (string) $key; // Human-friendly fallback.
        }

        // Interpolate {name} style placeholders for simple runtime values.
        if ($params) {
            $value = $this->interpolate($value, $params); // Replace tokens like {count} with values.
        }

        return $value; // Return the final translated string ready for rendering.
    }

    /**
     * getByPath: iteratively traverse an array using segments, e.g., ["sidebar","menu","title"].
     * Returns null if any segment is missing to avoid PHP notices and simplify fallback behavior.
     */
    private function getByPath(array $array, array $segments)
    {
        $current = $array; // Start at the root of the translations array.
        foreach ($segments as $seg) { // Visit each segment in order to drill into nested keys.
            if (!is_array($current) || !array_key_exists($seg, $current)) {
                return; // Missing segment stops traversal and signals "not found".
            }
            $current = $current[$seg]; // Descend one level deeper in the structure.
        }

        return $current; // Final value may be a string (expected) or nested array (mis-key).
    }

    /**
     * interpolate: replace tokens like {name} with corresponding values from $params.
     * This keeps templates clean and localizes message formatting in one place
     */
    private function interpolate(string $text, array $params): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($params) { // Match {token}.
            return array_key_exists($m[1], $params) ? (string) $params[$m[1]] : $m[0]; // Leave unknown tokens intact.
        }, $text); // Return the formatted string with substitutions applied.
    }
}
