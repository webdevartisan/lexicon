<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;

/**
 * Follows a growing log file and hands over whole lines. The tool redraws its progress bar with a
 * carriage return, so a carriage return ends a line too.
 */
final class LogTail
{
    private int $offset = 0;

    private string $partial = '';

    /**
     * @param  Closure(string): void  $onLine
     */
    public function __construct(private string $file, private Closure $onLine) {}

    /**
     * Pass on every line written since the last read. A line still being written waits for the next read.
     */
    public function read(): void
    {
        clearstatcache(true, $this->file);
        $handle = fopen($this->file, 'rb');
        if ($handle === false) {
            return;
        }

        fseek($handle, $this->offset);
        $chunk = (string) stream_get_contents($handle);
        fclose($handle);
        $this->offset += strlen($chunk);

        $lines = preg_split('/\r\n|\r|\n/', $this->partial.$chunk) ?: [];
        $this->partial = (string) array_pop($lines);
        array_walk($lines, fn (string $line) => $this->emit($line));
    }

    /**
     * Pass on the last line when the program ended without a line break.
     */
    public function flush(): void
    {
        $this->read();
        $this->emit($this->partial);
        $this->partial = '';
    }

    /**
     * Skip the blank lines a redrawn progress bar leaves behind.
     */
    private function emit(string $line): void
    {
        $line = trim($line);
        if ($line !== '') {
            ($this->onLine)($line);
        }
    }
}
