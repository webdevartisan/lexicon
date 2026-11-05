<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Modules\ModuleInstaller;
use Throwable;

/**
 * Installs a module from the modules folder, or applies its new schema files after an update.
 *
 * Usage: php cli module:install journey-map
 */
class ModuleInstallCommand
{
    public function __construct(private ModuleInstaller $installer)
    {
    }

    /**
     * @param array<int, string> $arguments The module key
     * @return int Exit code (0 = installed, 1 = bad input or a failed install)
     */
    public function handle(array $arguments = []): int
    {
        $key = trim((string) ($arguments[0] ?? ''));
        if ($key === '') {
            echo "Usage: php cli module:install <key>\n";

            return 1;
        }

        try {
            $applied = $this->installer->install($key);
        } catch (Throwable $e) {
            echo "✗ {$e->getMessage()}\n";

            return 1;
        }

        foreach ($applied as $file) {
            echo "  applied {$file}\n";
        }
        echo "✓ {$key} is installed.\n";

        return 0;
    }
}
