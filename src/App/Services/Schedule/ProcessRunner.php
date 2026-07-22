<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Interfaces\TaskRunnerInterface;

/**
 * Starts tasks as separate processes that outlive whatever asked for them.
 *
 * This is the runner we want. A task started here survives the cron tick or
 * the web request that triggered it, a crash inside it cannot touch anything
 * else, and because it is a real process it can still be stopped when it hangs.
 *
 * Detaching properly matters more than it looks. If the child keeps hold of the
 * standard streams it inherited, the web server can sit waiting on them long
 * after the response should have gone out, so all three are pointed at the null
 * device. The command is then handed to a short lived shell that backgrounds
 * the real work and exits, which lets us close our end immediately instead of
 * waiting on a task that might run for minutes.
 *
 * The only values interpolated into that shell command are the two row ids,
 * cast to int here, and paths passed through escapeshellarg. Nothing an
 * operator can type reaches this class.
 */
class ProcessRunner implements TaskRunnerInterface
{
    public function __construct(
        private string $rootPath,
        private string $phpBinary = '',
    ) {}

    /**
     * Start a task in the background.
     */
    public function dispatch(int $taskId, int $runId): bool
    {
        $command = $this->buildCommand((int) $taskId, (int) $runId);

        $descriptors = [
            ['file', $this->nullDevice(), 'r'],
            ['file', $this->nullDevice(), 'w'],
            ['file', $this->nullDevice(), 'w'],
        ];

        $pipes = [];
        $process = @proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return false;
        }

        // The shell we opened has already handed the work off and exited, so
        // this returns at once. Closing the real task instead would block the
        // dispatcher for as long as the task ran.
        proc_close($process);

        return true;
    }

    public function isDetached(): bool
    {
        return true;
    }

    /**
     * Stop a task that has outstayed its timeout.
     */
    public function kill(int $pid): bool
    {
        if ($pid < 2) {
            return false;
        }

        if ($this->isWindows()) {
            $pipes = [];
            $process = @proc_open(
                'taskkill /F /T /PID '.(int) $pid,
                [['file', $this->nullDevice(), 'w'], ['file', $this->nullDevice(), 'w']],
                $pipes
            );

            if (!is_resource($process)) {
                return false;
            }

            return proc_close($process) === 0;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 9);
        }

        $pipes = [];
        $process = @proc_open('kill -9 '.(int) $pid, [], $pipes);

        return is_resource($process) && proc_close($process) === 0;
    }

    /**
     * Whether this host actually allows starting processes.
     *
     * Checked against the disable_functions list as well, since the function
     * still exists when it has been turned off in php.ini and calling it would
     * only produce a warning.
     */
    public static function isAvailable(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !in_array('proc_open', $disabled, true);
    }

    /**
     * Build the platform specific background command.
     */
    private function buildCommand(int $taskId, int $runId): string
    {
        $php = escapeshellarg($this->resolveBinary());
        $cli = escapeshellarg($this->rootPath.DIRECTORY_SEPARATOR.'cli');
        $task = "schedule:run-task {$taskId} {$runId}";

        if ($this->isWindows()) {
            // The empty quotes are the window title start expects. Without
            // them it treats the quoted binary path as the title and never
            // runs anything.
            return "cmd /c start /B \"\" {$php} {$cli} {$task} > NUL 2>&1";
        }

        return "/bin/sh -c ".escapeshellarg("nohup {$php} {$cli} {$task} > /dev/null 2>&1 &");
    }

    /**
     * Work out which PHP to start.
     *
     * PHP_BINARY is only trustworthy on the command line. Under a web server
     * it names the server itself, so the fallback goes looking for the command
     * line build beside it instead.
     */
    private function resolveBinary(): string
    {
        if ($this->phpBinary !== '') {
            return $this->phpBinary;
        }

        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            return PHP_BINARY;
        }

        return PHP_BINDIR.DIRECTORY_SEPARATOR.($this->isWindows() ? 'php.exe' : 'php');
    }

    private function nullDevice(): string
    {
        return $this->isWindows() ? 'NUL' : '/dev/null';
    }

    private function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }
}
