<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\ImageProcessorInterface;
use App\Models\MediaModel;
use App\Resources\BlogResource;
use InvalidArgumentException;
use RuntimeException;

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
        private ImageProcessorInterface $images,
        private MediaUsageResolver $usage,
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
            'max_bytes' => 50 * 1024 * 1024,
            'preset' => 'media',
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
     * Make sure the image at a URL has a library row and return it, indexing it on
     * the fly if needed. Lets callers outside the library (e.g. the appearance page)
     * hand off any branding image to the editor even if a backfill never ran.
     *
     * @return array<string, mixed>|null The row, or null if the URL isn't a stored upload
     */
    public function ensureIndexed(int $blogId, ?int $userId, string $url, string $source = 'branding'): ?array
    {
        $diskPath = $this->urlToDiskPath($url);
        if ($diskPath === null) {
            return null;
        }

        $row = $this->media->findByPathForBlog($blogId, $diskPath);
        if ($row) {
            return $row;
        }

        if (!is_file(ROOT_PATH.'/storage/'.$diskPath)) {
            return null;
        }

        $this->register($blogId, $userId, $url, $source);

        return $this->media->findByPathForBlog($blogId, $diskPath);
    }

    /**
     * Full detail for one library item plus where it is used, for the editor modal.
     *
     * @return array<string, mixed>|null The row with a 'usages' list, or null if missing
     */
    public function details(int $id, int $blogId): ?array
    {
        $row = $this->media->findForBlog($id, $blogId);
        if (!$row) {
            return null;
        }

        $row['usages'] = $this->usage->usages($blogId, (string) $row['url']);

        return $row;
    }

    /**
     * Shrink every oversized image in the library to a maximum edge, re-encoding in
     * place so URLs (and the posts/branding that use them) stay valid. Images already
     * within the cap are skipped, which keeps repeated runs idempotent and avoids
     * degrading already-optimised files. SVGs are left alone.
     *
     * @return array{processed: int, skipped: int, bytes_before: int, bytes_after: int, bytes_saved: int}
     */
    public function optimizeOversized(int $blogId, int $ownerId, int $cap = 2048, int $quality = 80): array
    {
        $rows = $this->media->listForBlog($blogId, ['limit' => 100000]);
        $processed = 0;
        $skipped = 0;
        $before = 0;
        $after = 0;

        foreach ($rows as $row) {
            $maxEdge = max((int) $row['width'], (int) $row['height']);
            if (($row['mime_type'] ?? '') === 'image/svg+xml' || $maxEdge <= $cap) {
                $skipped++;
                continue;
            }

            try {
                $res = $this->process((int) $row['id'], $blogId, $ownerId, [
                    'mode' => 'overwrite',
                    'width' => $cap,
                    'height' => $cap,
                    'quality' => $quality,
                ]);
                $before += (int) $row['size_bytes'];
                $after += (int) $res['size_bytes'];
                $processed++;
            } catch (\Throwable $e) {
                error_log('Bulk optimise failed for media '.$row['id'].': '.$e->getMessage());
                $skipped++;
            }
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'bytes_before' => $before,
            'bytes_after' => $after,
            'bytes_saved' => max(0, $before - $after),
        ];
    }

    /**
     * Save a library item's editable details: its display name and alt text. Only the
     * friendly `original_name` is changed, never the on-disk filename, which is part of
     * every URL that references the image (renaming the file would break posts/branding).
     *
     * @return array<string, mixed> The updated row
     *
     * @throws InvalidArgumentException
     */
    public function saveMeta(int $id, int $blogId, string $name, ?string $alt): array
    {
        $row = $this->media->findForBlog($id, $blogId);
        if (!$row) {
            throw new InvalidArgumentException('Media item not found.');
        }

        $clean = $this->sanitizeName($name);
        if ($clean === '') {
            throw new InvalidArgumentException('Name cannot be empty.');
        }

        $altClean = $alt === null ? null : (mb_substr(trim(preg_replace('/\s+/', ' ', $alt) ?? ''), 0, 255) ?: null);

        $this->media->updateForBlog($id, $blogId, [
            'original_name' => $clean,
            'alt_text' => $altClean,
        ]);

        $row['original_name'] = $clean;
        $row['alt_text'] = $altClean;

        return $row;
    }

    /**
     * Reduce a user-supplied display name to a safe single line: no path separators
     * or control characters, whitespace collapsed, capped to the column length.
     */
    private function sanitizeName(string $name): string
    {
        $name = preg_replace('#[/\\\\\x00-\x1F]+#', ' ', $name) ?? '';
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        return mb_substr($name, 0, 255);
    }

    /**
     * Apply an edit (crop, resize, compress, convert) to a library image.
     *
     * 'overwrite' rewrites the same file so existing references stay valid; changing
     * format is refused there because it would change the URL. 'new' writes a fresh
     * file and indexes a new row, leaving references untouched.
     *
     * @param  array<string, mixed>  $opts  mode, crop{x,y,width,height}, width, height, quality, format
     * @return array<string, mixed> The affected media row data
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function process(int $id, int $blogId, int $ownerId, array $opts): array
    {
        $row = $this->media->findForBlog($id, $blogId);
        if (!$row) {
            throw new InvalidArgumentException('Media item not found.');
        }

        $srcAbs = ROOT_PATH.'/storage/'.ltrim((string) $row['disk_path'], '/');
        if (!is_file($srcAbs)) {
            throw new RuntimeException('Source file is missing on disk.');
        }

        $mode = ($opts['mode'] ?? 'new') === 'overwrite' ? 'overwrite' : 'new';
        $quality = max(1, min(100, (int) ($opts['quality'] ?? 82)));
        $format = $this->normaliseFormat($opts['format'] ?? null);
        $curExt = $this->normaliseFormat((string) $row['extension']) ?? strtolower((string) $row['extension']);
        $changingFormat = $format !== null && $format !== $curExt;

        if ($mode === 'overwrite' && $changingFormat) {
            throw new InvalidArgumentException('Converting to another format must be saved as a new image.');
        }

        $outExt = $format ?? $curExt;
        $procOpts = ['quality' => $quality];
        if ($changingFormat) {
            $procOpts['format'] = $format === 'jpg' ? 'jpeg' : $format;
        }

        [$destAbs, $destUrl] = $mode === 'overwrite'
            ? [$srcAbs, (string) $row['url']]
            : $this->newMediaTarget($ownerId, $blogId, (string) ($row['original_name'] ?: $row['filename']), $outExt, $opts);

        $result = $this->runEdit($srcAbs, $destAbs, $opts, $procOpts);

        if ($mode === 'new') {
            return $this->indexFile($destUrl, $blogId, null, 'upload', $row['original_name'] ?? null);
        }

        $mime = $this->sniffMime($destAbs);
        $this->media->updateForBlog($id, $blogId, [
            'size_bytes' => (int) (filesize($destAbs) ?: 0),
            'width' => $result['width'],
            'height' => $result['height'],
            'mime_type' => $mime,
        ]);

        return array_merge($row, [
            'size_bytes' => (int) (filesize($destAbs) ?: 0),
            'width' => $result['width'],
            'height' => $result['height'],
            'mime_type' => $mime,
            'usages' => $this->usage->usages($blogId, (string) $row['url']),
        ]);
    }

    /**
     * Run the actual crop/resize/compress against the image processor.
     *
     * @param  array<string, mixed>  $opts  The raw edit options (crop, width, height)
     * @param  array<string, mixed>  $procOpts  quality/format already resolved
     * @return array{width: int, height: int}
     */
    private function runEdit(string $srcAbs, string $destAbs, array $opts, array $procOpts): array
    {
        $resizeW = (int) ($opts['width'] ?? 0);
        $resizeH = (int) ($opts['height'] ?? 0);
        $crop = $opts['crop'] ?? null;

        if (is_array($crop) && isset($crop['width'], $crop['height'])) {
            $rect = [
                'x' => (int) ($crop['x'] ?? 0),
                'y' => (int) ($crop['y'] ?? 0),
                'width' => (int) $crop['width'],
                'height' => (int) $crop['height'],
            ];
            // Keep the crop's aspect when only one dimension is given, so cover() resizes
            // rather than distorts.
            [$outW, $outH] = $this->fitBox($rect['width'], $rect['height'], $resizeW, $resizeH);
            $procOpts['out_w'] = $outW;
            $procOpts['out_h'] = $outH;

            return $this->images->crop($srcAbs, $destAbs, $rect, $procOpts);
        }

        // no crop: resize within a box, or just recompress/convert when no size given
        $info = getimagesize($srcAbs) ?: [0, 0];
        $procOpts['max_w'] = $resizeW > 0 ? $resizeW : (int) $info[0];
        $procOpts['max_h'] = $resizeH > 0 ? $resizeH : (int) $info[1];

        return $this->images->process($srcAbs, $destAbs, $procOpts);
    }

    /**
     * Resolve an output box from a source box and optional target width/height,
     * preserving the source aspect when only one target dimension is supplied.
     *
     * @return array{0: int, 1: int} [width, height]
     */
    private function fitBox(int $srcW, int $srcH, int $targetW, int $targetH): array
    {
        if ($targetW > 0 && $targetH > 0) {
            return [$targetW, $targetH];
        }
        if ($targetW > 0) {
            return [$targetW, max(1, (int) round($targetW * $srcH / max(1, $srcW)))];
        }
        if ($targetH > 0) {
            return [max(1, (int) round($targetH * $srcW / max(1, $srcH))), $targetH];
        }

        return [$srcW, $srcH];
    }

    /**
     * Build the absolute path and public URL for a brand-new derived image in the
     * blog's media folder.
     *
     * @param  array<string, mixed>  $opts  Edit options, mixed into the hash for uniqueness
     * @return array{0: string, 1: string} [absolute path, public url]
     */
    private function newMediaTarget(int $ownerId, int $blogId, string $baseName, string $ext, array $opts): array
    {
        [$dir, $baseUrl] = $this->blogMediaPath($ownerId, $blogId);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $base = pathinfo($baseName, PATHINFO_FILENAME) ?: 'image';
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $base) ?: 'image';
        $hash = substr(sha1($baseName.microtime().json_encode($opts)), 0, 12);
        $filename = $safeBase.'-'.$hash.'.'.$ext;

        return [$dir.'/'.$filename, $baseUrl.'/'.$filename];
    }

    /**
     * Map a requested format to the extension we store, or null if unsupported.
     */
    private function normaliseFormat(?string $format): ?string
    {
        if ($format === null) {
            return null;
        }

        return match (strtolower($format)) {
            'jpg', 'jpeg' => 'jpg',
            'webp' => 'webp',
            'png' => 'png',
            default => null,
        };
    }

    private function sniffMime(string $absPath): ?string
    {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return null;
        }
        $mime = @finfo_file($finfo, $absPath) ?: null;
        finfo_close($finfo);

        return $mime;
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
