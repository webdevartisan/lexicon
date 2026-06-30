<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CategoryModel;
use App\Models\PostModel;
use App\Models\PostReviewerModel;
use App\Models\ReviewModel;
use App\Models\TagModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Resources\PostResource;
use App\Services\MediaService;
use App\Services\NotificationService;
use App\Services\PostAutosaveService;
use App\Services\UploadService;
use App\Services\WorkflowService;
use DateTime;
use DateTimeZone;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Dashboard post management controller.
 *
 * Handles CRUD operations, workflow transitions (draft→review→approve→publish),
 * autosave, and file uploads. Authorization via Gate + PostPolicy.
 */
final class PostController extends AppController
{
    public function __construct(
        private PostModel $model,
        private BlogModel $blogModel,
        private UserPreferencesModel $preference,
        private UploadService $uploader,
        private PostAutosaveService $autosaveService,
        private CategoryModel $categoryModel,
        private TagModel $tagModel,
        private WorkflowService $workflowService,
        private PostReviewerModel $postReviewerModel,
        private ReviewModel $reviewModel,
        private BlogSettingsModel $blogSettingsModel,
        private NotificationService $notifications,
        private UserModel $userModel,
        private MediaService $mediaService,
    ) {}

    /**
     * List user's posts with filters, search, and pagination.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $blogs = $this->blogModel->getBlogsByOwnerId($user['id']);
        $blogSlugs = array_column($blogs, 'blog_slug', 'id');
        $validBlogIds = array_column($blogs, 'id');

        // blog_id=all (sentinel) → explicit "All blogs"; numeric → that blog; missing → user's default.
        $blogIdRaw = isset($this->request->get['blog_id']) ? (string) $this->request->get['blog_id'] : null;
        $isAllBlogs = ($blogIdRaw === 'all' || $blogIdRaw === '');

        if ($isAllBlogs) {
            $blogId = null;
        } else {
            $blogId = ($blogIdRaw !== null) ? (int) $blogIdRaw : null;
            if ($blogId !== null && !in_array($blogId, $validBlogIds, true)) {
                $blogId = null;
            }
            // No selection at all → fall back to default blog
            if ($blogId === null && $blogIdRaw === null) {
                $defaultBlogId = $this->preference->getDefaultBlogId($user['id']);
                if ($defaultBlogId && in_array($defaultBlogId, $validBlogIds, true)) {
                    $blogId = $defaultBlogId;
                }
            }
        }

        // String value used by the view for URL building (preserves the "all" choice across pill clicks).
        $blogIdView = $isAllBlogs ? 'all' : ($blogId !== null ? (string) $blogId : null);

        $status = trim((string) ($this->request->get['status'] ?? ''));
        $q = trim((string) ($this->request->get['q'] ?? ''));
        $page = max(1, (int) ($this->request->get['page'] ?? 1));
        $perPage = 12;

        // The "Needs Changes" tab is workflow-state-driven, not status-driven —
        // those posts have status=draft. Translate the virtual status key to a
        // real workflow filter so the existing tab plumbing keeps working.
        $workflowFilter = '';
        if ($status === 'needs_changes') {
            $workflowFilter = 'needs_changes';
            $status = '';
        }

        $allowedSorts = ['newest', 'oldest', 'title_asc', 'title_desc'];
        $sort = (string) ($this->request->get['sort'] ?? 'newest');
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'newest';
        }

        // Category/tag filters only make sense inside one blog (they're per-blog).
        // Validate the ids belong to the active blog, otherwise drop them.
        $categoryId = null;
        $tagId = null;
        $blogCategories = [];
        $blogTags = [];
        if ($blogId !== null) {
            $blogCategories = $this->categoryModel->getByBlogId($blogId);
            $blogTags = $this->tagModel->getByBlogId($blogId);

            $rawCat = (int) ($this->request->get['category'] ?? 0);
            if ($rawCat > 0 && $this->categoryModel->findForBlog($rawCat, $blogId)) {
                $categoryId = $rawCat;
            }

            $rawTag = (int) ($this->request->get['tag'] ?? 0);
            if ($rawTag > 0 && $this->tagModel->findForBlog($rawTag, $blogId)) {
                $tagId = $rawTag;
            }
        }

        $result = $this->model->findByAuthorWithFiltersPagination(
            authorId: $user['id'],
            page: $page,
            perPage: $perPage,
            blogId: $blogId,
            status: $status,
            searchQuery: $q,
            sort: $sort,
            categoryId: $categoryId,
            tagId: $tagId,
            workflowState: $workflowFilter
        );

        // Each card shows a few of its tags as quick filters.
        foreach ($result['data'] as &$p) {
            $p['tags'] = $this->model->tags((int) $p['id']);
        }
        unset($p);

        // Per-status totals power the filter chip badges (respecting the active
        // category/tag filter so the numbers match what's shown).
        $counts = [
            'all' => 0,
            'published' => 0,
            'draft' => 0,
            'pending' => 0,
            'needs_changes' => 0,
            'archived' => 0,
        ];
        foreach (['published', 'draft', 'pending', 'archived'] as $s) {
            $counts[$s] = (int) $this->model->findByAuthorWithFiltersPagination(
                authorId: $user['id'], page: 1, perPage: 1, blogId: $blogId, status: $s, searchQuery: $q,
                categoryId: $categoryId, tagId: $tagId
            )['pagination']['total_records'];
        }
        // needs_changes is workflow-state, not status — distinct count.
        $counts['needs_changes'] = (int) $this->model->findByAuthorWithFiltersPagination(
            authorId: $user['id'], page: 1, perPage: 1, blogId: $blogId, status: '', searchQuery: $q,
            categoryId: $categoryId, tagId: $tagId, workflowState: 'needs_changes'
        )['pagination']['total_records'];
        $counts['all'] = $counts['published'] + $counts['draft'] + $counts['pending'] + $counts['archived'];

        // Preserve the user-facing status key for the view (so 'needs_changes' pill stays active).
        $statusForView = $workflowFilter !== '' ? $workflowFilter : $status;

        $activeBlogSlug = ($blogId !== null && isset($blogSlugs[$blogId])) ? $blogSlugs[$blogId] : '';

        // Tag filter context only resolves a name when scoped to a blog (tags are per-blog).
        $activeTag = ($tagId !== null && $blogId !== null) ? $this->tagModel->findForBlog($tagId, $blogId) : null;

        return $this->view([
            'posts' => $result['data'],
            'pagination' => $result['pagination'],
            'user' => $user,
            'blogs' => $blogs,
            'blog_id' => $blogId,
            'blogIdView' => $blogIdView,
            'isAllBlogs' => $isAllBlogs,
            'blog_slug' => $blogSlugs,
            'activeBlogSlug' => $activeBlogSlug,
            'status' => $statusForView,
            'q' => $q,
            'sort' => $sort,
            'counts' => $counts,
            'blogCategories' => $blogCategories,
            'blogTags' => $blogTags,
            'categoryId' => $categoryId,
            'tagId' => $tagId,
            'activeTag' => $activeTag,
        ]);
    }

    /**
     * Show single post with relationships.
     *
     * @param  string  $slug  Post slug
     */
    public function show(string $slug): Response
    {
        $post = $this->getPostBySlug($slug);

        return $this->view('Dashboard/Posts/show.lex.php', [
            'post' => $post,
            'author' => $this->model->author((int) $post['user_id']),
            'category' => $this->model->category($post['category_id'] ?? null),
            'tags' => $this->model->tags((int) $post['id']),
            'comments' => $this->model->comments((int) $post['id']),
        ]);
    }

    /**
     * Show post creation form.
     */
    public function new(): Response
    {
        $user = auth()->user();
        $userId = (int) $user['id'];

        // ?blog_id= lets a collaborator write into a shared blog without
        // touching their default-blog preference. Validated via Gate so we
        // can't be tricked into rendering the form for a blog the user lacks.
        $requestedBlogId = (int) ($this->request->get['blog_id'] ?? 0);
        $blogId = $requestedBlogId > 0
            ? $requestedBlogId
            : (int) ($this->preference->getDefaultBlogId($userId) ?? 0);

        if ($blogId <= 0) {
            $this->flash('error', 'Pick a blog first — either set a default or write from the Shared page.');

            return $this->redirect('/dashboard');
        }

        $blog = $this->getBlog($blogId);
        Gate::authorize('createPost', $blog, $user);

        return $this->view([
            'blog' => $blog->toArray(),
            'categories' => $this->categoryModel->getByBlogId((int) $blog->id()),
            'allTags' => $this->tagModel->getByBlogId((int) $blog->id()),
            'postTags' => [],
        ]);
    }

    /**
     * Create new post.
     */
    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();

        // The form posts back blog_id so create() knows which blog the new()
        // form was scoped to — works the same for default-blog and shared-blog flows.
        $formBlogId = (int) ($this->request->postParam('blog_id') ?? 0);
        $blogId = $formBlogId > 0
            ? $formBlogId
            : (int) ($this->preference->getDefaultBlogId($user['id']) ?? 0);

        $blog = $this->getBlog($blogId);

        Gate::authorize('createPost', $blog, $user);

        $validator = $this->validateOrFail([
            'title' => 'required|title|min:2|max:100',
            'slug' => 'required|slug|min:2|max:100|unique:posts,slug',
            'status' => 'in:'.implode(',', PostModel::STATUSES),
            'content' => 'required|max:60000',
            'excerpt' => 'required|max:300',
            'timezone' => 'timezone',
            'published_at' => 'datetime:d.m.y H:i',
        ]);

        $data = $validator->validated();

        // Convert published_at to UTC
        if (!empty($data['timezone']) && !empty($data['published_at'])) {
            $data['published_at'] = $this->normalizePublishedAt(
                $data['published_at'],
                $data['timezone']
            );
        } else {
            unset($data['published_at']);
        }

        $data['blog_id'] = $blog->id();
        $data['author_id'] = $user['id'];
        $data['category_id'] = $this->resolveCategoryId((int) $blog->id());

        // Handle featured image upload
        $featuredImagePath = $this->handleFeaturedImageUpload($user['id'], $blog);
        if ($featuredImagePath) {
            $data['featured_image'] = $featuredImagePath;
        }

        $okInsert = $this->model->insert($data);
        $postId = $this->model->getInsertID();

        if ($okInsert) {
            $this->tagModel->syncForPost($postId, (int) $blog->id(), $this->parsePostTags());
            $this->model->setFeatured($postId, (int) $blog->id(), !empty($this->request->post['is_featured']));

            audit()->log(
                $user['id'],
                'post.created',
                'post',
                $postId,
                ['title' => $data['title'], 'status' => $data['status']],
                $this->request->ip()
            );

            $this->flash('success', 'Post draft saved.');

            return $this->redirect("/dashboard/post/{$postId}/edit");
        }

        return $this->view('post.new', [
            'post' => $this->request->post,
        ]);
    }

    /**
     * Show post edit form.
     *
     * @param  string  $id  Post ID
     */
    public function edit(string $id): Response
    {
        $user = auth()->user();
        $post = $this->getPost((int) $id);
        $blog = $post->blog();

        Gate::authorize('update', $post, $user);

        // Convert published_at to display timezone
        $displayDate = null;
        if (!empty($post->publishedAt())) {
            $dt = new DateTime($post->publishedAt(), new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone($post->timezone()));
            $displayDate = $dt->format('d.m.y H:i');
        }

        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);
        $workflowState = $post->workflowState() ?? 'draft';
        $status = $post->status();

        $postUrl = base_url().'/blog/'.$blog->toArray()['blog_slug'].'/'.$post->toArray()['slug'];

        breadcrumbs()->replaceLast('Edit Post: '.$post->id());

        $postArray = $post->toArray();
        if ($displayDate) {
            $postArray['published_at'] = $displayDate;
        }

        $postTags = array_map(
            static fn (array $t): string => (string) $t['name'],
            $this->model->tags((int) $post->id())
        );

        $blogSettings = $this->blogSettingsModel->findByBlogId((int) $blog->id());
        $workflowEnabled = !empty($blogSettings['workflow_enabled']);

        $canRequestReview = $workflowEnabled
            && in_array($blogRole, ['author', 'contributor', 'owner', 'editor'], true)
            && in_array($workflowState, ['draft', 'needs_changes'], true);

        // Surface the most recent review feedback so the author sees it without visiting the review page
        $latestReview = $workflowEnabled ? $this->reviewModel->findLatestByPost((int) $post->id()) : null;

        return $this->view([
            'post' => $postArray,
            'blog' => $blog->toArray(),
            'postUrl' => $postUrl,
            'workflowState' => $workflowState,
            'status' => $status,
            'blogRole' => $blogRole,
            'workflowEnabled' => $workflowEnabled,
            'canRequestReview' => $canRequestReview,
            'latestReview' => $latestReview,
            'categories' => $this->categoryModel->getByBlogId((int) $blog->id()),
            'allTags' => $this->tagModel->getByBlogId((int) $blog->id()),
            'postTags' => $postTags,
        ]);
    }

    /**
     * Show post review screen with workflow permissions.
     *
     * @param  string  $id  Post ID
     */
    public function review(string $id): Response
    {
        $user = auth()->user();
        $post = $this->getPost((int) $id);
        $blog = $post->blog();

        Gate::authorize('reviewPost', $post, $user);

        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);
        $workflowState = $post->workflowState() ?? 'draft';
        $status = $post->status();
        $currentUserId = (int) $user['id'];

        // Lazy stale-reviewer guard: evaluated on page load, not at role-change time.
        $this->workflowService->checkStaleReviewer(
            postId: (int) $id,
            post: $post,
            ownerId: (int) $blog->ownerId()
        );

        $reviewers = $this->postReviewerModel->findByPost((int) $id);

        // Auto-claim / lock: first reviewer to open an unassigned pending post claims it.
        // Owners/editors don't auto-claim — they can supervise without locking the post.
        $blogSettings = $this->blogSettingsModel->findByBlogId((int) $blog->id());
        $workflowEnabled = !empty($blogSettings['workflow_enabled']);
        $isReviewerRole = $blogRole === 'reviewer';
        $postNeedsReview = $status === 'pending' || in_array($workflowState, ['in_review', 'needs_changes'], true);

        if ($workflowEnabled
            && $isReviewerRole
            && $postNeedsReview
            && empty($reviewers)
            && $currentUserId !== (int) $post->authorId()) {

            $this->workflowService->assignReviewer(
                postId: (int) $id,
                reviewerId: $currentUserId,
                assignedBy: $currentUserId,
                postAuthorId: (int) $post->authorId()
            );

            if ($workflowState !== 'in_review') {
                try {
                    $this->workflowService->submitForReview((int) $id, $currentUserId);
                    $workflowState = 'in_review';
                } catch (\RuntimeException $e) {
                    error_log('Auto submitForReview on claim failed: '.$e->getMessage());
                }
            }

            $reviewers = $this->postReviewerModel->findByPost((int) $id);
            $post = $this->getPost((int) $id); // re-fetch updated state
        }

        $reviews = $this->reviewModel->findByPost((int) $id);

        // Lock detection: is this post claimed by someone *other* than the current user?
        $currentReviewerIds = array_map('intval', array_column($reviewers, 'reviewer_id'));
        $isSelfAssigned = in_array($currentUserId, $currentReviewerIds, true);
        $lockedByOther = !empty($reviewers) && !$isSelfAssigned;
        $lockedByReviewer = $lockedByOther ? ($reviewers[0]['reviewer_username'] ?? 'another reviewer') : null;
        $lockedAt = $lockedByOther ? ($reviewers[0]['assigned_at'] ?? null) : null;

        // Reviewers see a read-only lock screen when someone else has claimed it.
        // Owners/editors can still take action (supervisory override).
        $reviewerLocked = $lockedByOther && $isReviewerRole;

        $canRequestReview = false; // Auto-triggered by status=pending now; no manual button.

        $canMarkNeedsChanges = !$reviewerLocked
            && in_array($blogRole, ['reviewer', 'editor', 'owner'], true)
            && in_array($workflowState, ['in_review', 'approved'], true);

        $canApprove = !$reviewerLocked
            && in_array($blogRole, ['reviewer', 'editor', 'owner'], true)
            && in_array($workflowState, ['in_review', 'needs_changes'], true);

        $canPublish = !$reviewerLocked
            && in_array($blogRole, ['editor', 'owner'], true)
            && in_array($workflowState, ['approved'], true);

        $canResetToDraft = !$reviewerLocked
            && in_array($blogRole, ['editor', 'owner'], true)
            && in_array($workflowState, ['approved'], true);

        // Self-assign button no longer needed: auto-claim handles it on page load.
        $canSelfAssign = false;
        $canAssignReviewer = !$reviewerLocked && in_array($blogRole, ['editor', 'owner'], true);

        // Dropdown candidates for editor/owner: reviewer-capable users not yet assigned, not the author
        $availableReviewers = [];
        if ($canAssignReviewer) {
            $blogUsers = $this->blogModel->getBlogUsers((int) $blog->id());
            foreach ($blogUsers as $blogUser) {
                if ((int) $blogUser['user_id'] !== $post->authorId()
                    && in_array($blogUser['role'], ['reviewer', 'editor', 'owner'], true)
                    && !in_array((int) $blogUser['user_id'], $currentReviewerIds, true)) {
                    $availableReviewers[] = $blogUser;
                }
            }
        }

        return $this->view([
            'post' => $post->toArray(),
            'blog' => $blog->toArray(),
            'workflowState' => $workflowState,
            'status' => $status,
            'blogRole' => $blogRole,
            'isAdmin' => auth()->hasRole('administrator'),
            'currentUserId' => $currentUserId,
            'canRequestReview' => $canRequestReview,
            'canMarkNeedsChanges' => $canMarkNeedsChanges,
            'canApprove' => $canApprove,
            'canPublish' => $canPublish,
            'canResetToDraft' => $canResetToDraft,
            'canAssignReviewer' => $canAssignReviewer,
            'canSelfAssign' => $canSelfAssign,
            'reviewerLocked' => $reviewerLocked,
            'lockedByReviewer' => $lockedByReviewer,
            'lockedAt' => $lockedAt,
            'reviewers' => $reviewers,
            'reviews' => $reviews,
            'availableReviewers' => $availableReviewers,
        ]);
    }

    /**
     * Toggle a post as its blog's featured headline (one per blog).
     *
     * @param  string  $id  Post ID
     */
    public function feature(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('update', $post, $user);

        $isFeatured = (bool) ($post->toArray()['is_featured'] ?? false);
        $this->model->setFeatured((int) $id, (int) $post->blogId(), !$isFeatured);

        audit()->log(
            $user['id'],
            $isFeatured ? 'post.unfeatured' : 'post.featured',
            'post',
            (int) $id,
            [],
            $this->request->ip()
        );

        $this->flash('success', $isFeatured ? 'Removed from the homepage.' : 'Featured on the homepage.');

        return $this->redirectBack();
    }

    /**
     * Update post.
     *
     * @param  string  $id  Post ID
     */
    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('update', $post, $user);

        $validator = $this->validateOrFail([
            'title' => 'required|title|min:2|max:100',
            'status' => 'in:'.implode(',', PostModel::STATUSES),
            'content' => 'required|max:60000',
            'excerpt' => 'required|max:300',
            'timezone' => 'timezone',
            'published_at' => 'datetime:d.m.y H:i',
            'remove_featured_image' => 'boolean',
            'comments_enabled' => 'boolean',
        ]);

        $newData = $validator->validated();
        $newData['comments_enabled'] = !empty($newData['comments_enabled']) ? 1 : 0;

        $timezone = $newData['timezone'] ?? 'UTC';

        // Normalize published_at to UTC for comparison
        if (!empty($newData['published_at'])) {
            $newData['published_at'] = $this->normalizePublishedAt(
                $newData['published_at'],
                $timezone
            );
        }

        // Build original data for comparison
        $originalData = [
            'title' => $post->title(),
            'slug' => $post->slug(),
            'content' => $post->content(),
            'excerpt' => $post->excerpt(),
            'status' => $post->status(),
            'timezone' => $post->timezone(),
            'published_at' => $post->publishedAt(),
            'comments_enabled' => $post->comments_enabled(),
            'blog_id' => $post->blogId(),
        ];

        // Validate status transitions
        $oldStatus = $originalData['status'];
        $newStatus = $newData['status'];

        if ($oldStatus !== $newStatus) {
            if (!isset(PostModel::STATUS_TRANSITIONS[$oldStatus])
                || !in_array($newStatus, PostModel::STATUS_TRANSITIONS[$oldStatus], true)) {
                $this->flash('error', "Cannot change status from '{$oldStatus}' to '{$newStatus}'.");

                return $this->redirectBack();
            }
        }

        // Detect changed fields
        $data = array_diff_assoc($newData, $originalData);

        if (isset($data['remove_featured_image'])) {
            unset($data['remove_featured_image']);
        }

        $blog = $post->blog();

        // Handle featured image upload
        $featuredImagePath = $this->handleFeaturedImageUpload($user['id'], $blog);
        if ($featuredImagePath) {
            $data['featured_image'] = $featuredImagePath;
        }

        // Handle explicit featured image removal
        if (($this->request->post['remove_featured_image'] ?? '0') === '1') {
            $data['featured_image'] = null;

            // Delete physical file
            $oldImagePath = $post->toArray()['featured_image'] ?? null;
            if ($oldImagePath) {
                $fullPath = ROOT_PATH.'/public'.$oldImagePath;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }

        // Set published_at when transitioning to published
        if ($newStatus === 'published' && $oldStatus !== 'published' && empty($data['published_at'])) {
            $utcNow = new DateTime('now', new DateTimeZone('UTC'));
            $data['published_at'] = $utcNow->format('Y-m-d H:i:s');
        }

        $blogId = (int) $post->blogId();

        // Category — only write it when it actually changed.
        $newCategoryId = $this->resolveCategoryId($blogId);
        $currentCategoryId = isset($post->toArray()['category_id']) ? (int) $post->toArray()['category_id'] : null;
        if ($newCategoryId !== $currentCategoryId) {
            $data['category_id'] = $newCategoryId;
        }

        // Tags sync independently of the field diff above.
        $tagsChanged = $this->tagModel->syncForPost((int) $id, $blogId, $this->parsePostTags());

        $this->model->setFeatured((int) $id, $blogId, !empty($this->request->post['is_featured'])); // TODO: optimize security

        // Update if changes detected
        if (!empty($data)) {
            $this->model->update((int) $id, $data);

            audit()->log(
                $user['id'],
                'post.updated',
                'post',
                (int) $id,
                array_intersect_key($data, array_flip(['title', 'status', 'published_at'])),
                $this->request->ip()
            );

            $this->flash('success', 'Post updated successfully.');
        } elseif ($tagsChanged) {
            $this->flash('success', 'Post updated successfully.');
        } else {
            $this->flash('info', 'No changes detected.');
        }

        // Auto-trigger review pipeline when author moves status to pending,
        // or re-triggers when resubmitting after needs_changes.
        // Replaces the old standalone "Submit for Review" button.
        $currentWorkflowState = $post->workflowState();
        $shouldSubmit = $newStatus === 'pending'
            && (
                $oldStatus !== 'pending'
                || $currentWorkflowState === 'needs_changes'
            )
            && $currentWorkflowState !== 'in_review';

        if ($shouldSubmit) {
            $blogSettings = $this->blogSettingsModel->findByBlogId($blogId);
            if (!empty($blogSettings['workflow_enabled'])) {
                try {
                    $this->workflowService->submitForReview((int) $id, (int) $user['id']);
                } catch (\RuntimeException $e) {
                    // Non-fatal: post status was saved as pending even if workflow trigger failed
                    error_log('Auto submitForReview failed for post '.$id.': '.$e->getMessage());
                }
            }
        }

        return $this->redirect("/dashboard/post/{$id}/edit");
    }

    /**
     * Resolve the submitted category_id, but only if it belongs to this blog.
     *
     * Returns null for "no category" or anything that isn't a real category of
     * the blog — so you can't slip in another blog's category id.
     */
    private function resolveCategoryId(int $blogId): ?int
    {
        $raw = $this->request->post['category_id'] ?? '';
        if ($raw === '' || $raw === null) {
            return null;
        }

        $id = (int) $raw;

        return $this->categoryModel->findForBlog($id, $blogId) ? $id : null;
    }

    /**
     * Split the comma-separated tags field into trimmed names.
     *
     * @return string[]
     */
    private function parsePostTags(): array
    {
        $raw = (string) ($this->request->post['tags'] ?? '');

        $names = array_map('trim', explode(',', $raw));

        return array_values(array_filter($names, static fn (string $n): bool => $n !== ''));
    }

    /**
     * Autosave post (AJAX endpoint).
     *
     * Delegates to PostAutosaveService for complex save logic.
     *
     * @return Response JSON response
     */
    public function autosave(): Response
    {
        $user = auth()->user();

        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $slugRule = 'slug|min:2|max:100|unique:posts,slug';
        if (!empty($this->request->post['id'])) {
            $slugRule .= ','.(int) $this->request->post['id'];
        }

        try {
            $validator = $this->validator($this->request->post);
            $validator->rules([
                'id' => 'integer',
                'title' => 'required|title|min:2|max:100',
                'slug' => $slugRule,
                'status' => 'in:'.implode(',', PostModel::STATUSES),
                'content' => 'required|max:60000',
                'excerpt' => 'required|max:300',
                'timezone' => 'timezone',
                'published_at' => 'datetime:d.m.y H:i',
            ]);

            if ($validator->fails()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $postId = (int) $validated['id'] ?? null;

            // Delegate to service
            $result = $this->autosaveService->save($validated, (int) $user['id'], $postId);

            $statusCode = $result['success'] ? 200 : 400;

            return $this->json($result, $statusCode);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show post deletion confirmation.
     *
     * @param  string  $id  Post ID
     */
    public function delete(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);
        Gate::authorize('delete', $post, $user);

        return $this->view('post.delete', [
            'post' => $post->toArray(),
        ]);
    }

    /**
     * Delete post permanently.
     *
     * @param  string  $id  Post ID
     */
    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);
        Gate::authorize('delete', $post, $user);

        $postData = $post->toArray();

        $this->model->delete((int) $id);

        audit()->log(
            $user['id'],
            'post.deleted',
            'post',
            (int) $id,
            ['title' => $postData['title'], 'slug' => $postData['slug']],
            $this->request->ip()
        );

        $this->flash('success', 'Post deleted successfully.');

        return $this->redirect('/dashboard');
    }

    /**
     * Apply a bulk action (publish / draft / archive / delete) to many posts.
     *
     * Each post is authorized individually. Unauthorized or missing posts are
     * silently skipped so a single bad id doesn't break the whole batch.
     */
    public function bulk(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $action = (string) ($this->request->post['bulk_action'] ?? '');
        $ids = array_filter(array_map('intval', (array) ($this->request->post['post_ids'] ?? [])));

        $allowed = ['publish', 'draft', 'review', 'archive', 'delete'];
        if (!in_array($action, $allowed, true) || empty($ids)) {
            $this->flash('error', 'No posts selected or invalid action.');

            return $this->redirect('/dashboard/post');
        }

        $applied = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $post = $this->model->findResource($id);
            if ($post === false) {
                $skipped++;
                continue;
            }

            $gateAction = $action === 'delete' ? 'delete' : 'publish';
            if (!Gate::allows($gateAction, $post, $user)) {
                $skipped++;
                continue;
            }

            match ($action) {
                'publish' => $this->model->updateStatus($id, 'published'),
                'draft' => $this->model->updateStatus($id, 'draft'),
                'review' => $this->model->updateStatus($id, 'pending'),
                'archive' => $this->model->updateStatus($id, 'archived'),
                'delete' => $this->model->delete($id),
            };

            $blogSettings = $this->blogSettingsModel->findByBlogId($post->blogId());
            if (!empty($blogSettings['workflow_enabled'])) {
                try {
                    $this->workflowService->submitForReview((int) $id, (int) $user['id']);
                } catch (\RuntimeException $e) {
                    // Non-fatal: post status was saved as pending even if workflow trigger failed
                    error_log('Auto submitForReview failed for post '.$id.': '.$e->getMessage());
                }
            }

            audit()->log(
                $user['id'],
                "post.bulk_{$action}",
                'post',
                $id,
                [],
                $this->request->ip()
            );

            $applied++;
        }

        $msg = "{$applied} post(s) updated";
        if ($skipped > 0) {
            $msg .= ", {$skipped} skipped";
        }
        $this->flash('success', $msg.'.');

        return $this->redirect('/dashboard/post');
    }

    /**
     * Publish post (requires approved workflow state).
     *
     * @param  string  $id  Post ID
     */
    public function publish(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);
        Gate::authorize('publish', $post, $user);

        // Enforce workflow precondition
        if (!in_array($post->workflowState(), ['approved'], true)) {
            $this->flash('error', 'Post must be approved before publishing.');

            return $this->redirect('/dashboard');
        }

        $this->model->updateStatus((int) $id, 'published');

        audit()->log(
            $user['id'],
            'post.published',
            'post',
            (int) $id,
            ['workflow_state' => $post->workflowState()],
            $this->request->ip()
        );

        $this->flash('success', 'Post published successfully.');

        return $this->redirect('/dashboard');
    }

    /**
     * Unpublish post (revert to approved state).
     *
     * @param  string  $id  Post ID
     */
    public function unpublish(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);
        Gate::authorize('publish', $post, $user);

        $this->model->unpublishPost((int) $id);
        $this->model->transitionWorkflow((int) $id, 'approved', $user['id']);

        audit()->log(
            $user['id'],
            'post.unpublished',
            'post',
            (int) $id,
            ['status' => 'draft', 'workflow_state' => 'approved'],
            $this->request->ip()
        );

        $this->flash('success', 'Post unpublished successfully.');

        return $this->redirect('/dashboard');
    }

    /**
     * Revert post to draft status.
     *
     * @param  string  $id  Post ID
     */
    public function draft(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);
        Gate::authorize('publish', $post, $user);

        $this->model->updateStatus((int) $id, 'draft');

        audit()->log(
            $user['id'],
            'post.reverted_to_draft',
            'post',
            (int) $id,
            [],
            $this->request->ip()
        );

        $this->flash('success', 'Post reverted to draft.');

        return $this->redirect('/dashboard');
    }

    /**
     * Archive post.
     *
     * @param  string  $id  Post ID
     */
    public function archive(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);
        Gate::authorize('publish', $post, $user);

        $this->model->updateStatus((int) $id, 'archived');

        audit()->log(
            $user['id'],
            'post.archived',
            'post',
            (int) $id,
            [],
            $this->request->ip()
        );

        $this->flash('success', 'Post archived successfully.');

        return $this->redirect('/dashboard');
    }

    /**
     * Request review (author/contributor → reviewer).
     *
     * @param  string  $id  Post ID
     */
    public function requestReview(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('update', $post, $user);

        $this->workflowService->submitForReview((int) $id, $user['id']);

        audit()->log(
            $user['id'],
            'post.review_requested',
            'post',
            (int) $id,
            ['workflow_state' => 'in_review'],
            $this->request->ip()
        );

        $this->dispatchSubmittedForReview($post, (string) ($user['username'] ?? ''));

        $this->flash('success', 'Post submitted for review.');

        return $this->redirect("/dashboard/posts/{$id}/edit");
    }

    /**
     * Review post decision.
     *
     * @param  string  $id  Post ID
     */
    public function reviewDecision(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $validator = $this->validateOrFail([
            'decision' => 'in:needs_changes,approved',
            'feedback' => 'max:300',
        ]);

        $data = $validator->validated();

        if ($data['decision'] === 'approved') {
            return $this->approve($id);
        } elseif ($data['decision'] === 'needs_changes') {
            return $this->markNeedsChanges($id);
        } else {
            $this->flash('error', 'Invalid review decision.');

            return $this->redirectBack();
        }
    }

    /**
     * Approve post (reviewer/editor).
     *
     * @param  string  $id  Post ID
     */
    public function approve(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('approve', $post, $user);

        $feedback = trim((string) ($this->request->postParam('feedback') ?? ''));
        $this->workflowService->approve((int) $id, $user['id'], $feedback);

        audit()->log(
            $user['id'],
            'post.approved',
            'post',
            (int) $id,
            ['workflow_state' => 'approved'],
            $this->request->ip()
        );

        $this->dispatchReviewDecision($post, 'post.approved', (string) ($user['username'] ?? ''), $feedback);

        $this->flash('success', 'Post approved.');

        return $this->redirect("/dashboard");
    }

    /**
     * Mark post as needing changes.
     *
     * @param  string  $id  Post ID
     */
    public function markNeedsChanges(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('markAsNeedsChanges', $post, $user);

        $feedback = trim((string) ($this->request->postParam('feedback') ?? ''));
        $this->workflowService->requestChanges((int) $id, $user['id'], $feedback);

        audit()->log(
            $user['id'],
            'post.marked_needs_changes',
            'post',
            (int) $id,
            ['workflow_state' => 'needs_changes'],
            $this->request->ip()
        );

        $this->dispatchReviewDecision($post, 'post.needs_changes', (string) ($user['username'] ?? ''), $feedback);

        $this->flash('info', 'Post marked as needing changes.');

        return $this->redirect('/dashboard');
    }

    /**
     * Notify reviewers when a post enters the review queue.
     *
     * Assigned-reviewer path → personal notification to that reviewer.
     * Unassigned path → broadcast to everyone on the blog who can pick it up
     * (owner, editor, reviewer). Failures are logged but never bubble — the
     * workflow transition succeeded, the user shouldn't see a 500.
     */
    private function dispatchSubmittedForReview(PostResource $post, string $authorUsername): void
    {
        try {
            $blog = $post->blog();
            $payload = [
                'post_id'         => $post->id(),
                'post_title'      => $post->title(),
                'post_slug'       => $post->slug(),
                'blog_id'         => $blog->id(),
                'blog_name'       => $blog->name(),
                'blog_slug'       => $blog->slug(),
                'author_username' => $authorUsername,
            ];

            $assignments = $this->postReviewerModel->findByPost($post->id());
            if (!empty($assignments)) {
                foreach ($assignments as $a) {
                    $rid = (int) ($a['reviewer_id'] ?? 0);
                    if ($rid > 0) {
                        $this->notifications->dispatch($rid, 'post.submitted', $payload);
                    }
                }

                return;
            }

            // No reviewer claimed yet — let everyone who could pick it up know.
            $recipients = $this->blogModel->getActiveUsersWithRoles(
                (int) $blog->id(),
                ['owner', 'editor', 'reviewer']
            );
            $authorId = $post->authorId();
            foreach ($recipients as $r) {
                $uid = (int) $r['user_id'];
                if ($uid === $authorId) {
                    continue; // don't notify the author about their own submission
                }
                $this->notifications->dispatch($uid, 'post.submitted_unassigned', $payload);
            }
        } catch (\Throwable $e) {
            error_log('Notify on submit-for-review failed: '.$e->getMessage());
        }
    }

    /**
     * Notify the author after a reviewer decision (approve or needs_changes).
     */
    private function dispatchReviewDecision(PostResource $post, string $type, string $reviewerUsername, string $feedback): void
    {
        try {
            $authorId = $post->authorId();
            if ($authorId <= 0) {
                return;
            }

            $blog = $post->blog();
            $payload = [
                'post_id'           => $post->id(),
                'post_title'        => $post->title(),
                'post_slug'         => $post->slug(),
                'blog_id'           => $blog->id(),
                'blog_name'         => $blog->name(),
                'blog_slug'         => $blog->slug(),
                'reviewer_username' => $reviewerUsername,
                'feedback'          => $feedback,
            ];

            $this->notifications->dispatch($authorId, $type, $payload);
        } catch (\Throwable $e) {
            error_log('Notify on review decision failed: '.$e->getMessage());
        }
    }

    /**
     * Reset workflow to draft.
     *
     * @param  string  $id  Post ID
     */
    public function resetWorkflowToDraft(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('update', $post, $user);

        $this->workflowService->resetToDraft((int) $id, $user['id']);

        audit()->log(
            $user['id'],
            'post.workflow_reset',
            'post',
            (int) $id,
            ['workflow_state' => 'draft'],
            $this->request->ip()
        );

        $this->flash('success', 'Workflow reset to draft.');

        return $this->redirect("/dashboard/posts/{$id}/edit");
    }

    /**
     * Assign a reviewer to a post.
     *
     * Owner and editor can assign any reviewer. A reviewer may only self-assign.
     *
     * @param  string  $id  Post ID
     */
    public function assignReviewer(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('assignReviewer', $post, $user);

        $reviewerId = (int) ($this->request->postParam('reviewer_id') ?? 0);

        if ($reviewerId <= 0) {
            $this->flash('error', 'Please select a reviewer.');

            return $this->redirectBack();
        }

        $blog = $post->blog();
        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);

        // Reviewers may only self-assign
        if ($blogRole === 'reviewer' && $reviewerId !== (int) $user['id']) {
            $this->flash('error', 'Reviewers may only assign themselves.');

            return $this->redirectBack();
        }

        $this->workflowService->assignReviewer(
            postId: (int) $id,
            reviewerId: $reviewerId,
            assignedBy: (int) $user['id'],
            postAuthorId: (int) $post->authorId(),
        );

        audit()->log(
            $user['id'],
            'post.reviewer_assigned',
            'post',
            (int) $id,
            ['reviewer_id' => $reviewerId],
            $this->request->ip()
        );

        $this->flash('success', 'Reviewer assigned.');

        return $this->redirectBack();
    }

    /**
     * Per-blog review queue.
     *
     * Lists every post in workflow_state in_review or needs_changes for one
     * blog, with assignment info. Anyone with a reviewer-capable role on the
     * blog (reviewer, editor, owner) or site admin may see this.
     *
     * @param  string  $blogId  Target blog
     */
    public function reviewQueue(string $blogId): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);

        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);
        $isAdmin  = auth()->hasRole('administrator');
        if (!$isAdmin && !in_array($blogRole, ['reviewer', 'editor', 'owner'], true)) {
            throw new PageNotFoundException('Review queue not available.');
        }

        $posts = $this->model->findReviewQueueForBlog((int) $blog->id());

        return $this->view('post.reviewQueue', [
            'blog'  => $blog->toArray(),
            'posts' => $posts,
            'blogRole' => $blogRole,
            'isAdmin'  => $isAdmin,
            'currentUserId' => (int) $user['id'],
        ]);
    }

    /**
     * Unassign a reviewer from a post.
     *
     * Editor/owner may unassign anyone. A reviewer may release themselves
     * — same authority axis as assignReviewer's self-assign rule, in reverse.
     *
     * @param  string  $id  Post ID
     */
    public function unassignReviewer(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        $reviewerId = (int) ($this->request->postParam('reviewer_id') ?? 0);
        if ($reviewerId <= 0) {
            $this->flash('error', 'Missing reviewer.');

            return $this->redirectBack();
        }

        $blog = $post->blog();
        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);
        $isAdmin = auth()->hasRole('administrator');
        $isSelfRelease = $reviewerId === (int) $user['id'];

        $canUnassignOthers = $isAdmin || in_array($blogRole, ['owner', 'editor'], true);
        if (!$canUnassignOthers && !$isSelfRelease) {
            $this->flash('error', 'Only the assigned reviewer, an editor, or the owner can unassign.');

            return $this->redirectBack();
        }

        if (!$this->postReviewerModel->unassign((int) $id, $reviewerId)) {
            $this->flash('info', 'That reviewer was already unassigned.');

            return $this->redirectBack();
        }

        audit()->log(
            $user['id'],
            'post.reviewer_unassigned',
            'post',
            (int) $id,
            ['reviewer_id' => $reviewerId, 'self_release' => $isSelfRelease],
            $this->request->ip()
        );

        $this->flash('success', $isSelfRelease ? 'You released this post back to the queue.' : 'Reviewer unassigned.');

        return $this->redirectBack();
    }

    /**
     * Get post resource or throw 404.
     *
     * @param  int  $id  Post ID
     *
     * @throws PageNotFoundException
     */
    private function getPost(int $id): PostResource
    {
        $post = $this->model->findResource($id);

        if ($post === false) {
            throw new PageNotFoundException("Post with ID: '$id' not found.");
        }

        return $post;
    }

    /**
     * Get post by slug or throw 404.
     *
     * @param  string  $slug  Post slug
     * @return array Post data
     *
     * @throws PageNotFoundException
     */
    private function getPostBySlug(string $slug): array
    {
        $post = $this->model->findBySlug($slug);

        if ($post === false) {
            throw new PageNotFoundException("Post with slug: '$slug' not found.");
        }

        return $post;
    }

    /**
     * Get blog resource or throw 404.
     *
     * @param  int  $id  Blog ID
     *
     * @throws PageNotFoundException
     */
    private function getBlog(int $id): \App\Resources\BlogResource
    {
        $blog = $this->blogModel->getBlog($id);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '$id' not found.");
        }

        return $blog;
    }

    /**
     * Handle featured image upload.
     *
     * @param  int  $userId  User ID (for temp cleanup)
     * @param  \App\Resources\BlogResource  $blog  Blog resource
     * @return string|null Featured image path or null
     */
    private function handleFeaturedImageUpload(int $userId, \App\Resources\BlogResource $blog): ?string
    {
        // If the user picked from the media library, prefer that — the file
        // already lives at a permanent URL and is already indexed.
        $picked = trim((string) ($this->request->post['featured_image_library_url'] ?? ''));
        if ($picked !== '') {
            // Make sure brand-new picks (e.g. someone pasted a URL by hand)
            // end up in the library.
            $this->mediaService->register((int) $blog->id(), $userId, $picked, 'post_image');

            return $picked;
        }

        $uploadedFiles = $this->uploader->getUploadedFiles(
            $this->request->post['uploaded_featured_image_files'] ?? []
        );

        // Take first file only
        if (empty($uploadedFiles[0])) {
            return null;
        }

        [$dir, $baseUrl] = $this->uploader->userBlogPostPath($blog->ownerId(), $blog->id());

        try {
            $path = $this->uploader->moveTempToBranding(
                $uploadedFiles[0],
                $blog->ownerId(),
                $blog->id(),
                'featured_image',
                $dir,
                $baseUrl
            );

            $this->uploader->cleanupTempFiles($userId);

            if ($path) {
                $this->mediaService->register((int) $blog->id(), $userId, $path, 'post_image');
            }

            return $path;

        } catch (\Throwable $e) {
            error_log("Featured image upload failed for blog {$blog->id()}: ".$e->getMessage());
            $this->flash('error', 'Featured image upload failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Normalize published_at datetime to UTC database format.
     *
     * Convert user-inputted datetime (in their timezone) to UTC for database storage.
     * Ensures accurate change detection when comparing old and new data.
     *
     * @param  string  $publishedAt  Datetime in format 'd.m.y H:i'
     * @param  string  $timezone  User's timezone (e.g., 'Europe/Athens')
     * @return string Normalized datetime in UTC 'Y-m-d H:i:s'
     */
    private function normalizePublishedAt(string $publishedAt, string $timezone): string
    {
        try {
            $userTz = new DateTimeZone($timezone);
            $dt = DateTime::createFromFormat('d.m.y H:i', $publishedAt, $userTz);

            if ($dt === false) {
                error_log("Failed to parse published_at: {$publishedAt}");

                return $publishedAt;
            }

            $dt->setTimezone(new DateTimeZone('UTC'));

            return $dt->format('Y-m-d H:i:s');

        } catch (\Exception $e) {
            error_log('Timezone conversion error: '.$e->getMessage());

            return $publishedAt;
        }
    }
}
