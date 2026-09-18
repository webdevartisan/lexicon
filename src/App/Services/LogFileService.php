<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * The application log files in storage/logs: listing, reading the tail, emptying.
 *
 * A file is only ever opened when the directory listing itself produced its name,
 * so a request can never point this at anything outside the folder.
 */
final class LogFileService
{
    public function __construct(private string $directory) {}

    /**
     * Log files keyed by filename, newest first.
     *
     * @return array<string, array{path: string, size: int, modified: int}>
     */
    public function all(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $files = [];
        foreach (scandir($this->directory) ?: [] as $file) {
            $path = $this->directory.DIRECTORY_SEPARATOR.$file;
            if (is_file($path) && preg_match('/^[A-Za-z0-9._-]+\.log$/', $file)) {
                $files[$file] = [
                    'path' => $path,
                    'size' => (int) filesize($path),
                    'modified' => (int) filemtime($path),
                ];
            }
        }

        uasort($files, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $files;
    }

    /**
     * Last lines of a log, read from the end so a large file is not loaded whole.
     *
     * @throws InvalidArgumentException When the name is not one of the listed logs
     * @throws RuntimeException When the file cannot be read
     */
    public function tail(string $name, int $lines): string
    {
        $handle = @fopen($this->pathFor($name), 'rb');
        if ($handle === false) {
            throw new RuntimeException("{$name} could not be opened for reading.");
        }

        $size = (int) fstat($handle)['size'];
        $chunk = 65536;
        $buffer = '';
        $position = $size;

        while ($position > 0 && substr_count($buffer, "\n") <= $lines) {
            $step = min($chunk, $position);
            $position -= $step;
            fseek($handle, $position);
            $buffer = fread($handle, $step).$buffer;
        }
        fclose($handle);

        return implode("\n", array_slice(explode("\n", rtrim($buffer, "\r\n")), -$lines));
    }

    /**
     * Empty a log in place and return how many bytes it held.
     *
     * The file is truncated under an exclusive lock rather than deleted, so a
     * process holding it open for append keeps writing to the same file.
     *
     * @throws InvalidArgumentException When the name is not one of the listed logs
     * @throws RuntimeException When the file could not be emptied
     */
    public function clear(string $name): int
    {
        $path = $this->pathFor($name);

        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException("{$name} could not be opened for writing. Check the file permissions.");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException("{$name} is locked by another process. Try again in a moment.");
            }

            $bytes = (int) fstat($handle)['size'];

            if (!ftruncate($handle, 0) || !fflush($handle) || (int) fstat($handle)['size'] !== 0) {
                throw new RuntimeException("{$name} could not be emptied.");
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $bytes;
    }

    /**
     * @throws InvalidArgumentException When the name is not one of the listed logs
     */
    private function pathFor(string $name): string
    {
        $files = $this->all();
        if (!isset($files[$name])) {
            throw new InvalidArgumentException("There is no log called {$name}.");
        }

        return $files[$name]['path'];
    }
}
