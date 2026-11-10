<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Framework\Modules\ModuleRegistry;
use Throwable;

/**
 * Rebuilds the module registry cache, for after a deploy adds or changes a module folder.
 *
 * Usage: php cli module:cache
 */
class ModuleCacheCommand
{
    public function __construct(private ModuleRegistry $registry) {}

    /**
     * @param  array<int, string>  $arguments  Unused
     * @return int Exit code (0 = rebuilt, 1 = failure)
     */
    public function handle(array $arguments = []): int
    {
        try {
            $this->registry->rebuild();
        } catch (Throwable $e) {
            echo "✗ {$e->getMessage()}\n";

            return 1;
        }

        foreach ($this->registry->all() as $key => $manifest) {
            $state = $this->registry->state($key) ?? 'not installed';
            echo "  {$key} v{$manifest->version}: {$state}\n";
        }
        echo "✓ Module cache rebuilt.\n";

        return 0;
    }
}
