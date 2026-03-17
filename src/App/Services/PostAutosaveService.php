<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PostModel;
use App\Models\UserPreferencesModel;
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
        private UserPreferencesModel $preferences
    ) {}

    /**
     * Save post draft via autosave (create or update).
     *
     * @param array $data Validated post data
     * @param int $userId User ID
     * @param int|null $postId Post ID (null for new draft)
     * @return array{success: bool, id?: int, saved_at?: string, error?: string, errors?: array}
     */
    public function save(array $data, int $userId, ?int $postId = null): array
    {
        // Handle published_at datetime conversion
        if (!empty($data['timezone']) && !empty($data['published_at'])) {
            try {
                $dt = DateTime::createFromFormat(
                    'd.m.y H:i',
                    $data['published_at'],
                    new DateTimeZone($data['timezone'])
                );

                if ($dt !== false) {
                    $dt->setTimezone(new DateTimeZone('UTC'));
                    $data['published_at'] = $dt->format('Y-m-d H:i:s');
                }
            } catch (\Exception $e) {
                // Silently skip invalid datetime
                unset($data['published_at']);
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

            $this->posts->update($postId, $data);

        } else {
            // Create new draft
            $defaultBlogId = $this->preferences->getDefaultBlogId($userId);

            if (!$defaultBlogId) {
                return ['success' => false, 'error' => 'No default blog set'];
            }

            $data['blog_id'] = $defaultBlogId;
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
        ];
    }
}
