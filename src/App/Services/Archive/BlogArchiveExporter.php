<?php

declare(strict_types=1);

namespace App\Services\Archive;

use App\Models\MediaModel;
use App\Models\PostArchiveModel;
use App\Resources\BlogResource;
use App\Services\UploadService;
use Framework\Modules\ModuleDuties;
use RuntimeException;
use ZipArchive;

/**
 * Builds a blog's archive: its posts with their category and tags, and the files they use.
 *
 * Knows nothing about HTTP, so a background worker can call it as it is. Posts are
 * read a page at a time and written to a temp file, and media is added from disk,
 * so memory stays flat however large the blog is.
 */
final class BlogArchiveExporter
{
    private const PAGE_SIZE = 100;

    private ZipArchive $zip;

    /** @var array<string, string> Canonical upload URL => archive path */
    private array $tokens = [];

    /** @var array<string, array<string, mixed>> Archive path => media.json entry */
    private array $mediaEntries = [];

    /** @var list<array{type: string, post: string, url: string}> */
    private array $warnings = [];

    /** @var array<string, true> */
    private array $warned = [];

    private int $bytes = 0;

    public function __construct(
        private PostArchiveModel $posts,
        private MediaModel $media,
        private ArchiveMediaUrls $urls,
        private ModuleDuties $moduleDuties,
        private string $storageRoot = ROOT_PATH.'/storage',
    ) {}

    /**
     * Write the archive for $blog to $destPath.
     *
     * @param  array{include_drafts?: bool}  $options  include_drafts defaults to true
     * @param  callable(string, int): void|null  $progress  Called with a stage name and a running count
     * @return array{posts: int, media: int, warnings: list<array{type: string, post: string, url: string}>, skipped_moderated: int}
     *
     * @throws ArchiveException When the blog is larger than an archive may be
     */
    public function export(BlogResource $blog, string $destPath, array $options = [], ?callable $progress = null): array
    {
        $this->reset();
        $blogId = (int) $blog->id();
        $postsFile = $destPath.'.posts.json';

        $this->zip = new ZipArchive();
        if ($this->zip->open($destPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create archive at {$destPath}");
        }

        try {
            $count = $this->writePosts($blog, $postsFile, $options['include_drafts'] ?? true, $progress);
            $moderated = $this->posts->moderatedInBlog($blogId);

            $this->zip->addFile($postsFile, ArchiveFormat::POSTS);
            $this->addJson(ArchiveFormat::MEDIA, array_values($this->mediaEntries));
            $modules = $this->writeModules($blogId);
            $this->addJson(ArchiveFormat::MANIFEST, $this->manifest($blog, $count, $moderated, $modules));

            if (!$this->zip->close()) {
                throw new RuntimeException('Could not finish writing the archive: '.$this->zip->getStatusString());
            }
        } catch (\Throwable $e) {
            @$this->zip->close();
            @unlink($destPath);

            throw $e;
        } finally {
            @unlink($postsFile);
        }

        $this->assertWithinCap($destPath);

        return [
            'posts' => $count,
            'media' => count($this->mediaEntries),
            'warnings' => $this->warnings,
            'skipped_moderated' => count($moderated),
        ];
    }

    private function reset(): void
    {
        $this->tokens = [];
        $this->mediaEntries = [];
        $this->warnings = [];
        $this->warned = [];
        $this->bytes = 0;
    }

    /**
     * Stream every exportable post into a JSON array file, one page at a time.
     *
     * @param  callable(string, int): void|null  $progress
     * @return int Number of posts written
     */
    private function writePosts(BlogResource $blog, string $file, bool $includeDrafts, ?callable $progress): int
    {
        $handle = fopen($file, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Could not open {$file} for writing");
        }

        $count = 0;
        $afterId = 0;

        try {
            fwrite($handle, '[');
            while (($rows = $this->posts->exportPage($blog->id(), $afterId, self::PAGE_SIZE, $includeDrafts)) !== []) {
                $tags = $this->posts->tagsForPosts(array_map(static fn (array $r): int => (int) $r['id'], $rows));

                foreach ($rows as $row) {
                    $json = json_encode($this->record($row, $tags[(int) $row['id']] ?? [], $blog), $this->jsonFlags());
                    fwrite($handle, ($count > 0 ? ',' : '')."\n".$json);
                    $this->bytes += strlen($json);
                    $this->assertWithinLimits(++$count);
                }

                $afterId = (int) end($rows)['id'];
                if ($progress !== null) {
                    $progress('posts', $count);
                }
            }
            fwrite($handle, "\n]\n");
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /**
     * One post as the archive carries it: an archive id instead of database links, local media as archive paths.
     *
     * @param  array<string, mixed>  $row
     * @param  list<array{name: string, slug: string}>  $tags
     * @return array<string, mixed>
     */
    private function record(array $row, array $tags, BlogResource $blog): array
    {
        $slug = (string) $row['slug'];
        $post = [];
        foreach (ArchiveFormat::POST_FIELDS as $field) {
            $post[$field] = $row[$field] ?? null;
        }

        foreach (['comments_enabled', 'meta_noindex', 'meta_nofollow'] as $flag) {
            $post[$flag] = (bool) $post[$flag];
        }

        if ($post['published_at'] !== null) {
            $post['published_at'] = (new \DateTimeImmutable((string) $post['published_at'], new \DateTimeZone('UTC')))
                ->format('Y-m-d\TH:i:s\Z');
        }

        $toToken = fn (string $value): ?string => $this->tokenFor($value, $blog, $slug);
        $post['content'] = $this->urls->rewrite((string) $post['content'], $toToken);
        foreach (ArchiveFormat::IMAGE_FIELDS as $field) {
            if (is_string($post[$field]) && $post[$field] !== '') {
                $post[$field] = $toToken($post[$field]) ?? $post[$field];
            }
        }

        $post['category'] = $row['category_slug'] !== null
            ? ['name' => (string) $row['category_name'], 'slug' => (string) $row['category_slug']]
            : null;
        $post['tags'] = $tags;
        $post['author_name'] = (string) ($row['author_name'] ?? '');
        // Only a reference inside this archive, so module rows can find their post again on restore
        $post['archive_id'] = (int) $row['id'];

        return $post;
    }

    /**
     * The archive path for a local upload of this blog, adding the file on first sight.
     * Returns null, so the reference is kept as it is, for anything that is not one.
     */
    private function tokenFor(string $value, BlogResource $blog, string $postSlug): ?string
    {
        $url = $this->urls->canonical($value);
        if ($url === null) {
            return null;
        }
        if (isset($this->tokens[$url])) {
            return $this->tokens[$url];
        }

        if (!UploadService::isBlogFile($url, $blog->mediaKey())) {
            $this->warn('foreign_file', $postSlug, $url);

            return null;
        }

        $disk = $this->diskPath($url);
        if ($disk === null) {
            $this->warn('missing_file', $postSlug, $url);

            return null;
        }

        return $this->tokens[$url] = $this->addMedia($url, $disk, $blog->id());
    }

    /**
     * Absolute path of an upload, or null when it is missing or resolves outside the uploads folder.
     */
    private function diskPath(string $url): ?string
    {
        $real = realpath($this->storageRoot.$url);
        $uploads = realpath($this->storageRoot.'/uploads');

        if ($real === false || $uploads === false || !is_file($real)
            || !str_starts_with($real, $uploads.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    private function addMedia(string $url, string $disk, int $blogId): string
    {
        $ext = strtolower(pathinfo($disk, PATHINFO_EXTENSION));
        $path = 'media/'.substr((string) sha1_file($disk), 0, 12).'.'.$ext;

        if (!isset($this->mediaEntries[$path])) {
            $row = $this->media->findByPathForBlog($blogId, ltrim($url, '/'));
            $size = (int) filesize($disk);

            $this->mediaEntries[$path] = [
                'path' => $path,
                'original_name' => (string) ($row['original_name'] ?? basename($disk)),
                'alt' => $row['alt_text'] ?? null,
                'mime_type' => $row['mime_type'] ?? null,
                'size' => $size,
            ];

            // Images are already compressed, so deflating them again only costs time.
            $this->zip->addFile($disk, $path);
            $this->zip->setCompressionName($path, ZipArchive::CM_STORE);
            $this->bytes += $size;
            $this->assertWithinLimits(0);
        }

        return $path;
    }

    private function warn(string $type, string $postSlug, string $url): void
    {
        if (isset($this->warned[$type.$url])) {
            return;
        }

        $this->warned[$type.$url] = true;
        $this->warnings[] = ['type' => $type, 'post' => $postSlug, 'url' => $url];
    }

    /**
     * @param  list<array{title: string, slug: string}>  $moderated
     * @param  list<string>  $modules  Modules whose rows sit under modules/
     * @return array<string, mixed>
     */
    private function manifest(BlogResource $blog, int $count, array $moderated, array $modules): array
    {
        $blogRow = $blog->toArray();

        return [
            'format' => ArchiveFormat::NAME,
            'format_version' => ArchiveFormat::VERSION,
            'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'source' => ['name' => (string) ($blogRow['blog_name'] ?? ''), 'slug' => (string) ($blogRow['blog_slug'] ?? '')],
            'counts' => ['posts' => $count, 'media' => count($this->mediaEntries)],
            'warnings' => $this->warnings,
            'skipped_moderated' => $moderated,
            'modules' => $modules,
        ];
    }

    /**
     * One file per module that holds rows for the blog.
     *
     * @return list<string> Keys of the modules written
     */
    private function writeModules(int $blogId): array
    {
        $modules = $this->moduleDuties->exportBlog($blogId);
        foreach ($modules as $key => $data) {
            $this->addJson(ArchiveFormat::modulePath($key), $data);
        }

        return array_keys($modules);
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function addJson(string $name, array $data): void
    {
        $this->zip->addFromString($name, json_encode($data, $this->jsonFlags() | JSON_PRETTY_PRINT));
    }

    private function jsonFlags(): int
    {
        return JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    }

    /**
     * An archive the importer would refuse is no use as a backup, so stop instead of writing one.
     */
    private function assertWithinLimits(int $postCount): void
    {
        if ($postCount > ArchiveFormat::MAX_POSTS) {
            throw new ArchiveException('This blog has more than '.ArchiveFormat::MAX_POSTS.' posts, the most one archive can hold.', 'archive.errors.blogTooManyPosts', ['count' => ArchiveFormat::MAX_POSTS]);
        }
        if ($this->bytes > ArchiveFormat::MAX_TOTAL_BYTES || count($this->mediaEntries) >= ArchiveFormat::MAX_ENTRIES - 3) {
            throw new ArchiveException('This blog is larger than one archive can hold.', 'archive.errors.blogTooLarge');
        }
    }

    private function assertWithinCap(string $destPath): void
    {
        if ((int) filesize($destPath) > ArchiveFormat::MAX_ARCHIVE_BYTES) {
            @unlink($destPath);

            throw new ArchiveException('This blog is larger than one archive can hold.', 'archive.errors.blogTooLarge');
        }
    }
}
