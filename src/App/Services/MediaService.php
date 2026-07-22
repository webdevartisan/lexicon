<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MediaModel;
use App\Resources\BlogResource;
use InvalidArgumentException;

/**
 * Orchestrates the per-blog media library on top of UploadService.
 *
 * UploadService is still the only thing that touches $_FILES and the
 * filesystem; this service writes the corresponding row in `media`
 * and answers the library's questions about what's stored.
 */
final class MediaService
{
    public function __construct(
        private UploadService $uploads,
        private MediaModel $media,
    ) {}

    /**
     * Deliberate library upload from the /media page.
     *
     * Stores the file under the new per-blog "media" folder so it's easy
     * to tell library uploads apart from post-images / branding on disk.
     * Files are stored under the blog owner's directory tree (matching the
     * existing branding / post-image conventions) regardless of which
     * collaborator did the upload.
     *
     * @param  array<string, mixed>  $file  Uploaded file entry from $_FILES
     * @return array<string, mixed> Indexed media row data
     */
    public function upload(array $file, int $uploaderId, int $blogId, int $ownerId): array
    {
        if (!isset($file['name'])) {
            throw new InvalidArgumentException('No file uploaded.');
        }

        $originalName = (string) $file['name'];

        [$dir, $baseUrl] = $this->blogMediaPath($ownerId, $blogId);

        $url = $this->uploads->storeImage($file, [
            'dir' => $dir,
            'base_url' => $baseUrl,
            'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_bytes' => 5 * 1024 * 1024,
            'rename' => pathinfo($originalName, PATHINFO_FILENAME) ?: 'image',
        ]);

        if (!$url) {
            throw new \RuntimeException('Upload failed.');
        }

        return $this->indexFile($url, $blogId, $uploaderId, 'upload', $originalName);
    }

    /**
     * Move a temp-uploaded featured image into the blog's post-image
     * folder and index it in the library.
     *
     * Files land under the blog owner's directory tree no matter which
     * collaborator uploaded, same as branding and inline post images.
     *
     * @param  string  $tempFilename  Temp filename from UploadService::getUploadedFiles()
     * @param  int  $uploaderId  Acting user, for temp cleanup and the library row
     * @return string Public URL of the stored image
     */
    public function storeFeaturedImage(string $tempFilename, BlogResource $blog, int $uploaderId): string
    {
        [$dir, $baseUrl] = $this->uploads->userBlogPostPath($blog->ownerId(), $blog->id());

        $path = $this->uploads->moveTempToBranding(
            $tempFilename,
            $blog->ownerId(),
            $blog->id(),
            'featured_image',
            $dir,
            $baseUrl
        );

        $this->uploads->cleanupTempFiles($uploaderId);

        $this->register((int) $blog->id(), $uploaderId, $path, 'post_image');

        return $path;
    }

    /**
     * Make sure a file that was uploaded through one of the existing
     * flows (post featured image, TinyMCE inline, branding) has a row
     * in the library. Safe to call repeatedly.
     *
     * @return array<string, mixed>|null Indexed media row data, or null if skipped
     */
    public function register(int $blogId, ?int $userId, string $url, string $source): ?array
    {
        if (trim($url) === '') {
            return null;
        }

        $diskPath = $this->urlToDiskPath($url);
        if ($diskPath === null) {
            return null;
        }

        if ($this->media->existsByPath($blogId, $diskPath)) {
            return null;
        }

        return $this->indexFile($url, $blogId, $userId, $source, null);
    }

    /**
     * Delete a media item by clearing out both parts of it: the file on disk
     * and the database row that points to it. And if the file has already vanished,
     * we still remove the row so the library can tidy itself up and stay consistent.
     */
    public function delete(int $id, int $blogId): bool
    {
        $row = $this->media->findForBlog($id, $blogId);
        if (!$row) {
            return false;
        }

        // disk_path is relative to storage/ (uploads/...)
        $absPath = ROOT_PATH.'/storage/'.ltrim((string) $row['disk_path'], '/');
        if (is_file($absPath)) {
            @unlink($absPath);
        }

        return $this->media->deleteForBlog($id, $blogId);
    }

    /**
     * Scan that imports existing files on disk for a blog.
     * Idempotent: existing rows are skipped via the unique index.
     *
     * Returns the number of newly indexed files.
     */
    public function backfill(int $blogId, int $ownerId, ?int $uploaderId = null): int
    {
        $added = 0;
        $uploaderId ??= $ownerId;

        $bases = [
            $this->uploads->userBlogPostPath($ownerId, $blogId) + ['source' => 'post_image'],
            $this->uploads->blogBrandingPath($ownerId, $blogId) + ['source' => 'branding'],
            $this->blogMediaPath($ownerId, $blogId) + ['source' => 'backfill'],
        ];

        foreach ($bases as $base) {
            [$dir, $baseUrl] = [$base[0], $base[1]];
            $source = $base['source'];

            if (!is_dir($dir)) {
                continue;
            }

            $files = scandir($dir) ?: [];
            foreach ($files as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $absPath = $dir.'/'.$name;
                if (!is_file($absPath)) {
                    continue;
                }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $url = $baseUrl.'/'.$name;
                if ($this->register($blogId, $uploaderId, $url, $source) !== null) {
                    $added++;
                }
            }
        }

        return $added;
    }

    /**
     * @return array{string, string} Absolute directory and public base URL
     */
    public function blogMediaPath(int $userId, int $blogId): array
    {
        $dir = ROOT_PATH.'/storage/uploads/users/'.$userId.'/blogs/'.$blogId.'/media';
        $url = '/uploads/users/'.$userId.'/blogs/'.$blogId.'/media';

        return [$dir, $url];
    }

    /**
     * Read disk metadata for the file behind a URL and write the row.
     *
     * @return array<string, mixed> Indexed media row data
     */
    private function indexFile(string $url, int $blogId, ?int $userId, string $source, ?string $originalName): array
    {
        $diskPath = $this->urlToDiskPath($url);
        if ($diskPath === null) {
            throw new \RuntimeException('Could not resolve file path for: '.$url);
        }

        $absPath = ROOT_PATH.'/storage/'.$diskPath;
        if (!is_file($absPath)) {
            throw new \RuntimeException('File not found at: '.$absPath);
        }

        $filename = basename($diskPath);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $size = (int) (filesize($absPath) ?: 0);

        $mime = null;
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $absPath) ?: null;
            finfo_close($finfo);
        }

        $width = null;
        $height = null;
        if ($mime !== 'image/svg+xml') {
            $info = @getimagesize($absPath);
            if ($info !== false) {
                $width = (int) $info[0];
                $height = (int) $info[1];
            }
        }

        $id = $this->media->createRecord([
            'blog_id' => $blogId,
            'user_id' => $userId,
            'disk_path' => $diskPath,
            'url' => $url,
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => $mime,
            'extension' => $ext,
            'size_bytes' => $size,
            'width' => $width,
            'height' => $height,
            'source' => $source,
        ]);

        return [
            'id' => $id,
            'blog_id' => $blogId,
            'url' => $url,
            'filename' => $filename,
            'mime_type' => $mime,
            'extension' => $ext,
            'size_bytes' => $size,
            'width' => $width,
            'height' => $height,
            'source' => $source,
        ];
    }

    /**
     * Strip leading slash so a URL like /uploads/users/1/.../foo.jpg
     * becomes a path relative to /public.
     *
     * Returns null for URLs that don't look like our uploads (so we
     * never accidentally index something we shouldn't).
     */
    private function urlToDiskPath(string $url): ?string
    {
        $url = ltrim($url, '/');
        if (!str_starts_with($url, 'uploads/')) {
            return null;
        }

        return $url;
    }
}
