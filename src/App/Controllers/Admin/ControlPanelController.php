<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\CommentModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\CacheManagementService;
use Framework\Core\Response;

class ControlPanelController extends AppController
{
    public function __construct(
        private PostModel $posts,
        private CommentModel $comments,
        private UserModel $users, 
        private CacheManagementService $cacheService
    ) {}

    public function index(): Response
    {
        $this->requireRole('administrator');

        $stats = [
            'posts' => $this->posts->count(),
            'comments' => $this->comments->count(),
            'users' => $this->users->count(),
        ];

        // $recentPosts    = $this->posts->latest(5);
        // $recentComments = $this->comments->latest(5);

        // fetch cache statistics for the control panel overview.
        //$cacheStats = cache()->stats();
        $cacheStats = $this->cacheService->getStats();

        return $this->view('controlpanel.index', [
            'stats' => $stats,
            // 'recentPosts'    => $recentPosts,
            // 'recentComments' => $recentComments,
            'cacheStats' => $cacheStats,
            'user' => auth()->user(),
        ]);
    }
}
