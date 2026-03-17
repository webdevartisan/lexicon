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
     */
    public function fileFromUrlPath(string $urlPath): ?string
    {
        $path = parse_url($urlPath, PHP_URL_PATH) ?? $urlPath;
        $relative = ltrim($path, '/');
        $segments = explode('/', $relative);
        $root = strtolower($segments[0] ?? '');

        if ($root === 'themes') {
            // /themes/{theme}/public/...
            $file = $this->projectRoot.'/'.$relative;
        } else {
            // /assets, /images, /uploads -> /public/...
            $file = $this->projectRoot.'/public/'.$relative;
        }

        return is_file($file) ? $file : null;
    }
}
