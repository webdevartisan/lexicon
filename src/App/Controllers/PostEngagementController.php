<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PostBookmarkModel;
use App\Models\PostLikeModel;
use App\Models\PostModel;
use Framework\Core\Response;

/**
 * Toggles likes and bookmarks on published public posts.
 */
class PostEngagementController extends AppController
{
    public function __construct(
        private PostModel $postModel,
        private PostLikeModel $likeModel,
        private PostBookmarkModel $bookmarkModel,
    ) {}

    public function toggleLike(string $id): Response
    {
        return $this->toggle((int) $id, 'like');
    }

    public function toggleBookmark(string $id): Response
    {
        return $this->toggle((int) $id, 'bookmark');
    }

    /**
     * Shared toggle flow: CSRF, post visibility check, flip, audit, JSON out.
     */
    private function toggle(int $postId, string $type): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $post = $this->postModel->find((string) $postId);

        if (!$post || $post['status'] !== 'published' || $post['visibility'] !== 'public') {
            return $this->jsonError('Post not found.', 404);
        }

        $userId = (int) auth()->user()['id'];

        $model = $type === 'like' ? $this->likeModel : $this->bookmarkModel;
        $active = $model->toggle($userId, $postId);

        audit()->log($userId, "post.{$type}.toggled", 'post', $postId, ['active' => $active], $this->request->ip());

        return $this->jsonSuccess([
            'active' => $active,
            'count' => $model->countByPost($postId),
        ]);
    }
}
