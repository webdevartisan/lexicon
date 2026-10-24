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
        private MediaReferenceCleaner $references,
    ) {}

    /**
     * Deliberate library upload from the /media page.
     *
     * The original file name is kept for the library only; the stored file is
     * image-{hash}, so whatever the person called it never reaches a public URL.
     *
     * @param  array<string, mixed>  $file  Uploaded file entry from $_FILES
     * @return array<string, mixed> Indexed media row data
     */
    public function upload(array $file, int $uploaderId, BlogResource $blog): array
    {
        if (!isset($file['name'])) {
            throw new InvalidArgumentException('No file uploaded.');
        }

        $originalName = (string) $file['name'];
        $blogId = $blog->id();

        [$dir, $baseUrl] = $this->uploads->blogUploadPath($blog->mediaKey());

        $url = $this->uploads->storeImage($file, [
            'dir' => $dir,
            'base_url' => $baseUrl,
            'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_bytes' => 50 * 1024 * 1024,
            'preset' => 'media',
            'rename' => 'image',
        ]);

        if (!$url) {
            throw new \RuntimeException('Upload failed.');
        }

        // The stored name comes from the file's content, so the same image lands on the row it already has
        $diskPath = $this->urlToDiskPath($url);
        $existing = $diskPath !== null ? $this->media->findByPathForBlog($blogId, $diskPath) : null;
        if ($existing !== null) {
            return $existing;
        }

        return $this->indexFile($url, $blogId, $uploaderId, 'upload', $originalName);
    }

    /**
     * Move a temp-uploaded featured image into the blog's folder and index it in the library.
     *
     * @param  string  $tempFilename  Temp filename from UploadService::getUploadedFiles()
     * @param  int  $uploaderId  Acting user, for temp cleanup and the library row
     * @return string Public URL of the stored image
     */
    public function storeFeaturedImage(string $tempFilename, BlogResource $blog, int $uploaderId): string
    {
        $path = $this->uploads->moveTempToBlog($tempFilename, $uploaderId, 'featured_image', $blog->mediaKey());

        $this->uploads->cleanupTempFiles($uploaderId);

        $this->register($blog, $uploaderId, $path, 'post_image');

        return $path;
    }

    /**
     * Make sure a file in the blog's folder has a row in the library. Safe to call repeatedly.
     *
     * @param  int|null  $userId  Who uploaded the file, or null when that is not known
     * @param  string|null  $originalName  Name the file had before it was stored, when known
     * @return array<string, mixed>|null Indexed media row data, or null if skipped
     */
    public function register(BlogResource $blog, ?int $userId, string $url, string $source, ?string $originalName = null): ?array
    {
        // The library deletes and rewrites its files, so it must never take in a file from
        // outside the blog's own folder, such as another blog's image or someone's avatar.
        if (!UploadService::isBlogFile($url, $blog->mediaKey())) {
            return null;
        }

        $diskPath = $this->urlToDiskPath($url);
        if ($diskPath === null || $this->media->existsByPath($blog->id(), $diskPath)) {
            return null;
        }

        return $this->indexFile($url, $blog->id(), $userId, $source, $originalName);
    }

    /**
     * Take an image picked from the library for one of the blog's slots.
     *
     * @return string The picked URL
     *
     * @throws InvalidArgumentException When the URL is not a file in this blog's folder
     */
    public function usePicked(BlogResource $blog, string $url, string $source): string
    {
        if (!UploadService::isBlogFile($url, $blog->mediaKey())) {
            throw new InvalidArgumentException("Pick an image from this blog's media library.");
        }

        // A pick is normally indexed already. If it is not, nobody knows who uploaded it, so the row gets no owner.
        $this->register($blog, null, $url, $source);

        return $url;
    }

    /**
     * Delete a media item: its library row, the post and branding slots that show it,
     * and the file on disk. A file that has already vanished still loses its row.
     */
    public function delete(int $id, BlogResource $blog): bool
    {
        $row = $this->media->findForBlog($id, $blog->id());
        if (!$row) {
            return false;
        }

        // Row and slots go together or not at all, so a failure never leaves a slot
        // pointing at a file the library no longer has. The file goes only after.
        $deleted = $this->media->transaction(function () use ($id, $blog, $row): bool {
            if (!$this->media->deleteForBlog($id, $blog->id())) {
                return false;
            }

            $this->references->clear($blog, (string) $row['url']);

            return true;
        });

        if (!$deleted) {
            return false;
        }

        // disk_path is relative to storage/ (uploads/...)
        $absPath = ROOT_PATH.'/storage/'.ltrim((string) $row['disk_path'], '/');
        if (is_file($absPath)) {
            @unlink($absPath);
        }

        return true;
    }

    /**
     * Make sure the image at a URL has a library row and return it, indexing it on
     * the fly if needed. Lets callers outside the library (e.g. the appearance page)
     * hand off any branding image to the editor even if a backfill never ran.
     *
     * @return array<string, mixed>|null The row, or null if the URL isn't a file in this blog's folder
     */
    public function ensureIndexed(BlogResource $blog, string $url, string $source = 'branding'): ?array
    {
        $diskPath = $this->urlToDiskPath($url);
        if ($diskPath === null) {
            return null;
        }

        $row = $this->media->findByPathForBlog($blog->id(), $diskPath);
        if ($row) {
            return $row;
        }

        if (!is_file(ROOT_PATH.'/storage/'.$diskPath)) {
            return null;
        }

        // Whoever clicked Edit did not necessarily upload it, so the row gets no owner
        $this->register($blog, null, $url, $source);

        return $this->media->findByPathForBlog($blog->id(), $diskPath);
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
     * degrading already-optimised files.
     *
     * @return array{processed: int, skipped: int, bytes_before: int, bytes_after: int, bytes_saved: int}
     */
    public function optimizeOversized(BlogResource $blog, int $cap = 2048, int $quality = 80): array
    {
        $rows = $this->media->listForBlog($blog->id(), ['limit' => 100000]);
        $processed = 0;
        $skipped = 0;
        $before = 0;
        $after = 0;

        foreach ($rows as $row) {
            $maxEdge = max((int) $row['width'], (int) $row['height']);
            if ($maxEdge <= $cap) {
                $skipped++;
                continue;
            }

            try {
                $res = $this->process((int) $row['id'], $blog, [
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
    public function process(int $id, BlogResource $blog, array $opts): array
    {
        $blogId = $blog->id();
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
            : $this->newMediaTarget($blog->mediaKey(), $outExt, $opts);

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
     * blog's folder.
     *
     * @param  array<string, mixed>  $opts  Edit options, mixed into the hash for uniqueness
     * @return array{0: string, 1: string} [absolute path, public url]
     */
    private function newMediaTarget(string $mediaKey, string $ext, array $opts): array
    {
        [$dir, $baseUrl] = $this->uploads->blogUploadPath($mediaKey);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $hash = substr(sha1($mediaKey.microtime().json_encode($opts)), 0, 12);
        $filename = 'image-'.$hash.'.'.$ext;

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
     * Files found this way get no owner. Whoever opened the library did not upload
     * them, and account erasure deletes the unused files an owner leaves behind.
     *
     * Returns the number of newly indexed files.
     */
    public function backfill(BlogResource $blog): int
    {
        $added = 0;

        foreach ($this->uploads->blogUploads($blog->mediaKey()) as $url) {
            $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }

            if ($this->register($blog, null, $url, 'backfill') !== null) {
                $added++;
            }
        }

        return $added;
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
        $info = @getimagesize($absPath);
        if ($info !== false) {
            $width = (int) $info[0];
            $height = (int) $info[1];
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
     * Strip leading slash so a URL like /uploads/blogs/{key}/2026/10/foo.jpg
     * becomes a path relative to /storage.
     *
     * Returns null for URLs that don't look like our uploads (so we
     * never accidentally index something we shouldn't).
     */
    private function urlToDiskPath(string $url): ?string
    {
        if (!UploadService::isUploadUrl($url)) {
            return null;
        }

        return ltrim($url, '/');
    }
}
