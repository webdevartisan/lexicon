<?php

declare(strict_types=1);

namespace App\Services\Archive;

use RuntimeException;

/**
 * Keeps an uploaded archive between the preview and the confirm step.
 *
 * Each import gets a random id and lives under the uploader's own folder, with a
 * small record of whose it is and which blog it was previewed for. A confirm
 * must match all of that, so one person can never apply another's upload, or
 * apply an upload to a blog other than the one they previewed it against.
 * Imports are removed when they finish, when they are cancelled, and by a sweep
 * of anything older than an hour that runs whenever a new one is stored.
 */
final class ArchiveImportStore
{
    public const MAX_AGE_SECONDS = 3600;

    private const ID_PATTERN = '/^[a-f0-9]{32}$/';

    public function __construct(private string $root = ROOT_PATH.'/storage/tmp/blog-archives') {}

    /**
     * Take ownership of an uploaded archive and return its import id.
     *
     * @param  string  $source  The uploaded file; moved when it is an HTTP upload, copied otherwise
     */
    public function store(string $source, int $userId, int $blogId): string
    {
        $this->sweep();

        $id = bin2hex(random_bytes(16));
        $dir = $this->userDir($userId);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException("Could not create {$dir}");
        }

        $zip = $dir.'/'.$id.'.zip';
        $moved = is_uploaded_file($source) ? move_uploaded_file($source, $zip) : copy($source, $zip);
        if (!$moved) {
            throw new RuntimeException("Could not store the uploaded archive at {$zip}");
        }

        $record = ['user_id' => $userId, 'blog_id' => $blogId, 'created_at' => time(), 'sha256' => hash_file('sha256', $zip)];
        if (file_put_contents($dir.'/'.$id.'.json', json_encode($record, JSON_THROW_ON_ERROR)) === false) {
            unlink($zip);
            throw new RuntimeException("Could not record import {$id}");
        }

        return $id;
    }

    /**
     * The archive path for an import this user previewed against this blog, or null.
     */
    public function find(string $id, int $userId, int $blogId): ?string
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            return null;
        }

        $dir = $this->userDir($userId);
        $zip = $dir.'/'.$id.'.zip';
        $record = is_file($dir.'/'.$id.'.json') ? json_decode((string) file_get_contents($dir.'/'.$id.'.json'), true) : null;

        if (!is_array($record) || !is_file($zip)
            || ($record['user_id'] ?? null) !== $userId
            || ($record['blog_id'] ?? null) !== $blogId
            || time() - (int) ($record['created_at'] ?? 0) > self::MAX_AGE_SECONDS
            || !hash_equals((string) ($record['sha256'] ?? ''), (string) hash_file('sha256', $zip))) {
            return null;
        }

        return $zip;
    }

    /**
     * An empty folder to extract this import's files into.
     */
    public function workDir(string $id, int $userId): string
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new \InvalidArgumentException('Invalid import id.');
        }

        $dir = $this->userDir($userId).'/'.$id.'.work';
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException("Could not create {$dir}");
        }

        return $dir;
    }

    /**
     * Remove an import: its archive, its record and its work folder.
     */
    public function discard(string $id, int $userId): void
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            return;
        }

        $base = $this->userDir($userId).'/'.$id;
        $this->removeTree($base.'.work');
        foreach ([$base.'.zip', $base.'.json'] as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new RuntimeException("Could not delete {$file}");
            }
        }
    }

    /**
     * Remove every import older than MAX_AGE_SECONDS, whoever left it.
     */
    public function sweep(): int
    {
        $removed = 0;
        $cutoff = time() - self::MAX_AGE_SECONDS;

        foreach (glob($this->root.'/*/*.json') ?: [] as $record) {
            if (filemtime($record) >= $cutoff) {
                continue;
            }

            $userId = (int) basename(dirname($record));
            $this->discard(basename($record, '.json'), $userId);
            $removed++;
        }

        return $removed;
    }

    private function userDir(int $userId): string
    {
        return $this->root.'/'.$userId;
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
