<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ModuleModel;
use Framework\Modules\ModuleRegistry;
use Throwable;

/**
 * Lets a module that was paused after repeated errors boot again.
 *
 * Usage: php cli module:resume journey-map
 */
class ModuleResumeCommand
{
    public function __construct(private ModuleModel $modules, private ModuleRegistry $registry)
    {
    }

    /**
     * @param array<int, string> $arguments The module key
     * @return int Exit code (0 = running again, 1 = bad input, not paused or a failure)
     */
    public function handle(array $arguments = []): int
    {
        $key = trim((string) ($arguments[0] ?? ''));
        if ($key === '') {
            echo "Usage: php cli module:resume <key>\n";

            return 1;
        }

        try {
            if (($this->modules->states()[$key] ?? null) !== 'paused') {
                echo "✗ {$key} is not paused.\n";

                return 1;
            }
            $this->modules->switchOn($key);
            $this->registry->rebuild();
        } catch (Throwable $e) {
            echo "✗ {$e->getMessage()}\n";

            return 1;
        }

        echo "✓ {$key} is running again.\n";

        return 0;
    }
}
