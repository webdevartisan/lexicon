<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Interfaces\ThemeResolverInterface;

class ThemeService implements ThemeResolverInterface
{
    private string $themesRoot;

    private ?string $activeTheme = null;

    /** @var array<string, array<string, string|null>>|null */
    private ?array $available = null;

    public function __construct(string $themesRoot)
    {
        $this->themesRoot = rtrim($themesRoot, '/');
    }

    /**
     * List installed themes with their metadata, keyed by directory name.
     *
     * The directory name is the canonical key: view and asset resolution both
     * build paths from it, so a mismatched "key" in theme.json would point at
     * a theme that cannot render. Each entry carries name, description,
     * version, author and a screenshot URL (null when the theme ships none).
     *
     * @return array<string, array<string, string|null>> Sorted by display name
     */
    public function available(): array
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $themes = [];

        foreach (glob($this->themesRoot.'/*/theme.json') as $file) {
            $meta = json_decode((string) file_get_contents($file), true);
            if (!is_array($meta)) {
                continue;
            }

            $key = basename(dirname($file));
            $screenshot = is_file($this->themesRoot.'/'.$key.'/public/screenshot.svg')
                ? '/themes/'.$key.'/public/screenshot.svg'
                : null;

            $themes[$key] = [
                'key' => $key,
                'name' => (string) ($meta['name'] ?? ucfirst($key)),
                'description' => (string) ($meta['description'] ?? ''),
                'version' => (string) ($meta['version'] ?? ''),
                'author' => (string) ($meta['author'] ?? ''),
                'screenshot' => $screenshot,
            ];
        }

        uasort($themes, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $this->available = $themes;
    }

    /**
     * Whether a theme key names an installed theme.
     *
     * @param  string  $key  Candidate theme directory name
     */
    public function isValidKey(string $key): bool
    {
        return array_key_exists($key, $this->available());
    }

    public function activate(?string $theme): void
    {
        $this->activeTheme = $theme ?: null;
    }

    public function getActive(): ?string
    {
        return $this->activeTheme;
    }

    /**
     * @return string[] View directories in resolution order (theme first, default last)
     */
    public function viewRoots(): array
    {
        $roots = [];
        if ($this->activeTheme) {
            $roots[] = $this->themesRoot.'/'.$this->activeTheme.'/views/';
        }
        $roots[] = dirname(__DIR__, 3).'/views/'; // default

        return $roots;
    }

    public function resolveView(string $template): ?string
    {
        foreach ($this->viewRoots() as $root) {
            $path = $root.$template;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function assetUrl(string $path, bool $versioned = true): string
    {
        $path = ltrim($path, '/');
        $assetMapper = new AssetPathMapper(ROOT_PATH);

        // Base URL
        if ($this->activeTheme) {
            $url = "/themes/{$this->activeTheme}/public/{$path}";
        } else {
            $url = "/assets/{$path}";
        }

        if (!$versioned) {
            return $url;
        }

        // Ask the mapper where this URL lives on disk
        $file = $assetMapper->fileFromUrlPath($url);
        if ($file !== null) {
            $v = (string) filemtime($file);
            $join = (str_contains($url, '?')) ? '&' : '?';

            return $url.$join.'v='.$v;
        }

        return $url;
    }
}
