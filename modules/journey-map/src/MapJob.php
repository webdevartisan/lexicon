<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;

/**
 * The map job file: the page queues a size check or a download, the background command runs it and
 * writes its progress, and the page reads it back every few seconds.
 *
 * Cancel is a separate flag file, so the command's progress writes can never wipe out an admin's cancel.
 */
class MapJob
{
    /** A running job that has not written for this long is taken as stopped (the server or PHP died). */
    private const STALE_SECONDS = 600;

    private Closure $clock;

    /** @var resource|null Held while the command runs, so a second command finds the job taken. */
    private $lock = null;

    /**
     * @param string $dir The module's folder under storage/, outside public/
     * @param Closure(): int|null $clock The current time, for tests
     */
    public function __construct(private string $dir, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Where the page keeps the place outlines it looked up.
     */
    public function regionDir(): string
    {
        return $this->dir.'/regions';
    }

    /**
     * Ask for a size check or a download. The last measured size is kept for the download check.
     *
     * @param string $kind size or download
     * @param int $userId Who asked, 0 for the scheduler
     */
    public function queue(string $kind, MapRequest $request, int $userId): void
    {
        $this->clearCancel();
        $this->write([
            'state' => 'queued',
            'kind' => $kind,
            'request' => $request->toArray(),
            'userId' => $userId,
            'queuedAt' => ($this->clock)(),
            'size' => $this->read()['size'] ?? null,
        ]);
    }

    /**
     * The command took the job.
     */
    public function begin(int $pid): void
    {
        $now = ($this->clock)();
        $this->update([
            'state' => 'running',
            'pid' => $pid,
            'startedAt' => $now,
            'heartbeat' => $now,
            'percent' => 0,
            'step' => '',
        ]);
    }

    /**
     * How far the current step is, which also shows the command is still alive.
     */
    public function progress(int $percent, string $step): void
    {
        $this->update(['percent' => $percent, 'step' => $step, 'heartbeat' => ($this->clock)()]);
    }

    /**
     * The measured size of the job's request, kept until another size check replaces it.
     */
    public function sized(int $bytes): void
    {
        $this->update(['size' => ['request' => $this->read()['request'] ?? null, 'bytes' => $bytes]]);
    }

    /**
     * The job is done.
     */
    public function finish(): void
    {
        $this->update(['state' => 'finished', 'finishedAt' => ($this->clock)()]);
        $this->clearCancel();
    }

    /**
     * The job stopped without a result.
     *
     * @param string $reason One word the page turns into a sentence
     */
    public function fail(string $reason): void
    {
        $this->update(['state' => 'failed', 'reason' => $reason, 'finishedAt' => ($this->clock)()]);
        $this->clearCancel();
    }

    /**
     * Ask the running command to stop.
     */
    public function requestCancel(): void
    {
        $this->ensureDir();
        touch($this->dir.'/cancel');
    }

    /**
     * Whether the admin pressed Cancel.
     */
    public function cancelRequested(): bool
    {
        clearstatcache(true, $this->dir.'/cancel');

        return is_file($this->dir.'/cancel');
    }

    /**
     * The job as the page shows it, or null when nothing ever ran.
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $job = $this->read();
        if ($job === null) {
            return null;
        }

        // A queued job whose process never started goes quiet just like one that died halfway.
        $quiet = ($this->clock)() - (int) ($job['heartbeat'] ?? $job['queuedAt'] ?? 0);
        if ($this->active($job) && $quiet > self::STALE_SECONDS) {
            $job['state'] = 'failed';
            $job['reason'] = 'stopped';
        }

        return $job;
    }

    /**
     * Whether a job is queued or running, so no second one may start.
     */
    public function busy(): bool
    {
        $job = $this->current();

        return $job !== null && $this->active($job);
    }

    /**
     * The measured size of this exact request, or null when it was never measured.
     */
    public function sizeFor(MapRequest $request): ?int
    {
        $size = $this->read()['size'] ?? null;

        return is_array($size) && ($size['request'] ?? null) === $request->toArray() ? (int) $size['bytes'] : null;
    }

    /**
     * Take the job for this process. False when another command holds it.
     */
    public function lock(): bool
    {
        $this->ensureDir();
        $handle = fopen($this->dir.'/job.lock', 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            return false;
        }

        $this->lock = $handle;

        return true;
    }

    /**
     * Let the job go when the command ends.
     */
    public function unlock(): void
    {
        if ($this->lock !== null) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
            $this->lock = null;
        }
    }

    /**
     * @param array<string, mixed> $job
     */
    private function active(array $job): bool
    {
        return in_array($job['state'] ?? null, ['queued', 'running'], true);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function update(array $changes): void
    {
        $this->write($changes + ($this->read() ?? []));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(): ?array
    {
        $file = $this->dir.'/job.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) && isset($data['state']) ? $data : null;
    }

    /**
     * Write through a temporary file and a rename, so the page never reads half a file.
     *
     * @param array<string, mixed> $job
     */
    private function write(array $job): void
    {
        $this->ensureDir();
        $temp = $this->dir.'/job.json.'.getmypid();
        file_put_contents($temp, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        rename($temp, $this->dir.'/job.json');
    }

    /**
     * Forget an old cancel, so it never stops the next job.
     */
    private function clearCancel(): void
    {
        if (is_file($this->dir.'/cancel')) {
            unlink($this->dir.'/cancel');
        }
    }

    /**
     * Create the folder on first use.
     */
    private function ensureDir(): void
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }
}
