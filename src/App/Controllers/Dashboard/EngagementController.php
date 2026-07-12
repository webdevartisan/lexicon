<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\PostBookmarkModel;
use App\Models\PostLikeModel;
use Framework\Core\Response;

/**
 * The signed-in user's liked and saved posts, TikTok-profile style.
 */
class EngagementController extends AppController
{
    public function __construct(
        private PostLikeModel $likeModel,
        private PostBookmarkModel $bookmarkModel,
    ) {}

    public function likes(): Response
    {
        return $this->renderTab('likes');
    }

    public function bookmarks(): Response
    {
        return $this->renderTab('bookmarks');
    }

    private function renderTab(string $tab): Response
    {
        $userId = (int) auth()->user()['id'];

        $posts = $tab === 'likes'
            ? $this->likeModel->likedPosts($userId)
            : $this->bookmarkModel->bookmarkedPosts($userId);

        return $this->view('engagement.index', [
            'tab' => $tab,
            'posts' => $posts,
        ]);
    }
}
