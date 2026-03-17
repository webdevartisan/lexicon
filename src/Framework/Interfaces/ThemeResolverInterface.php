<?php

declare(strict_types=1);

namespace Framework\Interfaces;

/**
 * Resolve logical view names (e.g. "blog/index") to concrete template file paths.
 *
 * Implemented by the application's theming service (e.g. App\Services\ThemeService).
 */
interface ThemeResolverInterface
{
    /**
     * Resolve a logical template name to an absolute file path, or null if not found.
     */
    public function resolveView(string $name): ?string;
}
