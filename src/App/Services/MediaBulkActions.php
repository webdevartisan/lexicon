<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MediaModel;
use App\Resources\BlogResource;
use InvalidArgumentException;

/**
 * Acts on several library items at once.
 */
final class MediaBulkActions
{
    /** Checking where an image is used reads every post's text, so a batch stays small */
    public const MAX_ITEMS = 50;

    public function __construct(
        private MediaModel $media,
        private MediaService $mediaService,
        private MediaUsageResolver $usage,
    ) {}

    /**
     * Where each of the given items is used, listing only the items that are used somewhere.
     *
     * @param  array<int, mixed>  $ids  Library ids from the request
     * @return array<int, array{id: int, name: string, usages: array<int, array<string, mixed>>}>
     */
    public function usages(array $ids, BlogResource $blog): array
    {
        $found = [];
        foreach ($this->cleanIds($ids) as $id) {
            $row = $this->media->findForBlog($id, $blog->id());
            if (!$row) {
                continue;
            }

            $usages = $this->usage->usages($blog->id(), (string) $row['url']);
            if ($usages !== []) {
                $found[] = [
                    'id' => $id,
                    'name' => (string) ($row['original_name'] ?: $row['filename']),
                    'usages' => $usages,
                ];
            }
        }

        return $found;
    }

    /**
     * Delete the given items. Ids that belong to another blog or no longer exist are skipped.
     *
     * @param  array<int, mixed>  $ids  Library ids from the request
     * @return array<int, int> The ids that were deleted
     */
    public function delete(array $ids, BlogResource $blog): array
    {
        return array_values(array_filter(
            $this->cleanIds($ids),
            fn (int $id): bool => $this->mediaService->delete($id, $blog),
        ));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function cleanIds(array $ids): array
    {
        $clean = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));

        if ($clean === []) {
            throw new InvalidArgumentException('Select at least one image.');
        }
        if (count($clean) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('Select at most '.self::MAX_ITEMS.' images at a time.');
        }

        return $clean;
    }
}
