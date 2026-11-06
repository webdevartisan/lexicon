<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;

/**
 * Starts the tool with proc_open from an argument list, so no shell ever reads its arguments.
 *
 * Output goes to a temporary log file that is read as it grows. Pipes would block on Windows,
 * where PHP cannot read them without waiting.
 */
final class ProcessToolRunner implements ToolRunner
{
    private const POLL_MICROSECONDS = 500_000;

    /**
     * Run the program and pass on its output until it ends or is stopped.
     */
    public function run(array $argv, Closure $onOutput, Closure $shouldStop): int
    {
        $log = (string) tempnam(sys_get_temp_dir(), 'jm-tool');
        $spec = [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['redirect', 1]];
        $process = proc_open($argv, $spec, $pipes, null, null, ['bypass_shell' => true]);
        if ($process === false) {
            unlink($log);

            return 1;
        }
        fclose($pipes[0]);

        try {
            return $this->watch($process, $log, $onOutput, $shouldStop);
        } finally {
            unlink($log);
        }
    }

    /**
     * Read new output every half second, until the program ends or a stop is asked for.
     *
     * @param resource $process
     */
    private function watch($process, string $log, Closure $onOutput, Closure $shouldStop): int
    {
        $reader = new LogTail($log, $onOutput);
        while (true) {
            $status = proc_get_status($process);
            $reader->read();
            if (!$status['running']) {
                $reader->flush();
                proc_close($process);

                return (int) $status['exitcode'];
            }
            if ($shouldStop()) {
                proc_terminate($process);
                proc_close($process);

                return self::STOPPED;
            }
            usleep(self::POLL_MICROSECONDS);
        }
    }
}
