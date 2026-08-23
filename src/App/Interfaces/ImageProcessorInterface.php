<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * ImageProcessorInterface
 *
 * Contract for processing uploaded images: resizing within bounds,
 * re-encoding (which also strips EXIF/embedded payloads), and optional
 * format normalisation. Kept behind an interface so UploadService can be
 * unit tested with a no-op double and so the driver stays swappable.
 */
interface ImageProcessorInterface
{
    /**
     * Read an image, resize it within the given bounds without upscaling,
     * re-encode it and write the result to $destPath.
     *
     * Re-encoding is deliberate: the written file is a freshly rendered image,
     * so any EXIF/ICC/comment payload from the original upload is dropped.
     *
     * @param  string  $sourcePath  Absolute path to the source (usually the upload temp file)
     * @param  string  $destPath  Absolute path to write the processed image to
     * @param  array<string, mixed>  $opts  max_w, max_h, quality, format ('jpg'|'png'|'webp'|null to keep)
     * @return array{width: int, height: int} Final dimensions of the written image
     */
    public function process(string $sourcePath, string $destPath, array $opts = []): array;

    /**
     * Crop a rectangle out of the source, then resize the result to the target box,
     * re-encoding to $destPath. Used for avatar cropping and the media editor: the
     * rectangle is expressed in the source image's own pixels so the same numbers a
     * browser cropper reports can be applied server-side.
     *
     * @param  string  $sourcePath  Absolute path to the source image
     * @param  string  $destPath  Absolute path to write the cropped image to
     * @param  array{x: int, y: int, width: int, height: int}  $rect  Crop rectangle in source pixels
     * @param  array<string, mixed>  $opts  out_w, out_h, quality, format ('jpg'|'png'|'webp'|null to keep)
     * @return array{width: int, height: int} Final dimensions of the written image
     */
    public function crop(string $sourcePath, string $destPath, array $rect, array $opts = []): array;
}
