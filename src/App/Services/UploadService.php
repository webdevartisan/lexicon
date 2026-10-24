<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\ImageProcessorInterface;
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
     * Named processing presets keyed by upload intent. Callers pass a preset name
     * instead of raw pixel numbers so sizing policy for each surface lives in one
     * place. scaleDown never upscales, so a smaller source is left as-is.
     *
     * @var array<string, array{max_w: int, max_h: int, quality: int}>
     */
    private const PROCESS_PRESETS = [
        'avatar' => ['max_w' => 512, 'max_h' => 512, 'quality' => 85],
        'avatar_source' => ['max_w' => 1024, 'max_h' => 1024, 'quality' => 88],
        'post_inline' => ['max_w' => 1600, 'max_h' => 1600, 'quality' => 82],
        'post_featured' => ['max_w' => 1920, 'max_h' => 1920, 'quality' => 82],
        'media' => ['max_w' => 2048, 'max_h' => 2048, 'quality' => 82],
        'banner' => ['max_w' => 2560, 'max_h' => 1440, 'quality' => 82],
        'logo' => ['max_w' => 800, 'max_h' => 800, 'quality' => 90],
        'favicon' => ['max_w' => 256, 'max_h' => 256, 'quality' => 90],
    ];

    private const BLOGS_URL = '/uploads/blogs';

    private const AVATARS_URL = '/uploads/avatars';

    private const MEDIA_KEY_PATTERN = '/^[0-9a-f]{16}$/';

    /**
     * @param  ImageProcessorInterface  $images  Resizes and re-encodes every stored upload
     */
    public function __construct(private readonly ImageProcessorInterface $images) {}

    /**
     * Store uploaded image with security validation.
     *
     * Validates file type via extension and MIME sniffing, enforces size limits,
     * generates content-based hash for deduplication, and sanitizes filenames.
     *
     * @param  array<string, mixed>  $file  PHP $_FILES array entry
     * @param  array<string, mixed>  $opts  Configuration: dir, base_url, max_bytes, allowed_ext, rename
     * @return string|null Public URL of stored file, or null on failure
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function storeImage(array $file, array $opts): ?string
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload error.');
        }

        $name = (string) ($file['name'] ?? '');
        $ext = $this->assertAllowedImage($file['tmp_name'], $name, (int) ($file['size'] ?? 0), $opts);

        $target = $this->prepareTarget($opts);
        if ($target === null) {
            return null;
        }
        $filename = $this->hashedFilename($file['tmp_name'], $name, $ext, $opts);

        // The file is processed in place rather than through move_uploaded_file(), so
        // check it is a genuine HTTP upload or a caller could point us at any server path.
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Upload error.');
        }

        // Resize and re-encode straight to the destination. The raw upload never lands
        // on disk, and the re-encode strips any EXIF/embedded payload the source carried.
        try {
            $this->images->process($file['tmp_name'], $target[0].'/'.$filename, $this->resolveProcessOptions($opts));
        } catch (\Throwable $e) {
            error_log('Image processing failed: '.$e->getMessage());

            return null;
        }

        return $target[1].'/'.$filename;
    }

    /**
     * Store an image that is already on disk, such as one extracted from an archive,
     * through the same checks and re-encode as an HTTP upload.
     *
     * The source has to sit inside $opts['source_root'], the folder the caller
     * extracted into itself, so this can never be pointed at an arbitrary server path.
     *
     * @param  string  $path  Absolute path of the file to store
     * @param  string  $originalName  Name the file is known by, which decides the extension
     * @param  array<string, mixed>  $opts  Same options as storeImage(), plus the required source_root
     * @return string Public URL of the stored file
     *
     * @throws InvalidArgumentException When the file is outside source_root or fails validation
     * @throws RuntimeException When the target is not configured or the re-encode fails
     */
    public function storeImageFromPath(string $path, string $originalName, array $opts): string
    {
        $root = realpath((string) ($opts['source_root'] ?? ''));
        $real = realpath($path);
        if ($root === false || $real === false || !is_file($real)
            || !str_starts_with($real, rtrim($root, '/\\').DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('File is outside the allowed source folder.');
        }

        $ext = $this->assertAllowedImage($real, $originalName, (int) filesize($real), $opts);

        $target = $this->prepareTarget($opts);
        if ($target === null) {
            throw new RuntimeException('No upload target was given.');
        }
        $filename = $this->hashedFilename($real, $originalName, $ext, $opts);

        $this->images->process($real, $target[0].'/'.$filename, $this->resolveProcessOptions($opts));

        return $target[1].'/'.$filename;
    }

    /**
     * Size, extension and content checks shared by every way an image is stored.
     *
     * @param  array<string, mixed>  $opts  max_bytes and allowed_ext
     * @return string The lower-cased extension
     *
     * @throws InvalidArgumentException When any check fails
     */
    private function assertAllowedImage(string $path, string $name, int $size, array $opts): string
    {
        $maxBytes = (int) ($opts['max_bytes'] ?? 2 * 1024 * 1024);
        if ($size > $maxBytes) {
            throw new InvalidArgumentException('File size exceeds the allowed range.');
        }

        $allowedExt = $opts['allowed_ext'] ?? ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new InvalidArgumentException('File extension not allowed.');
        }

        // allowed_ext comes from the caller, so the sniffed type is the real gate.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidArgumentException('File type not allowed.');
        }

        return $ext;
    }

    /**
     * The target folder and public base URL from the options, with the folder created.
     *
     * @param  array<string, mixed>  $opts  dir and base_url
     * @return array{0: string, 1: string}|null Null when either is missing
     */
    private function prepareTarget(array $opts): ?array
    {
        $targetDir = rtrim((string) ($opts['dir'] ?? ''), '/');
        $baseUrl = rtrim((string) ($opts['base_url'] ?? ''), '/');
        if ($targetDir === '' || $baseUrl === '') {
            return null;
        }
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        return [$targetDir, $baseUrl];
    }

    /**
     * A filename made from the source bytes, so the same image always lands under the
     * same name and a replaced one always gets a new URL.
     *
     * @param  array<string, mixed>  $opts  Optional rename
     */
    private function hashedFilename(string $path, string $name, string $ext, array $opts): string
    {
        $hash = substr(sha1_file($path) ?: bin2hex(random_bytes(8)), 0, 12);
        $baseName = (string) ($opts['rename'] ?? (pathinfo($name !== '' ? $name : 'file', PATHINFO_FILENAME)));
        $safeBase = preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $baseName) ?: 'file';

        return $safeBase.'-'.$hash.'.'.$ext;
    }

    /**
     * Resolve the processing options for a stored upload. An explicit 'process'
     * array wins; otherwise a named 'preset' expands to its settings. Falls back
     * to the processor's own defaults when neither is given.
     *
     * @param  array<string, mixed>  $opts  The storeImage options
     * @return array<string, mixed> Options passed straight to the image processor
     */
    private function resolveProcessOptions(array $opts): array
    {
        if (!empty($opts['process']) && is_array($opts['process'])) {
            return $opts['process'];
        }

        $preset = $opts['preset'] ?? null;

        return $preset !== null ? (self::PROCESS_PRESETS[$preset] ?? []) : [];
    }

    /**
     * Folder and public URL for a blog's new images, one subfolder per month.
     *
     * @param  string  $mediaKey  The blog's media_key
     * @return array{0: string, 1: string} [directory_path, url_base]
     */
    public function blogUploadPath(string $mediaKey): array
    {
        $month = date('Y/m');

        return [$this->blogRoot($mediaKey).'/'.$month, self::BLOGS_URL.'/'.$mediaKey.'/'.$month];
    }

    /**
     * Public URLs of every file stored for a blog.
     *
     * @param  string  $mediaKey  The blog's media_key
     * @return list<string>
     */
    public function blogUploads(string $mediaKey): array
    {
        $root = $this->blogRoot($mediaKey);
        if (!is_dir($root)) {
            return [];
        }

        $urls = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->isFile()) {
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                $urls[] = self::BLOGS_URL.'/'.$mediaKey.'/'.$relative;
            }
        }

        return $urls;
    }

    /**
     * Whether a public URL names a file in the folder of the blog with this media key.
     */
    public static function isBlogFile(string $url, string $mediaKey): bool
    {
        return preg_match(self::MEDIA_KEY_PATTERN, $mediaKey) === 1
            && str_starts_with($url, self::BLOGS_URL.'/'.$mediaKey.'/')
            && !str_contains($url, '..');
    }

    /**
     * Folder holding everything stored for a blog.
     *
     * @throws InvalidArgumentException When the key is malformed, so an empty or crafted
     *                                  value can never point at the folder of all blogs
     */
    private function blogRoot(string $mediaKey): string
    {
        if (preg_match(self::MEDIA_KEY_PATTERN, $mediaKey) !== 1) {
            throw new InvalidArgumentException('Not a blog media key.');
        }

        return $this->pathOf(self::BLOGS_URL.'/'.$mediaKey);
    }

    /**
     * Store an uploaded avatar as the re-crop source: sanitised, EXIF-stripped and
     * capped, but not yet cropped. The returned path is what the cropper reads and
     * what renderAvatar() later crops, so its pixels match the coordinates the
     * browser reports.
     *
     * @param  array<string, mixed>  $file  PHP $_FILES array entry
     * @return array{url: string, path: string, width: int, height: int}
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function storeAvatarSource(array $file): array
    {
        [$dir, $baseUrl] = $this->avatarPath();

        $url = $this->storeImage($file, [
            'dir' => $dir,
            'base_url' => $baseUrl,
            'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_bytes' => 20 * 1024 * 1024,
            'preset' => 'avatar_source',
            // Everyone's avatars share one folder, so a content-only name would let two
            // people who upload the same picture share, and delete, each other's file.
            'rename' => 'avatar-source-'.bin2hex(random_bytes(4)),
        ]);

        if ($url === null) {
            throw new RuntimeException('Avatar upload failed.');
        }

        $path = $dir.'/'.basename($url);
        $size = getimagesize($path) ?: [0, 0];

        return ['url' => $url, 'path' => $path, 'width' => (int) $size[0], 'height' => (int) $size[1]];
    }

    /**
     * Render the square avatar by cropping the stored source to the given rectangle.
     * Each render gets a fresh content-hashed filename so browser caches pick up the
     * change; the caller is responsible for deleting the previous avatar file.
     *
     * @param  array{x: int, y: int, width: int, height: int}  $rect  Crop rect in source pixels
     * @return string Public URL of the rendered avatar
     */
    public function renderAvatar(string $sourcePath, array $rect): string
    {
        [$dir, $baseUrl] = $this->avatarPath();

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $hash = substr(sha1(json_encode($rect).'|'.basename($sourcePath).'|'.microtime()), 0, 12);
        $filename = 'avatar-'.$hash.'.jpg';

        $this->images->crop($sourcePath, $dir.'/'.$filename, $rect, [
            'out_w' => 512,
            'out_h' => 512,
            'quality' => 85,
            'format' => 'jpeg',
        ]);

        return $baseUrl.'/'.$filename;
    }

    /**
     * Folder and public URL for new avatars, one subfolder per month. The profile row
     * records each file's URL, so nothing about the person goes into the path.
     *
     * @return array{0: string, 1: string} [directory_path, url_base]
     */
    private function avatarPath(): array
    {
        $url = self::AVATARS_URL.'/'.date('Y/m');

        return [$this->pathOf($url), $url];
    }

    /**
     * Absolute path of an avatar file, or null when the URL is not in the avatars folder.
     * Profile rows go through this, so they can never point the app at a blog's image.
     */
    public function avatarFilePath(string $url): ?string
    {
        if (!str_starts_with($url, self::AVATARS_URL.'/') || str_contains($url, '..')) {
            return null;
        }

        return $this->pathOf($url);
    }

    /**
     * Absolute path of a stored upload, from its public URL.
     *
     * @throws InvalidArgumentException When the URL is not a local upload
     */
    public function pathOf(string $url): string
    {
        if (!str_starts_with($url, '/uploads/') || str_contains($url, '..')) {
            throw new InvalidArgumentException("Not a stored upload: {$url}");
        }

        return ROOT_PATH.'/storage'.$url;
    }

    /**
     * Get upload directory and URL for admin-managed static page thumbnails.
     *
     * @return array{0: string, 1: string} [directory_path, url_base]
     */
    public function pageThumbnailPath(): array
    {
        $dir = ROOT_PATH.'/storage/uploads/pages/thumbnails';
        $url = '/uploads/pages/thumbnails';

        return [$dir, $url];
    }

    /**
     * Move a temporary upload to the page thumbnails folder with semantic naming.
     *
     * @param  string  $tempFilename  Filename in temp directory
     * @param  int  $userId  User ID (temp directory isolation only, pages aren't user-owned)
     * @return string Public URL of moved file
     *
     * @throws InvalidArgumentException If temp file not found
     * @throws RuntimeException If copy operation fails
     */
    public function moveTempToPageThumbnail(string $tempFilename, int $userId): string
    {
        $tempPath = $this->tempFilePath($tempFilename, $userId);

        $ext = strtolower(pathinfo($tempFilename, PATHINFO_EXTENSION));
        $hash = substr(sha1_file($tempPath), 0, 12);
        $newFilename = 'thumbnail-'.$hash.'.'.$ext;

        [$dir, $baseUrl] = $this->pageThumbnailPath();

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $destPath = $dir.'/'.$newFilename;

        if (!copy($tempPath, $destPath)) {
            throw new RuntimeException('Failed to copy file to folder');
        }

        return $baseUrl.'/'.$newFilename;
    }

    /**
     * Store uploaded file in temporary location before form submission.
     *
     * Used for AJAX uploads where final destination depends on form completion.
     * Files remain in temp until moved to permanent location or garbage collected.
     *
     * @param  array<string, mixed>  $file  PHP $_FILES array entry
     * @param  int  $userId  User ID for temp directory isolation
     * @return array{url: string, filename: string, size: int} File metadata for client
     *
     * @throws InvalidArgumentException If upload fails
     */
    public function storeTempImage(array $file, int $userId): array
    {
        $tempDir = ROOT_PATH.'/storage/uploads/temp/'.$userId;
        $tempUrl = '/uploads/temp/'.$userId;

        $url = $this->storeImage($file, [
            'dir' => $tempDir,
            'base_url' => $tempUrl,
            'max_bytes' => 10 * 1024 * 1024, // 10MB
            'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp'],
            // Temp uploads are mostly featured images; the 1920 cap is a safe upper
            // bound for branding banners too, and leaves smaller logos/favicons alone.
            'preset' => 'post_featured',
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
     * Parse Dropzone uploaded files JSON response.
     *
     * Handles both single JSON string and array of JSON strings.
     * Extracts file metadata from encoded format.
     *
     * @param  string|string[]  $fieldNames  JSON string or array of JSON strings
     * @return array<mixed> Parsed file data
     */
    public function getUploadedFiles(string|array $fieldNames): array
    {
        $json = $fieldNames;

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
            error_log('JSON decode error: '.json_last_error_msg());
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Move a temp upload into the blog's folder, named after its role and its content.
     * Example output: /uploads/blogs/3f9c2a7d41b08e65/2026/10/banner-db56fa563064.webp
     *
     * @param  string  $tempFilename  Filename in temp directory, as the browser reported it
     * @param  int  $userId  The user who uploaded the temp file (whose temp folder holds it)
     * @param  string  $prefix  Role of the image: 'banner', 'logo', 'favicon' or 'featured_image'
     * @param  string  $mediaKey  The blog's media_key
     * @return string Public URL of moved file
     *
     * @throws InvalidArgumentException If the filename is not a temp image name or the file is gone
     * @throws RuntimeException If copy operation fails
     */
    public function moveTempToBlog(string $tempFilename, int $userId, string $prefix, string $mediaKey): string
    {
        [$dir, $baseUrl] = $this->blogUploadPath($mediaKey);
        $tempPath = $this->tempFilePath($tempFilename, $userId);

        $ext = strtolower(pathinfo($tempFilename, PATHINFO_EXTENSION));

        // Generate content-based hash for cache busting
        $hash = substr(sha1_file($tempPath), 0, 12);

        $newFilename = $prefix.'-'.$hash.'.'.$ext;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $destPath = $dir.'/'.$newFilename;

        // Branding assets each get their own size (a favicon must not stay banner-sized),
        // so reprocess to the matching preset here where the intent is known. Flows with
        // no branding preset (for example featured_image, already sized at temp) are a
        // straight copy so we don't re-encode an image twice.
        $preset = self::PROCESS_PRESETS[$prefix] ?? null;
        if ($preset !== null) {
            $this->images->process($tempPath, $destPath, $preset);

            return $baseUrl.'/'.$newFilename;
        }

        if (!copy($tempPath, $destPath)) {
            throw new RuntimeException('Failed to copy file to folder');
        }

        return $baseUrl.'/'.$newFilename;
    }

    /**
     * Delete all temporary files for a user.
     *
     * Remove entire temp directory to clean up abandoned uploads.
     * Safe to call even if directory doesn't exist.
     *
     * @param  int  $userId  User ID
     */
    public function cleanupTempFiles(int $userId): void
    {
        $folderPath = ROOT_PATH.'/storage/uploads/temp/'.$userId;

        if (is_dir($folderPath)) {
            $this->deleteDirectory($folderPath);
        }
    }

    /**
     * Delete a person's own files: their avatar images and abandoned temp uploads.
     *
     * Blog files are not here even when this person uploaded them. They belong to
     * the blog, and a blog that outlives the account still shows them.
     *
     * @param  int  $userId  User ID, which names their temp folder
     * @param  list<string>  $avatarUrls  Public URLs of their avatar and its crop source
     */
    public function deleteProfileUploads(int $userId, array $avatarUrls): void
    {
        foreach ($avatarUrls as $url) {
            $this->deleteAvatar($url);
        }

        $this->cleanupTempFiles($userId);
    }

    /**
     * Delete an avatar file by its URL. A URL outside the avatars folder is left alone.
     *
     * @throws RuntimeException When the file exists but could not be removed
     */
    public function deleteAvatar(string $url): void
    {
        if ($this->avatarFilePath($url) !== null) {
            $this->deleteUpload($url);
        }
    }

    /**
     * Delete every file stored for a blog, whoever uploaded it.
     *
     * @param  string  $mediaKey  The blog's media_key
     */
    public function deleteBlogUploads(string $mediaKey): void
    {
        $this->deleteDirectory($this->blogRoot($mediaKey));
    }

    /**
     * Delete one stored upload by its public URL. A file that is already gone is fine.
     *
     * @throws InvalidArgumentException When the URL is not a local upload
     * @throws RuntimeException When the file exists but could not be removed
     */
    public function deleteUpload(string $url): void
    {
        $path = $this->pathOf($url);

        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException("Could not delete file {$path}.");
        }
    }

    /**
     * Recursively delete a directory and everything in it. Missing is fine.
     *
     * @param  string  $dir  Directory path to delete
     *
     * @throws RuntimeException If any file or folder could not be removed
     */
    public function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            throw new RuntimeException("Could not read directory {$dir}.");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (!unlink($path)) {
                throw new RuntimeException("Could not delete file {$path}.");
            }
        }

        if (!rmdir($dir)) {
            throw new RuntimeException("Could not delete directory {$dir}.");
        }
    }

    /**
     * Resolve a browser-supplied temp filename to its file in the uploader's temp folder.
     *
     * The name comes from the posted form, so anything other than a bare image
     * filename is refused. Otherwise "../../.env" would be copied into a public folder.
     *
     * @throws InvalidArgumentException If the name is not a temp image name or the file is gone
     */
    private function tempFilePath(string $tempFilename, int $userId): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.(jpe?g|png|webp)$/i', $tempFilename) !== 1) {
            throw new InvalidArgumentException('That upload reference is not valid. Please upload the image again.');
        }

        $tempPath = ROOT_PATH.'/storage/uploads/temp/'.$userId.'/'.$tempFilename;

        if (!file_exists($tempPath)) {
            throw new InvalidArgumentException('The uploaded image has expired. Please upload it again.');
        }

        return $tempPath;
    }
}
