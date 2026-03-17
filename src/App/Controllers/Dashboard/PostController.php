<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserPreferencesModel;
use App\Resources\PostResource;
use App\Services\PostAutosaveService;
use App\Services\UploadService;
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
        private PostAutosaveService $autosaveService
    ) {}

    /**
     * List user's posts with filters.
     *
     * @return Response
     */
    public function index(): Response
    {
        $user = auth()->user();

        // Fetch user's blogs for filter dropdown
        $blogs = $this->blogModel->getBlogsByOwnerId($user['id']);
        $blogSlugs = array_column($blogs, 'blog_slug', 'id');

        // Extract filters from query parameters
        $blogId = isset($this->request->get['blog_id']) ? (int) $this->request->get['blog_id'] : null;
        $status = $this->request->get['status'] ?? '';
        $q = trim($this->request->get['q'] ?? '');

        // Validate blogId belongs to user
        $validBlogIds = array_column($blogs, 'id');
        if ($blogId !== null && !in_array($blogId, $validBlogIds, true)) {
            $blogId = null;
        }

        // Fetch filtered posts
        $posts = $this->model->findByAuthorWithFilters($user['id'], $blogId, $status, $q);

        return $this->view([
            'posts' => $posts,
            'user' => $user,
            'blogs' => $blogs,
            'blog_id' => $blogId,
            'blog_slug' => $blogSlugs,
            'status' => $status,
            'q' => $q,
        ]);
    }

    /**
     * Show single post with relationships.
     *
     * @param string $slug Post slug
     * @return Response
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
     *
     * @return Response
     */
    public function new(): Response
    {
        $user = auth()->user();
        $userId = (int) $user['id'];
        $defaultBlogId = $this->preference->getDefaultBlogId($userId);

        if (empty($defaultBlogId)) {
            $this->flash('error', 'Default blog has not been set');
            return $this->redirect('/dashboard');
        }

        $blog = $this->getBlog($defaultBlogId);
        Gate::authorize('createPost', $blog, $user);

        return $this->view([
            'blog' => $blog->toArray(),
        ]);
    }

    /**
     * Create new post.
     *
     * @return Response
     */
    public function create(): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $defaultBlogId = $this->preference->getDefaultBlogId($user['id']);
        $blog = $this->getBlog($defaultBlogId);

        Gate::authorize('createPost', $blog, $user);

        $validator = $this->validateOrFail([
            'title' => 'required|title|min:2|max:50',
            'slug' => 'required|slug|min:2|max:50|unique:posts,slug',
            'status' => 'in:' . implode(',', PostModel::STATUSES),
            'content' => 'required|max:10000',
            'excerpt' => 'required|max:200',
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

        // Handle featured image upload
        $featuredImagePath = $this->handleFeaturedImageUpload($user['id'], $blog);
        if ($featuredImagePath) {
            $data['featured_image'] = $featuredImagePath;
        }

        $okInsert = $this->model->insert($data);
        $postId = $this->model->getInsertID();

        if ($okInsert) {
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
     * @param string $id Post ID
     * @return Response
     */
    public function edit(string $id): Response
    {
        $user = auth()->user();
        $post = $this->getPost((int) $id);
        $blog = $post->blog();

        Gate::authorize('view', $post, $user);

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

        $postUrl = base_url() . '/blog/' . $blog->toArray()['blog_slug'] . '/' . $post->toArray()['slug'];

        breadcrumbs()->replaceLast('Edit Post: ' . $post->id());

        $postArray = $post->toArray();
        if ($displayDate) {
            $postArray['published_at'] = $displayDate;
        }

        return $this->view([
            'post' => $postArray,
            'blog' => $blog->toArray(),
            'postUrl' => $postUrl,
            'workflowState' => $workflowState,
            'status' => $status,
            'blogRole' => $blogRole,
        ]);
    }

    /**
     * Show post review screen with workflow permissions.
     *
     * @param string $id Post ID
     * @return Response
     */
    public function review(string $id): Response
    {
        $user = auth()->user();
        $post = $this->getPost((int) $id);
        $blog = $post->blog();

        Gate::authorize('view', $post, $user);

        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);
        $workflowState = $post->workflowState() ?? 'draft';
        $status = $post->status();

        // Calculate workflow permissions for UI
        $canRequestReview = in_array($blogRole, ['author', 'contributor', 'owner'], true)
            && in_array($workflowState, ['draft', 'needs_changes'], true);

        $canMarkNeedsChanges = in_array($blogRole, ['reviewer', 'editor', 'owner'], true)
            && in_array($workflowState, ['in_review', 'approved'], true);

        $canApprove = in_array($blogRole, ['reviewer', 'editor', 'owner'], true)
            && in_array($workflowState, ['in_review', 'needs_changes'], true);

        $canPublish = in_array($blogRole, ['editor', 'owner'], true)
            && in_array($workflowState, ['approved'], true);

        $canResetToDraft = in_array($blogRole, ['editor', 'owner'], true)
            && in_array($workflowState, ['approved'], true);

        return $this->view([
            'post' => $post->toArray(),
            'blog' => $blog->toArray(),
            'workflowState' => $workflowState,
            'status' => $status,
            'blogRole' => $blogRole,
            'canRequestReview' => $canRequestReview,
            'canMarkNeedsChanges' => $canMarkNeedsChanges,
            'canApprove' => $canApprove,
            'canPublish' => $canPublish,
            'canResetToDraft' => $canResetToDraft,
        ]);
    }

    /**
     * Update post.
     *
     * @param string $id Post ID
     * @return Response
     */
    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('update', $post, $user);

        $validator = $this->validateOrFail([
            'title' => 'required|title|min:2|max:50',
            'status' => 'in:' . implode(',', PostModel::STATUSES),
            'content' => 'required|max:10000',
            'excerpt' => 'required|max:200',
            'timezone' => 'timezone',
            'published_at' => 'datetime:d.m.y H:i',
            'remove_featured_image' => 'boolean',
        ]);

        $newData = $validator->validated();
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
                $fullPath = ROOT_PATH . '/public' . $oldImagePath;
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
        } else {
            $this->flash('info', 'No changes detected.');
        }

        return $this->redirect("/dashboard/post/{$id}/edit");
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

        $slugRule = 'slug|min:2|max:50|unique:posts,slug'; 
        if (!empty($this->request->post['id'])) {
            $slugRule .= ',' . (int) $this->request->post['id'];
        }
        

        try {
            $validator = $this->validator($this->request->post);
            $validator->rules([
                'id' => 'integer',
                'title' => 'required|title|min:2|max:50',
                'slug' => $slugRule,
                'status' => 'in:' . implode(',', PostModel::STATUSES),
                'content' => 'required|max:10000',
                'excerpt' => 'required|max:200',
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
            $postId = $validated['id'] ?? null;

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
     * @param string $id Post ID
     * @return Response
     */
    public function delete(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

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
     * @param string $id Post ID
     * @return Response
     */
    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

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
     * Publish post (requires approved workflow state).
     *
     * @param string $id Post ID
     * @return Response
     */
    public function publish(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

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
     * @param string $id Post ID
     * @return Response
     */
    public function unpublish(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

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
     * @param string $id Post ID
     * @return Response
     */
    public function draft(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

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
     * @param string $id Post ID
     * @return Response
     */
    public function archive(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $post = $this->getPost((int) $id);
        Gate::authorize('publish', $post, $user);

        $this->model->updateStatus((int) $id, 'archived');

        audit()->log(
            $user['id'],
            'post.archived',
            'post',
            (int) $id,
            null,
            $this->request->ip()
        );

        $this->flash('success', 'Post archived successfully.');

        return $this->redirect('/dashboard');
    }

    /**
     * Request review (author/contributor → reviewer).
     *
     * @param string $id Post ID
     * @return Response
     */
    public function requestReview(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('update', $post, $user);

        $this->model->transitionWorkflow((int) $id, 'in_review', $user['id']);

        audit()->log(
            $user['id'],
            'post.review_requested',
            'post',
            (int) $id,
            ['workflow_state' => 'in_review'],
            $this->request->ip()
        );

        $this->flash('success', 'Post submitted for review.');

        return $this->redirect("/dashboard/posts/{$id}/edit");
    }

    /**
     * Approve post (reviewer/editor).
     *
     * @param string $id Post ID
     * @return Response
     */
    public function approve(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('approve', $post, $user);

        $this->model->transitionWorkflow((int) $id, 'approved', $user['id']);

        audit()->log(
            $user['id'],
            'post.approved',
            'post',
            (int) $id,
            ['workflow_state' => 'approved'],
            $this->request->ip()
        );

        $this->flash('success', 'Post approved.');

        return $this->redirect("/dashboard/posts/{$id}/edit");
    }

    /**
     * Mark post as needing changes.
     *
     * @param string $id Post ID
     * @return Response
     */
    public function markNeedsChanges(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('markAsNeedsChanges', $post, $user);

        $this->model->transitionWorkflow((int) $id, 'needs_changes', $user['id']);

        audit()->log(
            $user['id'],
            'post.marked_needs_changes',
            'post',
            (int) $id,
            ['workflow_state' => 'needs_changes'],
            $this->request->ip()
        );

        $this->flash('info', 'Post marked as needing changes.');

        return $this->redirect("/dashboard/posts/{$id}/edit");
    }

    /**
     * Reset workflow to draft.
     *
     * @param string $id Post ID
     * @return Response
     */
    public function resetWorkflowToDraft(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('update', $post, $user);

        $this->model->transitionWorkflow((int) $id, 'draft', $user['id']);

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
     * Get post resource or throw 404.
     *
     * @param int $id Post ID
     * @return PostResource
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
     * @param string $slug Post slug
     * @return array Post data
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
     * @param int $id Blog ID
     * @return \App\Resources\BlogResource
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
     * @param int $userId User ID (for temp cleanup)
     * @param \App\Resources\BlogResource $blog Blog resource
     * @return string|null Featured image path or null
     */
    private function handleFeaturedImageUpload(int $userId, \App\Resources\BlogResource $blog): ?string
    {
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

            return $path;

        } catch (\Throwable $e) {
            error_log("Featured image upload failed for blog {$blog->id()}: " . $e->getMessage());
            $this->flash('error', 'Featured image upload failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize published_at datetime to UTC database format.
     *
     * Convert user-inputted datetime (in their timezone) to UTC for database storage.
     * Ensures accurate change detection when comparing old and new data.
     *
     * @param string $publishedAt Datetime in format 'd.m.y H:i'
     * @param string $timezone User's timezone (e.g., 'Europe/Athens')
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
            error_log('Timezone conversion error: ' . $e->getMessage());
            return $publishedAt;
        }
    }
}
