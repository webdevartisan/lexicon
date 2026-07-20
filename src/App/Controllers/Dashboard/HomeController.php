<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\BlogSubscriberModel;
use App\Models\CommentModel;
use App\Models\PostBookmarkModel;
use App\Models\PostModel;
use App\Models\UserPreferencesModel;
use App\Resources\BlogResource;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * HomeController renders the dashboard overview: a true at-a-glance summary
 * (stats, needs-attention queues, recent activity, quick actions). Not a
 * filtered post list. The post-list-with-tabs role lives on All Posts.
 */
class HomeController extends AppController
{
    public function __construct(
        private PostModel $post,
        private BlogModel $blogModel,
        private UserPreferencesModel $preference,
        private BlogSettingsModel $blogSettings,
        private BlogSubscriberModel $subscribers,
        private PostBookmarkModel $bookmarks,
        private CommentModel $comments,
    ) {}

    /**
     * Dashboard overview for the active blog context.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $selectedBlogId = $this->preference->getDefaultBlogId($user['id']) ?? 0;

        $accessibleBlogs = $this->blogModel->getAccessibleBlogs($user['id']);
        $isAdmin = auth()->hasRole('administrator');

        if (empty($accessibleBlogs)) {
            // No owned or shared blogs, so there is no dashboard to show.
            // The library is this account's home. Keeping the redirect here
            // means login and signup can keep sending everyone to /dashboard.
            return $this->redirect('/library');
        }

        // Pure collaborator (zero owned blogs, ≥1 shared): the Shared page is the natural landing.
        $ownsAny = false;
        foreach ($accessibleBlogs as $b) {
            if (($b['user_role'] ?? '') === 'owner') {
                $ownsAny = true;
                break;
            }
        }
        if (!$ownsAny && $selectedBlogId <= 0) {
            return $this->redirect('/dashboard/shared');
        }

        $blogIds = [];
        $blogSlugs = [];

        foreach ($accessibleBlogs as $blog) {
            $blogIds[(int) $blog['id']] = (string) $blog['blog_name'];
            $blogSlugs[(int) $blog['id']] = (string) $blog['blog_slug'];
        }

        if ($selectedBlogId <= 0 || !isset($blogSlugs[$selectedBlogId])) {
            return $this->view([
                'blogIds' => $blogIds,
                'blogSlug' => '',
                'selectedBlogId' => $selectedBlogId,
                'hasNoBlogs' => false,
                'stats' => $this->emptyStats(),
                'recent' => [],
                'needsAttention' => [],
                'blogsSummary' => $this->buildBlogsSummary($user['id']),
                'isAdmin' => $isAdmin,
                'blogRole' => 'none',
            ]);
        }

        $blog = $this->getBlog($selectedBlogId);
        Gate::authorize('view', $blog, $user);
        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);

        // Editorial pipeline determines whether "Pending" is a live bucket for this blog.
        // With workflow off it's not part of the normal flow. hide the tile and drop it from Needs Attention.
        $settings = $this->blogSettings->findByBlogId($selectedBlogId);
        $workflowEnabled = !empty($settings['workflow_enabled']);

        // Pull each status bucket once: we use the pagination total for stats,
        // and the first few rows as the "what's in this bucket" preview.
        $publishedResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'], page: 1, perPage: 4, blogId: $selectedBlogId, status: 'published'
        );
        $draftResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'], page: 1, perPage: 4, blogId: $selectedBlogId, status: 'draft'
        );
        $pendingResult = $workflowEnabled
            ? $this->post->findByAuthorWithFiltersPagination(
                authorId: $user['id'], page: 1, perPage: 4, blogId: $selectedBlogId, status: 'pending'
            )
            : ['data' => [], 'pagination' => ['total_records' => 0]];
        $archivedResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'], page: 1, perPage: 1, blogId: $selectedBlogId, status: 'archived'
        );

        $stats = [
            'published' => (int) $publishedResult['pagination']['total_records'],
            'draft' => (int) $draftResult['pagination']['total_records'],
            'pending' => (int) $pendingResult['pagination']['total_records'],
            'archived' => (int) $archivedResult['pagination']['total_records'],
            'comments' => $this->post->countCommentsByBlogIdAndStatus($selectedBlogId, 'approved'),
            'comments_pending' => $this->post->countCommentsByBlogIdAndStatus($selectedBlogId, 'pending'),
            'subscribers' => $this->subscribers->countForBlog($selectedBlogId),
        ];
        $stats['total'] = $stats['published'] + $stats['draft'] + $stats['pending'];

        // "Needs attention" = drafts (+ pending when the review pipeline is on).
        // We surface them so the creator sees unfinished work the moment they land.
        $needsAttention = array_slice(
            $workflowEnabled
                ? array_merge($draftResult['data'], $pendingResult['data'])
                : $draftResult['data'],
            0,
            4
        );

        breadcrumbs()->clear();

        return $this->view([
            'blogIds' => $blogIds,
            'blogSlug' => $blogSlugs[$selectedBlogId],
            'selectedBlogId' => $selectedBlogId,
            'hasNoBlogs' => false,
            'stats' => $stats,
            'recent' => $publishedResult['data'],
            'needsAttention' => $needsAttention,
            'blogsSummary' => count($blogIds) > 1 ? $this->buildBlogsSummary($user['id']) : [],
            'isAdmin' => $isAdmin,
            'blogRole' => $blogRole,
            'workflowEnabled' => $workflowEnabled,
            'hideTitle' => true,
        ]);
    }

    /**
     * GET /library
     *
     * Open to creators as well as readers. Publishing a blog does not stop
     * you reading other people's, so the library is not gated on role.
     */
    public function library(): Response
    {
        return $this->readerHome(auth()->user());
    }

    /**
     * The reading hub: feed from subscribed blogs, recent saves, recent
     * conversations, and a soft path into writing.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    private function readerHome(array $user): Response
    {
        $userId = (int) $user['id'];
        $email = (string) ($user['email'] ?? '');

        breadcrumbs()->clear();

        return $this->view('home.reader', [
            'feed' => $this->subscribers->feedForUser($userId, $email, 12),
            'savedPosts' => array_slice($this->bookmarks->bookmarkedPosts($userId), 0, 4),
            'subscriptions' => $this->subscribers->forUser($userId, $email),
            'replies' => $this->comments->repliesToUser($userId, 3),
            'myComments' => $this->comments->byUserWithContext($userId, 3),
            'noBreadcrumb' => true,
            'hideTitle' => true,
        ]);
    }

    public function setDefaultBlog(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();

        // Missing field used to fatal on an undefined index. The owner check
        // below already covers authorization, this just stops a malformed
        // post returning a 500.
        $selectedBlogId = (int) ($this->request->post['blog'] ?? 0);

        // Default-blog is the OWNER workspace context.
        $blog = $this->blogModel->getBlog($selectedBlogId);
        if (!$blog) {
            $this->flash('error', 'That blog no longer exists.');

            return $this->redirect('/dashboard');
        }

        if ((int) $blog->ownerId() !== (int) $user['id']) {
            $this->flash('error', 'You can only set blogs you own as your default. Shared work lives on the Shared page.');

            return $this->redirect('/dashboard');
        }

        $this->preference->setDefaultBlogId($user['id'], $selectedBlogId);

        return $this->redirect('/dashboard');
    }

    /**
     * Per-blog summary cards (only relevant when the user has 2+ blogs).
     *
     * @return array<int, array{id:int,name:string,slug:string,post_count:int}>
     */
    private function buildBlogsSummary(int $userId): array
    {
        $rows = $this->blogModel->getBlogsByOwnerWithCounts($userId);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) ($row['blog_name'] ?? ''),
            'slug' => (string) ($row['blog_slug'] ?? ''),
            'post_count' => (int) ($row['post_count'] ?? 0),
            'status' => (string) ($row['status'] ?? 'draft'),
        ], $rows);
    }

    /** @return array<string,int> */
    private function emptyStats(): array
    {
        return [
            'published' => 0,
            'draft' => 0,
            'pending' => 0,
            'archived' => 0,
            'comments' => 0,
            'comments_pending' => 0,
            'subscribers' => 0,
            'total' => 0,
        ];
    }

    private function getBlog(int $id): BlogResource
    {
        $blog = $this->blogModel->getBlog((string) $id);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$id}' not found.");
        }

        return $blog;
    }
}
