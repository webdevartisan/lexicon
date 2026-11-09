<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Modules\ModuleInstaller;
use Throwable;

/**
 * Removes a module and its tables. Refuses while blogs hold its data unless --purge is given.
 *
 * Usage: php cli module:uninstall journey-map [--purge]
 */
class ModuleUninstallCommand
{
    public function __construct(private ModuleInstaller $installer) {}

    /**
     * @param  array<int, string>  $arguments  The module key, optionally --purge
     * @return int Exit code (0 = removed, 1 = bad input, data still present or a failure)
     */
    public function handle(array $arguments = []): int
    {
        $purge = in_array('--purge', $arguments, true);
        $key = trim((string) (array_values(array_diff($arguments, ['--purge']))[0] ?? ''));
        if ($key === '') {
            echo "Usage: php cli module:uninstall <key> [--purge]\n";

            return 1;
        }

        try {
            $this->installer->uninstall($key, $purge);
        } catch (Throwable $e) {
            echo "✗ {$e->getMessage()}\n";

            return 1;
        }

        echo "✓ {$key} is uninstalled and its tables are gone.\n";

        return 0;
    }
}
