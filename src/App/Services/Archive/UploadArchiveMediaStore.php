<?php

declare(strict_types=1);

namespace App\Services\Archive;

use App\Interfaces\ArchiveMediaStoreInterface;
use App\Models\MediaModel;
use App\Resources\BlogResource;
use App\Services\MediaService;
use App\Services\UploadService;

/**
 * Stores archive images the way every other upload is stored: checked, resized and
 * re-encoded by UploadService, under the blog owner's media folder, and indexed in
 * the blog's library. Content-hash file names make a repeated import a no-op.
 */
final class UploadArchiveMediaStore implements ArchiveMediaStoreInterface
{
    public function __construct(
        private UploadService $uploads,
        private MediaService $media,
        private MediaModel $mediaRows,
    ) {}

    public function store(string $path, string $sourceRoot, string $name, ?string $alt, BlogResource $blog, int $userId): string
    {
        $blogId = (int) $blog->id();
        $baseName = pathinfo($name, PATHINFO_FILENAME) ?: 'image';
        [$dir, $baseUrl] = $this->media->blogMediaPath($blog->ownerId(), $blogId);

        $url = $this->uploads->storeImageFromPath($path, $name, [
            'source_root' => $sourceRoot,
            'dir' => $dir,
            'base_url' => $baseUrl,
            'allowed_ext' => ArchiveFormat::IMAGE_EXTENSIONS,
            'max_bytes' => ArchiveFormat::MAX_IMAGE_BYTES,
            'preset' => 'media',
            'rename' => $baseName,
        ]);

        $row = $this->media->register($blogId, $userId, $url, 'upload', $name);
        if ($row !== null && $alt !== null) {
            $this->mediaRows->updateForBlog((int) $row['id'], $blogId, ['alt_text' => $alt]);
        }

        return $url;
    }
}
