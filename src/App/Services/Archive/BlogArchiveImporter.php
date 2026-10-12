<?php

declare(strict_types=1);

namespace App\Services\Archive;

use App\Interfaces\ArchiveMediaStoreInterface;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Resources\BlogResource;
use App\Services\PublicCacheInvalidator;
use App\ValueObjects\PostFieldRules;
use Framework\Database;

/**
 * Applies an archive plan to a blog: stores its media, then writes its posts.
 *
 * Knows nothing about HTTP, so a background worker can call it as it is.
 * Media goes first because several posts can share one file. Each post is then
 * written in its own transaction with its category and tags, so one bad post
 * never undoes the rest and never lands half written. Imported posts are never
 * announced to subscribers: every post that is not a draft is stamped as
 * already notified, and the notifier is never called.
 */
final class BlogArchiveImporter
{
    public const CLASH_SKIP = 'skip';

    public const CLASH_COPY = 'copy';

    public const CLASH_OVERWRITE = 'overwrite';

    public function __construct(
        private Database $db,
        private PostModel $posts,
        private BlogModel $blogs,
        private ArchiveMediaStoreInterface $mediaStore,
        private PublicCacheInvalidator $cacheInvalidator,
        private ArchivePostWriter $writer,
    ) {}

    /**
     * @param  array{on_clash: string, keep_status: bool, work_dir: string}  $options
     * @param  callable(string, int): void|null  $progress  Called with a stage name and a running count
     * @return array{created: list<array<string, mixed>>, overwritten: list<array<string, mixed>>, skipped: list<array<string, mixed>>, failed: list<array<string, mixed>>, media: array{stored: int, failed: list<array{name: string, reason: string}>}}
     *
     * @throws ArchiveException When the archive cannot be opened again
     */
    public function import(ArchivePlan $plan, string $zipPath, BlogResource $target, int $userId, array $options, ?callable $progress = null): array
    {
        if (!in_array($options['on_clash'], [self::CLASH_SKIP, self::CLASH_COPY, self::CLASH_OVERWRITE], true)) {
            throw new \InvalidArgumentException('Unknown clash policy: '.$options['on_clash']);
        }

        $mediaFailures = [];
        $urls = $this->storeMedia($plan, $zipPath, $target, $userId, $options['work_dir'], $mediaFailures, $progress);

        $result = ['created' => [], 'overwritten' => [], 'skipped' => [], 'failed' => []];
        foreach ($plan->posts as $i => $entry) {
            [$outcome, $item] = $this->importPost($plan, $entry, $urls, $target, $userId, $options);
            $result[$outcome][] = $item;

            if ($progress !== null) {
                $progress('posts', $i + 1);
            }
        }

        if ($result['created'] !== [] || $result['overwritten'] !== []) {
            $this->purgeCaches($target);
        }

        return $result + ['media' => ['stored' => count($urls), 'failed' => $mediaFailures]];
    }

    /**
     * Store every usable file into the target blog's media folder and library.
     *
     * @param  list<array{name: string, reason: string}>  $failures
     * @param  callable(string, int): void|null  $progress
     * @return array<string, string> Archive path => public URL
     */
    private function storeMedia(ArchivePlan $plan, string $zipPath, BlogResource $target, int $userId, string $workDir, array &$failures, ?callable $progress): array
    {
        $usable = $plan->usableMedia();
        if ($usable === []) {
            return [];
        }

        $zip = ArchiveZip::open($zipPath);
        $urls = [];

        try {
            foreach ($usable as $path => $entry) {
                try {
                    $urls[$path] = $this->storeOne($zip, $entry, $target, $userId, $workDir);
                } catch (\Throwable $e) {
                    $failures[] = ['name' => $entry['original_name'], 'reason' => $this->reason($e, 'The image could not be stored.')];
                }

                if ($progress !== null) {
                    $progress('media', count($urls) + count($failures));
                }
            }
        } finally {
            $zip->close();
        }

        return $urls;
    }

    /**
     * Extract one file to the work folder and hand it to the media store.
     *
     * @param  array{path: string, original_name: string, alt: ?string}  $entry
     */
    private function storeOne(ArchiveZip $zip, array $entry, BlogResource $target, int $userId, string $workDir): string
    {
        $ext = strtolower(pathinfo($entry['path'], PATHINFO_EXTENSION));
        $name = (pathinfo($entry['original_name'], PATHINFO_FILENAME) ?: 'image').'.'.$ext;
        $temp = $workDir.'/'.bin2hex(random_bytes(8)).'.'.$ext;

        try {
            $zip->extractTo($entry['path'], $temp, ArchiveFormat::MAX_IMAGE_BYTES);

            return $this->mediaStore->store($temp, $workDir, $name, $entry['alt'], $target, $userId);
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    /**
     * Write one post, or say why it was not written.
     *
     * @param  array{data: array<string, mixed>, errors: list<string>, clash: array{id: int, status: string, title: string}|null}  $entry
     * @param  array<string, string>  $urls  Archive path => stored URL
     * @param  array{on_clash: string, keep_status: bool}  $options
     * @return array{0: string, 1: array<string, mixed>} Outcome key and the result row
     */
    private function importPost(ArchivePlan $plan, array $entry, array $urls, BlogResource $target, int $userId, array $options): array
    {
        $data = $entry['data'];
        $item = ['title' => (string) ($data['title'] ?? ''), 'slug' => (string) ($data['slug'] ?? ''), 'notes' => []];

        if ($entry['errors'] !== []) {
            return ['failed', $item + ['reason' => implode(' ', $entry['errors'])]];
        }

        $clash = $entry['clash'];
        if ($clash !== null && $options['on_clash'] === self::CLASH_SKIP) {
            return ['skipped', $item + ['reason' => 'A post with this slug already exists.']];
        }
        if ($clash !== null && $options['on_clash'] === self::CLASH_OVERWRITE && $clash['status'] === 'moderated') {
            return ['skipped', $item + ['reason' => 'The existing post is held by moderation and cannot be overwritten.']];
        }

        try {
            $prepared = $this->writer->prepare($data, $urls, $options['keep_status'], $plan->missingAtExport);
            $item['notes'] = $prepared['notes'];
            $overwriteId = $clash !== null && $options['on_clash'] === self::CLASH_OVERWRITE ? $clash['id'] : null;

            if ($overwriteId === null && $clash !== null) {
                $prepared['row']['slug'] = $this->posts->availableSlug((int) $target->id(), $item['slug'], null, PostFieldRules::SLUG_MAX);
                $item['notes'][] = 'Imported as '.$prepared['row']['slug'].'.';
                $item['slug'] = $prepared['row']['slug'];
            }

            $item['id'] = $this->db->transaction(
                fn (): int => $this->writer->write($prepared, $target, $userId, $overwriteId)
            );
        } catch (\Throwable $e) {
            return ['failed', $item + ['reason' => $this->reason($e, 'The post could not be saved.')]];
        }

        return [$overwriteId !== null ? 'overwritten' : 'created', $item];
    }

    private function purgeCaches(BlogResource $target): void
    {
        $this->blogs->forgetPublicCaches((string) $target->toArray()['blog_slug']);
        $this->posts->forgetBlogPostFragments((int) $target->id());
        $this->cacheInvalidator->purgeDiscover();
    }

    /**
     * The message for a failure the owner can act on, or a generic one with the real error logged.
     */
    private function reason(\Throwable $e, string $generic): string
    {
        if ($e instanceof ArchiveException || $e instanceof \InvalidArgumentException) {
            return $e->getMessage();
        }

        error_log('Blog archive import: '.get_class($e).': '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

        return $generic;
    }
}
