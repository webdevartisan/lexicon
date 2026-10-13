<?php

declare(strict_types=1);

namespace Framework\Session\Drivers;

use Framework\Session\SessionDriver;

/**
 * PHP's own file storage, unchanged: data in session.save_path, locked per
 * session by the file handler, collected by PHP's probabilistic gc.
 */
final class FileSessionDriver extends \SessionHandler implements SessionDriver
{
    public function validateId(string $id): bool
    {
        return is_file($this->path($id));
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }

    public function storesRecords(): bool
    {
        return false;
    }

    public function record(string $id): ?array
    {
        return null;
    }

    public function forgetHashes(array $hashes): void
    {
        // Files are named by the raw id, which is never stored. A revoked
        // session's file is wiped the next time it is opened, or by gc.
    }

    public function setClientIp(?string $ip): void {}

    private function path(string $id): string
    {
        // save_path may be "N;MODE;/path"; the directory is always the last part.
        $parts = explode(';', (string) session_save_path());
        $dir = end($parts) ?: sys_get_temp_dir();

        return rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'sess_'.$id;
    }
}
