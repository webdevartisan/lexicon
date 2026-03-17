<?php

declare(strict_types=1);

namespace Framework\Filesystem;

/**
 * Path normalization utilities for filesystem operations.
 *
 * centralize path handling to prevent directory traversal and ensure
 * consistent slash usage across platforms (Windows/Unix).
 */
class Path
{
    /**
     * Normalize a filesystem path.
     *
     * convert backslashes to forward slashes, resolve '..' and '.',
     * and remove duplicate slashes for consistent path handling.
     *
     * @param  string  $path  Path to normalize
     * @return string Normalized path
     */
    public static function normalize(string $path): string
    {
        // convert all backslashes to forward slashes for consistency.
        $path = str_replace('\\', '/', $path);

        // remove duplicate slashes.
        $path = preg_replace('#/+#', '/', $path);

        // trim trailing slashes (except for root '/').
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * Join path segments safely.
     *
     * concatenate path parts with forward slashes and normalize the result.
     *
     * @param  string  ...$parts  Path segments to join
     * @return string Joined and normalized path
     */
    public static function join(string ...$parts): string
    {
        $joined = implode('/', array_filter($parts, fn ($p) => $p !== ''));

        return self::normalize($joined);
    }

    /**
     * Check if path is within allowed directory (prevents traversal).
     *
     * use realpath() to resolve symlinks and '..' sequences, then
     * verify the result is within the allowed base directory.
     *
     * @param  string  $path  Path to check
     * @param  string  $allowedBase  Base directory that must contain the path
     * @return bool True if path is safely within allowed base
     */
    public static function isWithin(string $path, string $allowedBase): bool
    {
        $realPath = realpath($path);
        $realBase = realpath($allowedBase);

        if ($realPath === false || $realBase === false) {
            return false;
        }

        return str_starts_with($realPath, $realBase);
    }
}
