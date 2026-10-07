<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Single source of truth for which locales the platform serves, and for each
 * one's name, text direction and Open Graph locale.
 *
 * All of it is read from config/localization.php (or its storage override) and
 * nothing else keeps a list of its own. The supported list previously lived in
 * five separate require calls plus a hardcoded model constant. They drifted,
 * and fr and de stayed advertised for months with no strings file behind them,
 * rendering raw keys to visitors.
 *
 * @phpstan-type Language array{name: string, dir: string, og_locale: string}
 * @phpstan-type Config array{default: string, languages: array<string, Language>}
 */
final class LocaleRegistry
{
    private static ?self $instance = null;

    /** @var Config|null */
    private ?array $config = null;

    /** @var string[]|null */
    private ?array $usable = null;

    public function __construct(private string $rootPath) {}

    /**
     * Shared instance for pre-routing, which runs before the DI container exists.
     */
    public static function instance(): self
    {
        return self::$instance ??= new self(ROOT_PATH);
    }

    /**
     * Drop the shared instance. Tests only, to keep global state from leaking
     * between cases.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Locales the platform will actually serve: configured, and backed by a
     * strings file.
     *
     * A configured locale with no JSON renders every key raw, so it is treated
     * as absent rather than shipped broken.
     *
     * @return string[]
     */
    public function supported(): array
    {
        if ($this->usable !== null) {
            return $this->usable;
        }

        $config = $this->readConfig();

        $usable = array_values(array_filter(
            array_keys($config['languages']),
            fn (string $code): bool => $this->hasTranslationFile($code)
        ));

        // An empty list would take the whole site down rather than degrade one
        // locale, so the default is always kept as a floor.
        return $this->usable = $usable !== [] ? $usable : [$config['default']];
    }

    /**
     * Every locale named in configuration, including ones with no strings file.
     *
     * The admin UI needs this to show a configured-but-incomplete locale. Routing
     * and metadata must use supported() instead.
     *
     * @return string[]
     */
    public function configured(): array
    {
        return array_keys($this->readConfig()['languages']);
    }

    /**
     * The fallback locale, guaranteed to be a member of supported().
     */
    public function default(): string
    {
        $default = $this->readConfig()['default'];
        $supported = $this->supported();

        return in_array($default, $supported, true) ? $default : ($supported[0] ?? 'en');
    }

    /**
     * Whether the platform serves this locale, strings-file filtering included.
     */
    public function isSupported(string $code): bool
    {
        return in_array(strtolower(trim($code)), $this->supported(), true);
    }

    /**
     * Whether this language is written right to left.
     */
    public function isRtl(string $code): bool
    {
        return ($this->language($code)['dir'] ?? 'ltr') === 'rtl';
    }

    /**
     * The language's own name for itself, for use in a language picker.
     *
     * Deliberately not translated. A picker exists for someone who cannot read
     * the language currently on screen, so labelling Greek as "Greek" is useless
     * to the one person who needs that entry.
     *
     * @param  string  $code  Locale code, any case
     * @return string Native name, or the uppercased code when unknown
     */
    public function nativeName(string $code): string
    {
        return $this->language($code)['name'] ?? strtoupper(strtolower(trim($code)));
    }

    /**
     * Each supported language's own name, keyed by code in picker order.
     *
     * @return array<string, string>
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->supported() as $code) {
            $names[$code] = $this->nativeName($code);
        }

        return $names;
    }

    /**
     * The region-qualified Open Graph locale, e.g. el_GR. A language without
     * one gets its code doubled (fr_FR), which is right often enough to be the
     * fallback but worth configuring.
     */
    public function ogLocale(string $code): string
    {
        $code = strtolower(trim($code));

        return $this->language($code)['og_locale'] ?? $code.'_'.strtoupper($code);
    }

    /**
     * Lowercase and validate a locale code, returning null when unsupported.
     *
     * Callers previously reimplemented this dance inline and disagreed on the
     * details, which is part of how the drift happened.
     */
    public function normalize(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $code = strtolower(trim($code));

        return in_array($code, $this->supported(), true) ? $code : null;
    }

    /**
     * Whether locales/{code}.json exists. Existence only, deliberately not a key
     * coverage threshold: a threshold would let a locale vanish from routing when
     * someone adds English strings without translating them.
     */
    public function hasTranslationFile(string $code): bool
    {
        $code = strtolower(trim($code));

        // The code arrives straight from the URL prefix, so a traversal attempt
        // must not become a filesystem probe.
        if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $code) !== 1) {
            return false;
        }

        return is_file($this->rootPath.'/locales/'.$code.'.json');
    }

    /**
     * @return Language|null
     */
    private function language(string $code): ?array
    {
        return $this->readConfig()['languages'][strtolower(trim($code))] ?? null;
    }

    /**
     * @return Config
     */
    private function readConfig(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $override = $this->rootPath.'/storage/localization.json';

        if (is_file($override)) {
            $decoded = json_decode((string) file_get_contents($override), true);

            if (is_array($decoded) && is_array($decoded['languages'] ?? null) && isset($decoded['default'])) {
                return $this->config = $this->normalizeConfig($decoded);
            }
        }

        return $this->config = $this->normalizeConfig(
            require $this->rootPath.'/config/localization.php'
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return Config
     */
    private function normalizeConfig(array $raw): array
    {
        $default = strtolower(trim((string) ($raw['default'] ?? 'en')));
        $languages = [];

        foreach (is_array($raw['languages'] ?? null) ? $raw['languages'] : [] as $code => $language) {
            $code = strtolower(trim((string) $code));
            $language = is_array($language) ? $language : [];

            $languages[$code] = [
                'name' => is_string($language['name'] ?? null) && $language['name'] !== '' ? $language['name'] : strtoupper($code),
                'dir' => strtolower((string) ($language['dir'] ?? 'ltr')) === 'rtl' ? 'rtl' : 'ltr',
                'og_locale' => is_string($language['og_locale'] ?? null) && $language['og_locale'] !== '' ? $language['og_locale'] : $code.'_'.strtoupper($code),
            ];
        }

        return [
            'default' => $default,
            'languages' => $languages !== [] ? $languages : [$default => ['name' => strtoupper($default), 'dir' => 'ltr', 'og_locale' => $default.'_'.strtoupper($default)]],
        ];
    }
}
