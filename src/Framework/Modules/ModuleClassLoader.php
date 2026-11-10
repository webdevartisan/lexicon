<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * Loads module classes from each module's own src folder.
 *
 * Composer cannot map these: the folder is kebab case (journey-map) while the
 * namespace is PascalCase (Modules\JourneyMap), so each manifest's class namespace
 * is pointed at its src folder here instead.
 */
final class ModuleClassLoader
{
    /** @var array<string, string> Namespace prefix with trailing backslash => src folder */
    private static array $prefixes = [];

    private static bool $registered = false;

    /**
     * Make a module's classes loadable.
     */
    public static function register(ModuleManifest $manifest): void
    {
        $namespace = substr($manifest->class, 0, (int) strrpos($manifest->class, '\\') + 1);
        if ($namespace === '') {
            return;
        }

        self::$prefixes[$namespace] = $manifest->path.'/src/';

        if (!self::$registered) {
            spl_autoload_register([self::class, 'load']);
            self::$registered = true;
        }
    }

    /**
     * Autoloader callback.
     */
    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $dir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
}
