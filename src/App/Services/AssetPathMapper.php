<?php

declare(strict_types=1);

namespace App\Services;

final class AssetPathMapper
{
    public function __construct(
        private string $projectRoot
    ) {}

    /**
     * Map a URL path (no scheme/host, e.g. "/themes/foo/public/css/app.css")
     * to an absolute filesystem path, or null if it doesn't look like a known asset.
     * Theme files resolve only inside the theme's public/ folder.
     *
     * Upload URLs keep their public /uploads/... form but resolve into
     * storage/uploads, so uploaded files are only ever
     * reachable through this mapper and never browsable directly.
     */
    public function fileFromUrlPath(string $urlPath): ?string
    {
        $path = parse_url($urlPath, PHP_URL_PATH) ?? $urlPath;
        $relative = ltrim($path, '/');

        // Reject traversal and null-byte tricks before touching the filesystem
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '\\')) {
            return null;
        }
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        $segments = explode('/', $relative);
        $root = strtolower($segments[0]);

        if ($root === 'themes') {
            // Only a theme's public/ folder is web-facing; views/ and theme.json are source
            if (count($segments) < 4 || $segments[1] === '' || $segments[2] !== 'public') {
                return null;
            }
            $base = $this->projectRoot.'/themes/'.$segments[1].'/public';
            $file = $this->projectRoot.'/'.$relative;
        } elseif ($root === 'uploads') {
            // /uploads/... -> /storage/uploads/...
            $base = $this->projectRoot.'/storage/uploads';
            $file = $this->projectRoot.'/storage/'.$relative;
        } else {
            // /assets, /images, ... -> /public/...
            $base = $this->projectRoot.'/public';
            $file = $this->projectRoot.'/public/'.$relative;
        }

        if (!is_file($file)) {
            return null;
        }

        // Containment check as a second layer: the resolved file must stay
        // inside its base directory even if a trick slipped past the string checks
        $realFile = realpath($file);
        $realBase = realpath($base);
        if ($realFile === false || $realBase === false
            || !str_starts_with($realFile, $realBase.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realFile;
    }
}
