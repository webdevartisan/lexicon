<?php

declare(strict_types=1);

namespace Framework\Modules;

use Closure;

/**
 * Knows which modules exist on disk and which are installed, from one compiled cache file.
 *
 * Every request would otherwise glob the modules folder and query the modules table,
 * so both answers are written to a PHP file that opcache keeps in memory.
 */
final class ModuleRegistry
{
    /** Slot names core offers. A manifest asking for any other slot is rejected. */
    public const SLOTS = [
        'post.header-aside',
        'post.after-content',
        'blog.nav',
        'editor.main',
        'head.end',
        'body.end',
    ];

    /** @var array<string, ModuleManifest>|null */
    private ?array $manifests = null;

    /** @var array<string, string> */
    private array $states = [];

    /**
     * @param string $modulesPath Folder holding one subfolder per module
     * @param string $cacheFile Where the compiled registry is written
     * @param Closure(): array<string, string> $installedStates Reads module key to state from the database
     */
    public function __construct(
        private readonly string $modulesPath,
        private readonly string $cacheFile,
        private readonly Closure $installedStates,
    ) {
    }

    /**
     * Every module with a valid manifest, installed or not.
     *
     * @return array<string, ModuleManifest>
     */
    public function all(): array
    {
        $this->load();

        return $this->manifests;
    }

    /**
     * Modules that boot on this request: switched on for the site, not off and not paused.
     *
     * @return array<string, ModuleManifest>
     */
    public function running(): array
    {
        return $this->withState(['on']);
    }

    /**
     * Every installed module, off and paused ones included, so deletion and export never skip them.
     *
     * @return array<string, ModuleManifest>
     */
    public function present(): array
    {
        return $this->withState(['on', 'off', 'paused']);
    }

    /**
     * Running blog modules a blog can switch on for itself, the ones its Extras page lists.
     *
     * @return array<string, ModuleManifest>
     */
    public function blogExtras(): array
    {
        return array_filter($this->running(), fn (ModuleManifest $manifest) => $manifest->scope === 'blog');
    }

    /**
     * A module's site wide state.
     *
     * @param string $key The module key
     * @return string|null 'on', 'off' or 'paused', or null when the module is not installed
     */
    public function state(string $key): ?string
    {
        $this->load();

        return $this->states[$key] ?? null;
    }

    /**
     * Find a module by key.
     *
     * @param string $key The module key
     * @return ModuleManifest|null The manifest, or null when no valid module has that key
     */
    public function find(string $key): ?ModuleManifest
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Scan the modules folder and the database again and rewrite the cache file.
     */
    public function rebuild(): void
    {
        $this->manifests = $this->scan();
        $this->states = ($this->installedStates)();
        $this->writeCache();
    }

    /**
     * @param list<string> $states
     * @return array<string, ModuleManifest>
     */
    private function withState(array $states): array
    {
        $this->load();

        return array_filter(
            $this->manifests,
            fn (ModuleManifest $manifest) => in_array($this->states[$manifest->key] ?? null, $states, true)
        );
    }

    private function load(): void
    {
        if ($this->manifests !== null) {
            return;
        }

        $cached = is_file($this->cacheFile) ? include $this->cacheFile : null;
        if (!is_array($cached) || !isset($cached['manifests'], $cached['states'])) {
            $this->rebuild();
            return;
        }

        $this->manifests = [];
        foreach ($cached['manifests'] as $key => $row) {
            $this->manifests[$key] = ModuleManifest::fromArray($row, $row['path']);
        }
        $this->states = $cached['states'];
    }

    /**
     * @return array<string, ModuleManifest>
     */
    private function scan(): array
    {
        $manifests = [];
        foreach (glob($this->modulesPath.'/*/module.json') ?: [] as $file) {
            try {
                $manifest = ModuleManifest::fromFile($file);
            } catch (InvalidManifestException $e) {
                error_log('Module '.basename(dirname($file)).' skipped: '.$e->getMessage());
                continue;
            }
            $manifests[$manifest->key] = $manifest;
        }

        return $manifests;
    }


    private function writeCache(): void
    {
        $rows = array_map(
            fn (ModuleManifest $manifest) => ['path' => $manifest->path] + $manifest->toArray(),
            $this->manifests
        );
        $php = '<?php return '.var_export(['manifests' => $rows, 'states' => $this->states], true).";\n";

        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Write beside the target and rename so a request never includes a half written file.
        $temp = $this->cacheFile.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temp, $php, LOCK_EX);
        rename($temp, $this->cacheFile);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cacheFile, true);
        }
    }
}
