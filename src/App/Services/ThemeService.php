<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Interfaces\ThemeResolverInterface;

class ThemeService implements ThemeResolverInterface
{
    private string $themesRoot;

    private ?string $activeTheme = null;

    public function __construct(string $themesRoot)
    {
        $this->themesRoot = rtrim($themesRoot, '/');
    }

    public function activate(?string $theme): void
    {
        $this->activeTheme = $theme ?: null;
    }

    public function getActive(): ?string
    {
        return $this->activeTheme;
    }

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
