<?php

declare(strict_types=1);

namespace Framework\View;

use Framework\Modules\ModuleManifest;
use Framework\Modules\ModuleRegistry;

/**
 * Finds module views named <key>::<view>, such as journey-map::route, inside the module's views folder.
 */
final class ModuleViewPaths
{
    private const VIEW_PATTERN = '#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#';

    public function __construct(private ModuleRegistry $registry) {}

    /**
     * The file behind a module view name.
     *
     * @param  string  $name  A name like journey-map::route or journey-map::partials/stop
     * @return string|null The absolute file path, or null when the module is not running or the file is missing
     */
    public function resolve(string $name): ?string
    {
        [$key, $view] = array_pad(explode('::', $name, 2), 2, '');

        // The pattern allows no dots, so a name can never climb out of the views folder.
        if (!preg_match(ModuleManifest::KEY_PATTERN, $key) || !preg_match(self::VIEW_PATTERN, $view)) {
            return null;
        }

        $manifest = $this->registry->running()[$key] ?? null;
        if ($manifest === null) {
            return null;
        }

        $file = $manifest->path.'/views/'.$view.'.lex.php';

        return is_file($file) ? $file : null;
    }
}
