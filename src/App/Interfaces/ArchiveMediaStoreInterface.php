<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Resources\BlogResource;

/**
 * Stores one image extracted from a blog archive into a blog's media library.
 */
interface ArchiveMediaStoreInterface
{
    /**
     * @param  string  $path  Extracted file, inside $sourceRoot
     * @param  string  $sourceRoot  The folder the caller extracted into
     * @param  string  $name  File name to store under, with the extension the archive gave it
     * @param  string|null  $alt  Alt text to record for the image
     * @return string Public URL of the stored image
     *
     * @throws \InvalidArgumentException When the file is not an acceptable image
     * @throws \RuntimeException When it could not be stored
     */
    public function store(string $path, string $sourceRoot, string $name, ?string $alt, BlogResource $blog, int $userId): string;
}
