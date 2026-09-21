<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Gate;
use App\Models\ActivityLogModel;
use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\ModerationCaseModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Presenters\ModerationCasePresenter;
use App\Resources\SystemResource;
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
        private ModerationCaseModel $cases,
    ) {}

    public function index(): Response
    {
        $commentCounts = $this->comments->countsByStatus();
        $postCounts = $this->posts->countsByStatus();

        $stats = [
            'posts' => array_sum($postCounts),
            'comments' => $commentCounts['all'],
            'users' => $this->users->count(),
            'blogs' => $this->blogs->count(),
        ];

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
            'reports' => $this->reportsSummary(),
            'recentUsers' => $this->users->latest(5),
            'recentActivity' => $this->activityLog->latestEntries(8),
            'cacheStats' => $this->cacheService->getStats(),
            'health' => $this->systemHealth(),
            'user' => auth()->user(),
        ]);
    }

    /**
     * The open report cases, most urgent first, for anyone who may handle
     * them. Null hides the report widgets from everyone else.
     *
     * @return array{active: int, escalated: int, cases: array<int, array<string, mixed>>}|null
     */
    private function reportsSummary(): ?array
    {
        if (!Gate::allows('handleReports', SystemResource::class, auth()->user() ?? [])) {
            return null;
        }

        $counts = $this->cases->statusCounts();
        $top = $this->cases->findForQueue(['status' => 'active'], 1, 5, 'mc.priority DESC, mc.id DESC');

        return [
            'active' => $counts['open'] + $counts['in_review'] + $counts['escalated'],
            'escalated' => $counts['escalated'],
            'cases' => array_map(ModerationCasePresenter::present(...), $top['data']),
        ];
    }

    /**
     * Environment facts an admin checks when something feels off.
     *
     * @return array<string, mixed> Environment summary values
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
