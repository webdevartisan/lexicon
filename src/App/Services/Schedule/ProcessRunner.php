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
 * What goes into that shell command is fixed console command names, row ids
 * cast to int here, and paths passed through escapeshellarg. Nothing an
 * operator can type reaches this class, and nothing should ever be added that
 * does.
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
        return $this->spawn('schedule:run-task '.(int) $taskId.' '.(int) $runId);
    }

    /**
     * Start a whole tick in the background.
     */
    public function dispatchTick(): bool
    {
        return $this->spawn('schedule:run');
    }

    /**
     * Start a console command in the background and let go of it.
     *
     * @param  string  $consoleArguments  Command name and arguments, built from trusted values only
     */
    private function spawn(string $consoleArguments): bool
    {
        $binary = $this->resolveBinary();

        // Refuse rather than hand Windows a path that is not there. Starting a
        // missing executable puts an error dialog on the server desktop and
        // leaves the task sitting at running until the reaper notices, which
        // tells whoever pressed the button nothing at all.
        if ($this->looksLikePath($binary) && !is_file($binary)) {
            return false;
        }

        $command = $this->buildCommand($binary, $consoleArguments);

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
    private function buildCommand(string $binary, string $task): string
    {
        $php = escapeshellarg($binary);
        $cli = escapeshellarg($this->rootPath.DIRECTORY_SEPARATOR.'cli');

        if ($this->isWindows()) {
            // The empty quotes are the window title start expects. Without
            // them it treats the quoted binary path as the title and never
            // runs anything.
            return "cmd /c start /B \"\" {$php} {$cli} {$task} > NUL 2>&1";
        }

        return '/bin/sh -c '.escapeshellarg("nohup {$php} {$cli} {$task} > /dev/null 2>&1 &");
    }

    /**
     * Work out which PHP to start.
     *
     * PHP_BINARY names the running program, which is what we want on the
     * command line and under the built in server, and completely wrong under
     * Apache or php-fpm where it names the web server.
     *
     * PHP_BINDIR looks like the obvious fallback and is not. On Windows it
     * holds the path the build was compiled with, commonly C:\php, which
     * usually does not exist on the machine. Handing that to the shell puts an
     * error dialog on the server desktop, so it is only used when it turns out
     * to be real and we drop to the PATH otherwise.
     */
    private function resolveBinary(): string
    {
        if ($this->phpBinary !== '') {
            return $this->phpBinary;
        }

        if (in_array(PHP_SAPI, ['cli', 'cli-server', 'phpdbg'], true) && PHP_BINARY !== '') {
            return PHP_BINARY;
        }

        $name = $this->isWindows() ? 'php.exe' : 'php';
        $guess = PHP_BINDIR.DIRECTORY_SEPARATOR.$name;

        if (is_file($guess)) {
            return $guess;
        }

        // Nothing dependable left to go on, so let the shell search PATH. On a
        // host where that fails too, set SCHEDULE_PHP_BINARY.
        return 'php';
    }

    /**
     * Whether a binary setting names a location rather than something on PATH.
     */
    private function looksLikePath(string $binary): bool
    {
        return str_contains($binary, '/') || str_contains($binary, '\\');
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
