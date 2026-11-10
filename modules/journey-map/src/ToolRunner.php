<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;

/**
 * Runs the map tool and reports what it prints, so the job can show progress and be cancelled.
 */
interface ToolRunner
{
    /** The exit code reported when the run was stopped on request, as a shell reports Ctrl+C. */
    public const STOPPED = 130;

    /**
     * Run a program from an argument list and wait for it.
     *
     * @param  list<string>  $argv  The program and its arguments, never passed through a shell
     * @param  Closure(string): void  $onOutput  Called with each line it prints, on either output
     * @param  Closure(): bool  $shouldStop  Asked while it runs; true stops it
     * @return int The exit code, or STOPPED
     */
    public function run(array $argv, Closure $onOutput, Closure $shouldStop): int;
}
