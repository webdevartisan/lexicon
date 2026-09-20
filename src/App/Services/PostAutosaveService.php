<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\TimezoneHelper;
use App\Models\PostModel;
use DateTime;
use DateTimeZone;

/**
 * Handles asynchronous post autosave operations.
 *
 * Orchestrates validation, datetime conversion, and save logic for AJAX autosave.
 * Separates autosave concerns from main CRUD controller.
 */
final class PostAutosaveService
{
    public function __construct(
        private PostModel $posts,
        private ExternalMediaGuard $mediaGuard,
        private PostContentSanitizer $contentSanitizer,
    ) {}

    /**
     * Save post draft via autosave (create or update).
     *
     * @param  array<string, mixed>  $data  Validated post data
     * @param  int  $userId  User ID
     * @param  int|null  $postId  Post ID (null for new draft)
     * @param  int|null  $blogId  Blog a new draft is created in, already authorized by the caller
     * @return array{success: bool, id?: int, saved_at?: string, error?: string, errors?: array<string, string[]>}
     */
    public function save(array $data, int $userId, ?int $postId = null, ?int $blogId = null): array
    {
        if (isset($data['content'])) {
            $data['content'] = $this->contentSanitizer->clean((string) $data['content']);
        }

        // A date that cannot be read is left out and reported, and the rest of the draft still saves.
        $fieldErrors = [];
        if (!empty($data['published_at'])) {
            $utc = TimezoneHelper::localToUtc((string) $data['published_at'], (string) ($data['timezone'] ?? 'UTC'));
            if ($utc === null) {
                unset($data['published_at']);
                $fieldErrors['published_at'] = [TimezoneHelper::INVALID_PUBLISH_DATE];
            } else {
                $data['published_at'] = $utc;
            }
        }

        $data['author_id'] = $userId;

        if ($postId) {
            // Update existing post
            $post = $this->posts->findResource($postId);

            if (!$post) {
                return ['success' => false, 'error' => 'Post not found'];
            }

            if ($post->authorId() !== $userId) {
                return ['success' => false, 'error' => 'Unauthorized'];
            }

            // Don't allow slug changes on autosave
            unset($data['slug']);

            $rejection = $this->outsideMediaRejection((string) ($data['content'] ?? ''), $post->content());
            if ($rejection !== null) {
                return $rejection;
            }

            $this->posts->update($postId, $data);
            $savedSlug = $post->slug();

        } else {
            if ($blogId === null) {
                return ['success' => false, 'error' => 'There is no blog to save this draft in. Pick a blog and try again.'];
            }

            $rejection = $this->outsideMediaRejection((string) ($data['content'] ?? ''), '');
            if ($rejection !== null) {
                return $rejection;
            }

            $data['blog_id'] = $blogId;
            if (!empty($data['slug'])) {
                $data['slug'] = $this->posts->availableSlug($blogId, (string) $data['slug']);
            }
            $savedSlug = (string) ($data['slug'] ?? '');
            $this->posts->insert($data);
            $postId = $this->posts->getInsertID();
        }

        // Format saved time in user's timezone
        $userTimezone = $data['timezone'] ?? date_default_timezone_get();
        $dt = new DateTime('now', new DateTimeZone($userTimezone));

        return [
            'success' => true,
            'id' => $postId,
            'saved_at' => $dt->format('g:i:s A'),
            'slug' => $savedSlug,
        ] + ($fieldErrors === [] ? [] : ['errors' => $fieldErrors]);
    }

    /**
     * @return array{success: false, error: string, errors: array<string, string[]>}|null
     */
    private function outsideMediaRejection(string $content, string $previous): ?array
    {
        $outside = $this->mediaGuard->newExternalSources($content, $previous);
        if ($outside === []) {
            return null;
        }

        $message = $this->mediaGuard->rejectionMessage($outside);

        return ['success' => false, 'error' => $message, 'errors' => ['content' => [$message]]];
    }
}
