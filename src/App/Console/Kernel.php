<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\CacheClearCommand;
use App\Console\Commands\CachePruneCommand;
use App\Console\Commands\CacheWarmCommand;
use App\Console\Commands\KeyGenerateCommand;
use Framework\Console\Kernel as ConsoleKernel;

/**
 * Application Console Kernel
 *
 * We extend the framework's base console kernel and register
 * application-specific commands. This keeps command registration
 * in the application layer where it belongs.
 *
 * Add new commands here as your application grows.
 */
class Kernel extends ConsoleKernel
{
    /**
     * Register application commands.
     *
     * We map command names to their handler classes.
     * The framework kernel will automatically load and route these.
     *
     * @return array<string, class-string>
     */
    protected function commands(): array
    {
        return [
            // Cache management commands
            'cache:clear' => CacheClearCommand::class,
            'cache:prune' => CachePruneCommand::class,
            'cache:warm' => CacheWarmCommand::class,
            'key:generate' => KeyGenerateCommand::class,

            // 'db:migrate'    => MigrateCommand::class,
            // 'db:seed'       => SeedCommand::class,
            // 'make:controller' => MakeControllerCommand::class,
        ];
    }
}
