<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Single source of truth for which locales the platform serves.
 *
 * The supported list previously lived in five separate require calls plus a
 * hardcoded model constant. They drifted, and fr and de stayed advertised for
 * months with no strings file behind them, rendering raw keys to visitors.
 */
final class LocaleRegistry
{
    /**
     * Right-to-left languages.
     *
     * Direction is a property of the language, not a deployment choice, so it
     * lives in code where the admin UI cannot produce a state like Greek RTL.
     */
    public const RTL = ['ar', 'he', 'fa', 'ur'];

    private static ?self $instance = null;

    /** @var array{supported: string[], default: string}|null */
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
            $config['supported'],
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
        return $this->readConfig()['supported'];
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
        return in_array(strtolower(trim($code)), self::RTL, true);
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
     * @return array{supported: string[], default: string}
     */
    private function readConfig(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $override = $this->rootPath.'/storage/localization.json';

        if (is_file($override)) {
            $decoded = json_decode((string) file_get_contents($override), true);

            if (is_array($decoded) && isset($decoded['supported'], $decoded['default'])) {
                return $this->config = $this->normalizeConfig($decoded);
            }
        }

        return $this->config = $this->normalizeConfig(
            require $this->rootPath.'/config/localization.php'
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{supported: string[], default: string}
     */
    private function normalizeConfig(array $raw): array
    {
        $supported = is_array($raw['supported'] ?? null) ? $raw['supported'] : ['en'];

        return [
            'supported' => array_values(array_map(
                static fn ($code): string => strtolower(trim((string) $code)),
                $supported
            )),
            'default' => strtolower(trim((string) ($raw['default'] ?? 'en'))),
        ];
    }
}
