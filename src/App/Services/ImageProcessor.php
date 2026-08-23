<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\ImageProcessorInterface;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;

/**
 * Resize, re-encode and normalise uploaded images with Intervention Image.
 *
 * This is the single seam where a raw upload becomes a safe stored file. It is
 * driven by UploadService rather than called from controllers directly, so every
 * upload path gets the same treatment without anyone having to remember to ask.
 */
final class ImageProcessor implements ImageProcessorInterface
{
    private ImageManager $manager;

    /**
     * Largest source we will decode, in pixels (width * height). Anything bigger
     * is rejected before decoding so a small "pixel bomb" file cannot exhaust
     * memory by expanding to a huge bitmap.
     */
    private int $maxPixels;

    /**
     * @param  array<string, mixed>  $config  driver ('gd'|'imagick'), max_pixels
     */
    public function __construct(array $config = [])
    {
        $this->manager = new ImageManager($this->resolveDriver($config['driver'] ?? 'gd'));
        $this->maxPixels = (int) ($config['max_pixels'] ?? 40_000_000); // ~40 MP
    }

    /**
     * {@inheritDoc}
     */
    public function process(string $sourcePath, string $destPath, array $opts = []): array
    {
        $this->guardDimensions($sourcePath);

        $maxW = (int) ($opts['max_w'] ?? 1600);
        $maxH = (int) ($opts['max_h'] ?? 1600);
        $quality = (int) ($opts['quality'] ?? 82);
        $format = $opts['format'] ?? null;

        // read() auto-orients from EXIF in v3; scaleDown never upscales and keeps aspect
        $image = $this->manager->read($sourcePath)->scaleDown($maxW, $maxH);

        $encoded = $format !== null
            ? $this->encodeAs($image, (string) $format, $quality)
            : $image->encodeByPath($destPath, quality: $quality);

        $encoded->save($destPath);

        return ['width' => $image->width(), 'height' => $image->height()];
    }

    /**
     * {@inheritDoc}
     */
    public function crop(string $sourcePath, string $destPath, array $rect, array $opts = []): array
    {
        $this->guardDimensions($sourcePath);

        $outW = (int) ($opts['out_w'] ?? 512);
        $outH = (int) ($opts['out_h'] ?? 512);
        $quality = (int) ($opts['quality'] ?? 85);
        $format = $opts['format'] ?? null;

        $image = $this->manager->read($sourcePath);
        [$x, $y, $w, $h] = $this->clampRect($rect, $image->width(), $image->height());

        // crop the chosen rectangle, then cover() resizes it to the exact target box
        // without distortion (a 1:1 rect into a square box is a plain resize)
        $image->crop($w, $h, $x, $y)->cover($outW, $outH);

        $encoded = $format !== null
            ? $this->encodeAs($image, (string) $format, $quality)
            : $image->encodeByPath($destPath, quality: $quality);

        $encoded->save($destPath);

        return ['width' => $image->width(), 'height' => $image->height()];
    }

    /**
     * Clamp a requested crop rectangle to the image bounds so a stale or hand-crafted
     * coordinate set can never read outside the bitmap. Rejects a rectangle that has
     * no area left once clamped.
     *
     * @param  array{x?: int|float, y?: int|float, width?: int|float, height?: int|float}  $rect
     * @return array{0: int, 1: int, 2: int, 3: int} [x, y, width, height]
     */
    private function clampRect(array $rect, int $imgW, int $imgH): array
    {
        $x = max(0, min((int) ($rect['x'] ?? 0), $imgW - 1));
        $y = max(0, min((int) ($rect['y'] ?? 0), $imgH - 1));
        $w = (int) ($rect['width'] ?? 0);
        $h = (int) ($rect['height'] ?? 0);

        $w = min($w > 0 ? $w : $imgW, $imgW - $x);
        $h = min($h > 0 ? $h : $imgH, $imgH - $y);

        if ($w < 1 || $h < 1) {
            throw new InvalidArgumentException('Invalid crop region.');
        }

        return [$x, $y, $w, $h];
    }

    /**
     * Reject images whose pixel count is over the cap before we hand the file to
     * the decoder. getimagesize() only reads the header, so this stays cheap.
     */
    private function guardDimensions(string $sourcePath): void
    {
        $size = @getimagesize($sourcePath);
        if ($size === false) {
            throw new InvalidArgumentException('Unreadable or unsupported image.');
        }

        if (($size[0] * $size[1]) > $this->maxPixels) {
            throw new InvalidArgumentException('Image dimensions exceed the allowed range.');
        }
    }

    /**
     * Encode to an explicit target format, used when a caller wants to normalise
     * (for example forcing avatars to webp regardless of what was uploaded).
     */
    private function encodeAs(ImageInterface $image, string $format, int $quality): \Intervention\Image\Interfaces\EncodedImageInterface
    {
        return match (strtolower($format)) {
            'jpg', 'jpeg' => $image->toJpeg($quality),
            'webp' => $image->toWebp($quality),
            'png' => $image->toPng(),
            default => throw new InvalidArgumentException("Unsupported target format: {$format}"),
        };
    }

    /**
     * Prefer Imagick when it is both requested and actually loaded; otherwise fall
     * back to GD so the service still works on hosts without the Imagick extension.
     */
    private function resolveDriver(string $driver): GdDriver|ImagickDriver
    {
        if ($driver === 'imagick' && extension_loaded('imagick')) {
            return new ImagickDriver();
        }

        return new GdDriver();
    }
}
