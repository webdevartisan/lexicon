<?php

declare(strict_types=1);

namespace App\Services\Archive;

use App\Models\PostArchiveModel;
use App\Resources\BlogResource;

/**
 * Opens an archive, checks everything in it, and returns the plan for a target blog.
 *
 * Has no side effects: nothing is extracted to disk and nothing is written to the
 * database. Media is judged from the zip index and the first bytes of each file.
 */
final class BlogArchiveReader
{
    private const MANIFEST_MAX_BYTES = 1024 * 1024;

    private const IMAGE_MIMES = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];

    /** @var array<string, true> Upload URLs the exporter could not find on disk */
    private array $missingAtExport = [];

    public function __construct(
        private PostArchiveModel $posts,
        private ArchivePostValidator $validator,
        private ArchiveMediaUrls $urls,
    ) {}

    /**
     * @throws ArchiveException When the archive as a whole is refused
     */
    public function read(string $zipPath, BlogResource $target): ArchivePlan
    {
        $zip = ArchiveZip::open($zipPath);

        try {
            $source = $this->manifest($zip);
            $media = $this->media($zip);
            $problems = [];
            foreach ($media as $entry) {
                if (!$entry['usable']) {
                    $problems[] = ['type' => 'media_skipped', 'subject' => $entry['original_name'], 'detail' => (string) $entry['reason']];
                }
            }

            $posts = $this->posts($zip, $media, $problems);
            $posts = $this->withClashes($posts, (int) $target->id());
        } finally {
            $zip->close();
        }

        return new ArchivePlan($source, $posts, $media, $problems, $this->missingAtExport);
    }

    /**
     * @return array{name: string, slug: string, exported_at: string}
     *
     * @throws ArchiveException
     */
    private function manifest(ArchiveZip $zip): array
    {
        $manifest = $zip->json(ArchiveFormat::MANIFEST, self::MANIFEST_MAX_BYTES);

        if (!is_array($manifest) || ($manifest['format'] ?? null) !== ArchiveFormat::NAME) {
            throw new ArchiveException('manifest.json does not describe a Lexicon blog archive.', 'archive.errors.noManifest');
        }

        $version = $manifest['format_version'] ?? null;
        if ($version !== ArchiveFormat::VERSION) {
            $shown = is_scalar($version) ? mb_substr((string) $version, 0, 10) : 'unknown';

            throw new ArchiveException(
                "This archive uses format version {$shown}, and this site reads version ".ArchiveFormat::VERSION.' only.',
                'archive.errors.version',
                ['version' => $shown, 'supported' => ArchiveFormat::VERSION]
            );
        }

        $source = is_array($manifest['source'] ?? null) ? $manifest['source'] : [];
        $this->missingAtExport = [];
        foreach (is_array($manifest['warnings'] ?? null) ? $manifest['warnings'] : [] as $warning) {
            if (is_array($warning) && ($warning['type'] ?? null) === 'missing_file' && is_string($warning['url'] ?? null)) {
                $this->missingAtExport[$warning['url']] = true;
            }
        }

        return [
            'name' => is_string($source['name'] ?? null) ? mb_substr($source['name'], 0, 100) : '',
            'slug' => is_string($source['slug'] ?? null) ? mb_substr($source['slug'], 0, 100) : '',
            'exported_at' => is_string($manifest['exported_at'] ?? null) ? mb_substr($manifest['exported_at'], 0, 25) : '',
        ];
    }

    /**
     * Every media.json entry with whether it can be stored, and why not.
     *
     * @return array<string, array{path: string, original_name: string, alt: ?string, usable: bool, reason: ?string}>
     *
     * @throws ArchiveException
     */
    private function media(ArchiveZip $zip): array
    {
        $raw = $zip->has(ArchiveFormat::MEDIA) ? $zip->json(ArchiveFormat::MEDIA) : [];
        if (!is_array($raw) || !array_is_list($raw) || count($raw) > ArchiveFormat::MAX_ENTRIES) {
            throw new ArchiveException('media.json is not a list of files.', 'archive.errors.badJson', ['name' => ArchiveFormat::MEDIA]);
        }

        $media = [];
        foreach ($raw as $item) {
            $path = is_array($item) && is_string($item['path'] ?? null) ? $item['path'] : '';
            if (preg_match(ArchiveFormat::MEDIA_PATH_PATTERN, $path) !== 1 || isset($media[$path])) {
                continue;
            }

            $name = is_string($item['original_name'] ?? null) ? basename(str_replace('\\', '/', $item['original_name'])) : '';
            $alt = is_string($item['alt'] ?? null) ? mb_substr(trim($item['alt']), 0, 255) : null;
            $reason = $this->unusableReason($zip, $path);

            $media[$path] = [
                'path' => $path,
                'original_name' => mb_substr($name !== '' ? $name : basename($path), 0, 255),
                'alt' => $alt !== '' ? $alt : null,
                'usable' => $reason === null,
                'reason' => $reason,
            ];
        }

        return $media;
    }

    private function unusableReason(ArchiveZip $zip, string $path): ?string
    {
        if (!$zip->has($path)) {
            return 'The file is listed but missing from the archive.';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ArchiveFormat::IMAGE_EXTENSIONS, true)) {
            return strtoupper($ext).' files cannot be imported. Only JPG, PNG and WebP are supported.';
        }

        $mime = $zip->sniffMime($path);
        if (!in_array($ext, self::IMAGE_MIMES[$mime] ?? [], true)) {
            return 'The file is named .'.$ext.' but its contents are not that kind of image.';
        }

        return null;
    }

    /**
     * Validate every post and note the media each one refers to that will not arrive.
     *
     * @param  array<string, array{usable: bool, reason: ?string, original_name: string}>  $media
     * @param  list<array{type: string, subject: string, detail: string}>  $problems
     * @return list<array{data: array<string, mixed>, errors: list<string>, clash: null}>
     *
     * @throws ArchiveException
     */
    private function posts(ArchiveZip $zip, array $media, array &$problems): array
    {
        $raw = $zip->json(ArchiveFormat::POSTS);
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new ArchiveException('posts.json is not a list of posts.', 'archive.errors.badJson', ['name' => ArchiveFormat::POSTS]);
        }
        if (count($raw) > ArchiveFormat::MAX_POSTS) {
            throw new ArchiveException('The archive has more than '.ArchiveFormat::MAX_POSTS.' posts.', 'archive.errors.tooManyPosts', ['count' => ArchiveFormat::MAX_POSTS]);
        }

        $posts = [];
        $seen = [];
        foreach ($raw as $i => $item) {
            $result = $this->validator->validate($item);
            $subject = $this->subject($result['data'], $i);
            $slug = $result['data']['slug'] ?? null;

            if ($result['errors'] === [] && isset($seen[$slug])) {
                $result['errors'][] = 'Another post in the archive has the same slug.';
            }
            $seen[$slug] = true;

            if ($result['errors'] !== []) {
                $problems[] = ['type' => 'post_invalid', 'subject' => $subject, 'detail' => implode(' ', $result['errors'])];
            } else {
                $this->noteReferences($result['data'], $subject, $media, $problems);
            }

            $posts[] = $result + ['clash' => null];
        }

        return $posts;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array{usable: bool}>  $media
     * @param  list<array{type: string, subject: string, detail: string}>  $problems
     */
    private function noteReferences(array $data, string $subject, array $media, array &$problems): void
    {
        $references = $this->urls->references((string) $data['content']);
        foreach (ArchiveFormat::IMAGE_FIELDS as $field) {
            if (is_string($data[$field])) {
                $references[] = $data[$field];
            }
        }

        foreach (array_unique($references) as $ref) {
            if (preg_match(ArchiveFormat::MEDIA_PATH_PATTERN, $ref) === 1 && !isset($media[$ref])) {
                $problems[] = ['type' => 'media_missing', 'subject' => $subject, 'detail' => $ref];
            } elseif (preg_match(ArchiveFormat::MEDIA_PATH_PATTERN, $ref) === 1 && !$media[$ref]['usable']) {
                $problems[] = ['type' => 'media_left_out', 'subject' => $subject, 'detail' => $ref];
            } elseif (isset($this->missingAtExport[(string) $this->urls->canonical($ref)])) {
                $problems[] = ['type' => 'media_missing', 'subject' => $subject, 'detail' => $ref];
            } elseif ($this->urls->canonical($ref) !== null) {
                $problems[] = ['type' => 'foreign_upload', 'subject' => $subject, 'detail' => $ref];
            } elseif ($this->urls->isExternal($ref) && $this->isLoadingReference($ref, $data)) {
                $problems[] = ['type' => 'external_media', 'subject' => $subject, 'detail' => $ref];
            }
        }
    }

    /**
     * Links to other sites are ordinary, and share images are fetched by the networks
     * themselves. Only what a reader's browser loads from another site breaks.
     *
     * @param  array<string, mixed>  $data
     */
    private function isLoadingReference(string $ref, array $data): bool
    {
        if ($ref === $data['featured_image']) {
            return true;
        }

        $quoted = preg_quote($ref, '#');

        return preg_match('#<(?:img|source|video|audio|iframe|embed)\b[^>]*["\']'.$quoted.'["\'\s,]#i', (string) $data['content']) === 1;
    }

    /**
     * @param  list<array{data: array<string, mixed>, errors: list<string>, clash: null}>  $posts
     * @return list<array{data: array<string, mixed>, errors: list<string>, clash: array{id: int, status: string, title: string}|null}>
     */
    private function withClashes(array $posts, int $blogId): array
    {
        $slugs = [];
        foreach ($posts as $post) {
            if ($post['errors'] === []) {
                $slugs[] = $post['data']['slug'];
            }
        }

        $existing = $this->posts->existingSlugs($blogId, $slugs);

        return array_map(static function (array $post) use ($existing): array {
            if ($post['errors'] === []) {
                $post['clash'] = $existing[$post['data']['slug']] ?? null;
            }

            return $post;
        }, $posts);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function subject(array $data, int $index): string
    {
        $title = is_string($data['title'] ?? null) ? trim($data['title']) : '';

        return $title !== '' ? mb_substr($title, 0, 100) : 'Post #'.($index + 1);
    }
}
