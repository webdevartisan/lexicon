<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\ActivityLogModel;
use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\CacheManagementService;
use Framework\Core\Response;

/**
 * Control panel overview: site-wide stats, moderation queue, recent
 * activity, and system health in one screen.
 */
class ControlPanelController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'accessDashboard';

    public function __construct(
        private PostModel $posts,
        private CommentModel $comments,
        private UserModel $users,
        private BlogModel $blogs,
        private ActivityLogModel $activityLog,
        private CacheManagementService $cacheService,
        private SettingModel $settings
    ) {}

    public function index(): Response
    {
        $commentCounts = $this->comments->countsByStatus();
        $postCounts = $this->posts->countsByStatus();

        $stats = [
            'posts' => array_sum($postCounts),
            'comments' => $commentCounts['all'],
            'pending_comments' => $commentCounts['pending'],
            'users' => $this->users->count(),
            'blogs' => $this->blogs->count(),
        ];

        $pendingComments = $this->comments->findAllWithFilters('pending', '', 1, 5);

        // top blogs by post volume for the insights column
        $topBlogs = $this->blogs->getAllBlogsWithOwnerAndCounts();
        usort($topBlogs, fn ($a, $b) => (int) $b['post_count'] <=> (int) $a['post_count']);
        $topBlogs = array_slice($topBlogs, 0, 5);

        return $this->view('controlpanel.index', [
            'stats' => $stats,
            'postCounts' => $postCounts,
            'commentCounts' => $commentCounts,
            'signups' => $this->users->signupsByDay(30),
            'topBlogs' => $topBlogs,
            'recentPosts' => $this->posts->findAllForAdmin(1, 5)['data'],
            'pendingComments' => $pendingComments['data'],
            'recentUsers' => $this->users->latest(5),
            'recentActivity' => $this->activityLog->latestEntries(8),
            'cacheStats' => $this->cacheService->getStats(),
            'health' => $this->systemHealth(),
            'user' => auth()->user(),
        ]);
    }

    /**
     * Environment facts an admin checks when something feels off.
     */
    private function systemHealth(): array
    {
        $storageDir = dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'storage';
        $freeBytes = @disk_free_space($storageDir);

        return [
            'php_version' => PHP_VERSION,
            'environment' => (string) env('APP_ENV', 'unknown'),
            'debug' => (bool) env('APP_DEBUG', false),
            // One switch: the storage/maintenance.json flag file (see MaintenanceMode)
            'maintenance' => \App\Services\MaintenanceMode::active(),
            'mail_driver' => (string) env('MAIL_DRIVER', 'not set'),
            'disk_free_gb' => $freeBytes !== false ? round($freeBytes / 1024 ** 3, 1) : null,
            'server_time' => date('Y-m-d H:i:s T'),
        ];
    }
}
