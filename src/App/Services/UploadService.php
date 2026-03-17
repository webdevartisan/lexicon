<?php

namespace App\Services;

use App\Interfaces\UploadServiceInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Handle file uploads, storage, and cleanup operations.
 *
 * Provides secure image upload with MIME validation, content hashing,
 * temporary file management, and directory cleanup utilities.
 */
final class UploadService implements UploadServiceInterface
{
    /**
     * Store uploaded image with security validation.
     *
     * Validates file type via extension and MIME sniffing, enforces size limits,
     * generates content-based hash for deduplication, and sanitizes filenames.
     * Performs SVG hardening by rejecting files containing script tags.
     *
     * @param array $file PHP $_FILES array entry
     * @param array $opts Configuration: dir, base_url, max_bytes, allowed_ext, rename
     * @return string|null Public URL of stored file, or null on failure
     * @throws InvalidArgumentException If validation fails
     */
    public function storeImage(array $file, array $opts): ?string
    {
        // Validate file array
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload error.');
        }

        // Enforce size limits
        $maxBytes = (int) ($opts['max_bytes'] ?? 2 * 1024 * 1024); // 2 MB default
        if (!empty($file['size']) && (int) $file['size'] > $maxBytes) {
            throw new InvalidArgumentException('File size exceeds the allowed range.');
        }

        // Validate extension
        $allowedExt = $opts['allowed_ext'] ?? ['jpg', 'jpeg', 'png', 'webp', 'svg'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new InvalidArgumentException('File extension not allowed.');
        }

        // Validate MIME type via file content inspection
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMime = [
            'image/jpeg', 'image/png', 'image/webp', 'image/svg+xml',
        ];
        if (!in_array($mime, $allowedMime, true)) {
            throw new InvalidArgumentException('File type not allowed.');
        }

        // SVG hardening: reject files containing script tags to prevent XSS
        if ($mime === 'image/svg+xml') {
            $svg = file_get_contents($file['tmp_name']);
            if (preg_match('#<script#i', $svg)) {
                return null;
            }
        }

        // Ensure target directory exists
        $targetDir = rtrim((string) $opts['dir'], '/');
        $baseUrl = rtrim((string) $opts['base_url'], '/');
        if ($targetDir === '' || $baseUrl === '') {
            return null;
        }
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        // Generate content-based hash for deduplication and cache busting
        $hash = substr(sha1_file($file['tmp_name']) ?: bin2hex(random_bytes(8)), 0, 12);
        $baseName = ($opts['rename'] ?? pathinfo($file['name'] ?? 'file', PATHINFO_FILENAME));
        $safeBase = preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $baseName) ?: 'file';
        $filename = $safeBase . '-' . $hash . '.' . $ext;

        // Move uploaded file to permanent location
        $dest = $targetDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return null;
        }

        return $baseUrl . '/' . $filename;
    }

    /**
     * Get upload directory and URL for blog post images.
     *
     * @param int $userId User ID
     * @param int $blogId Blog ID
     * @return array{0: string, 1: string} [directory_path, url_base]
     */
    public function userBlogPostPath(int $userId, int $blogId): array
    {
        $dir = ROOT_PATH . '/public/uploads/users/' . $userId . '/blogs/' . $blogId . '/postImages';
        $url = '/uploads/users/' . $userId . '/blogs/' . $blogId . '/postImages';

        return [$dir, $url];
    }

    /**
     * Get upload directory and URL for user profile images.
     *
     * @param int $userId User ID
     * @return array{0: string, 1: string} [directory_path, url_base]
     */
    public function userProfilePath(int $userId): array
    {
        $dir = ROOT_PATH . '/public/uploads/users/' . $userId . '/profile';
        $url = '/uploads/users/' . $userId . '/profile';

        return [$dir, $url];
    }

    /**
     * Get upload directory and URL for blog branding assets.
     *
     * @param int $userId User ID
     * @param int $blogId Blog ID
     * @return array{0: string, 1: string} [directory_path, url_base]
     */
    public function blogBrandingPath(int $userId, int $blogId): array
    {
        $dir = ROOT_PATH . '/public/uploads/users/' . $userId . '/blogs/' . $blogId . '/branding';
        $url = '/uploads/users/' . $userId . '/blogs/' . $blogId . '/branding';

        return [$dir, $url];
    }

    /**
     * Store uploaded file in temporary location before form submission.
     *
     * Used for AJAX uploads where final destination depends on form completion.
     * Files remain in temp until moved to permanent location or garbage collected.
     *
     * @param array $file PHP $_FILES array entry
     * @param int $userId User ID for temp directory isolation
     * @return array{url: string, filename: string, size: int} File metadata for client
     * @throws InvalidArgumentException If upload fails
     */
    public function storeTempImage(array $file, int $userId): array
    {
        $tempDir = ROOT_PATH . '/public/uploads/temp/' . $userId;
        $tempUrl = '/uploads/temp/' . $userId;

        $url = $this->storeImage($file, [
            'dir' => $tempDir,
            'base_url' => $tempUrl,
            'max_bytes' => 5 * 1024 * 1024, // 5MB
            'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
        ]);

        if (!$url) {
            throw new InvalidArgumentException('Failed to store temporary file.');
        }

        return [
            'url' => $url,
            'filename' => basename($url),
            'size' => $file['size'],
        ];
    }

    /**
     * Move file from temporary to permanent post images location.
     *
     * @param string $tempFilename Filename in temp directory
     * @param int $userId User ID
     * @param int $blogId Blog ID
     * @return string|null Public URL of moved file, or null if source missing
     */
    public function moveTempToPermanent(string $tempFilename, int $userId, int $blogId): ?string
    {
        $tempPath = ROOT_PATH . '/public/uploads/temp/' . $userId . '/' . $tempFilename;

        if (!file_exists($tempPath)) {
            return null;
        }

        [$dir, $baseUrl] = $this->userBlogPostPath($userId, $blogId);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $destPath = $dir . '/' . $tempFilename;

        if (rename($tempPath, $destPath)) {
            return $baseUrl . '/' . $tempFilename;
        }

        return null;
    }

    /**
     * Parse Dropzone uploaded files JSON response.
     *
     * Handles both single JSON string and array of JSON strings.
     * Extracts file metadata from encoded format.
     *
     * @param string|array $fieldNames JSON string or array of JSON strings
     * @return array Parsed file data
     */
    public function getUploadedFiles(string|array $fieldNames): array
    {
        $json = $fieldNames ?? '[]';

        if (is_array($json)) {
            $fileNames = [];
            foreach ($json as $key => $value) {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $decoded = $decoded[0];
                }
                $fileNames[$key] = $decoded;
            }

            return $fileNames;
        }

        $decoded = json_decode($json, true);

        // Log JSON errors for debugging
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Move temporary file to blog branding folder with semantic naming.
     *
     * Generates content-based hash and applies semantic prefix (banner, logo, favicon).
     * Example output: /uploads/users/32/blogs/25/branding/banner-db56fa563064.webp
     *
     * @param string $tempFilename Filename in temp directory
     * @param int $userId User ID
     * @param int $blogId Blog ID
     * @param string $prefix Semantic prefix: 'banner', 'logo', or 'favicon'
     * @param string $dir Target directory path
     * @param string $baseUrl Target URL base
     * @return string|null Public URL of moved file
     * @throws InvalidArgumentException If temp file not found
     * @throws RuntimeException If copy operation fails
     */
    public function moveTempToBranding(
        string $tempFilename,
        int $userId,
        int $blogId,
        string $prefix,
        string $dir,
        string $baseUrl
    ): ?string {
        $tempPath = ROOT_PATH . '/public/uploads/temp/' . $userId . '/' . $tempFilename;

        if (!file_exists($tempPath)) {
            throw new InvalidArgumentException("Temporary file not found: $tempFilename");
        }

        $ext = strtolower(pathinfo($tempFilename, PATHINFO_EXTENSION));

        // Generate content-based hash for cache busting
        $hash = substr(sha1_file($tempPath), 0, 12);

        $newFilename = $prefix . '-' . $hash . '.' . $ext;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $destPath = $dir . '/' . $newFilename;

        if (!copy($tempPath, $destPath)) {
            throw new RuntimeException('Failed to copy file to folder');
        }

        return $baseUrl . '/' . $newFilename;
    }

    /**
     * Delete all temporary files for a user.
     *
     * Remove entire temp directory to clean up abandoned uploads.
     * Safe to call even if directory doesn't exist.
     *
     * @param int $userId User ID
     */
    public function cleanupTempFiles(int $userId): void
    {
        $folderPath = ROOT_PATH . '/public/uploads/temp/' . $userId;

        if (is_dir($folderPath)) {
            $this->deleteDirectory($folderPath);
        }
    }

    /**
     * Delete all uploaded files for a user.
     *
     * Remove entire user upload directory including avatars and attachments.
     * Used during account deletion. Failures are logged but don't throw
     * exceptions since file cleanup can be performed later if needed.
     *
     * @param int $userId User ID
     */
    public function deleteUserUploads(int $userId): void
    {
        try {
            [$uploadDir, $urlBase] = $this->userProfilePath($userId);

            if (is_dir($uploadDir)) {
                // Delete profile directory
                $this->deleteDirectory($uploadDir);
            }

            // Also clean up blogs directory if exists
            $userBlogsDir = ROOT_PATH . '/public/uploads/users/' . $userId . '/blogs';
            if (is_dir($userBlogsDir)) {
                $this->deleteDirectory($userBlogsDir);
            }

            // Clean up temp files
            $this->cleanupTempFiles($userId);

        } catch (\Exception $e) {
            // Log but don't throw - file deletion failure shouldn't
            // block account deletion (files can be cleaned up later)
            error_log("Failed to delete uploads for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Recursively delete directory and all contents.
     *
     * Use when removing user uploads or clearing temporary files.
     * Handles nested directories and files safely. Operates recursively
     * since rmdir() only works on empty directories.
     *
     * @param string $dir Directory path to delete
     */
    public function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
