<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Helpers\TimezoneHelper;
use App\Models\AnalyticsStatsModel;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CategoryModel;
use App\Models\PostListingModel;
use App\Models\PostModel;
use App\Models\PostTranslationModel;
use App\Models\ReviewModel;
use App\Models\TagModel;
use App\Models\UserPreferencesModel;
use App\Presenters\PostActionPresenter;
use App\Resources\BlogResource;
use App\Resources\PostResource;
use App\Services\ActiveBlog;
use App\Services\ExternalMediaGuard;
use App\Services\LocaleRegistry;
use App\Services\MediaService;
use App\Services\PostAuthorService;
use App\Services\PostAutosaveService;
use App\Services\PostContentSanitizer;
use App\Services\SubscriberNotificationService;
use App\Services\UploadService;
use App\Services\WorkflowService;
use App\ValueObjects\AnalyticsScope;
use App\ValueObjects\PostFieldRules;
use DateTime;
use DateTimeZone;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Dashboard post management controller.
 *
 * Handles CRUD, the Posts list for the active blog,
 * status changes, autosave, and featured images.
 */
final class PostController extends AppController
{
    /**
     * Validation rules for the optional SEO / social sharing fields.
     * Shared by create() and update() so the two paths can't drift.
     */
    private const SEO_RULES = PostFieldRules::SEO;

    public function __construct(
        private PostModel $model,
        private BlogModel $blogModel,
        private UserPreferencesModel $preference,
        private UploadService $uploader,
        private PostAutosaveService $autosaveService,
        private CategoryModel $categoryModel,
        private TagModel $tagModel,
        private WorkflowService $workflowService,
        private ReviewModel $reviewModel,
        private BlogSettingsModel $blogSettingsModel,
        private MediaService $mediaService,
        private SubscriberNotificationService $subscriberNotifier,
        private PostTranslationModel $translationModel,
        private LocaleRegistry $localeRegistry,
        private PostAuthorService $postAuthors,
        private ExternalMediaGuard $mediaGuard,
        private PostContentSanitizer $contentSanitizer,
        private AnalyticsStatsModel $analyticsStats,
        private ActiveBlog $activeBlog,
        private PostListingModel $postListing,
    ) {}

    /**
     * Posts in the active blog. Owners and editors see every post in it,
     * everyone else who writes there sees their own.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $userId = (int) $user['id'];

        $blog = $this->activeBlogOrNull($userId);
        if ($blog === null) {
            return $this->redirect('/dashboard/blog');
        }
        Gate::authorize('listPosts', $blog, $user);

        $blogId = $blog->id();
        $managesAll = Gate::allows('managePosts', $blog, $user);
        $authorScope = $managesAll ? null : $userId;

        $status = trim((string) $this->request->getParam('status', ''));
        $q = trim((string) $this->request->getParam('q', ''));
        $page = max(1, (int) $this->request->getParam('page', 1));

        // The "Needs Changes" tab is workflow-state-driven.
        $workflowFilter = '';
        if ($status === 'needs_changes') {
            $workflowFilter = 'needs_changes';
            $status = '';
        }

        $sort = (string) $this->request->getParam('sort', 'newest');
        if (!in_array($sort, ['newest', 'oldest', 'title_asc', 'title_desc'], true)) {
            $sort = 'newest';
        }

        // Categories and tags belong to one blog; ids from another blog are dropped.
        $rawCat = (int) $this->request->getParam('category', 0);
        $categoryId = $rawCat > 0 && $this->categoryModel->findForBlog($rawCat, $blogId) ? $rawCat : null;
        $rawTag = (int) $this->request->getParam('tag', 0);
        $activeTag = $rawTag > 0 ? $this->tagModel->findForBlog($rawTag, $blogId) : null;
        $tagId = $activeTag ? $rawTag : null;

        $result = $this->postListing->findByAuthorWithFiltersPagination(
            authorId: $authorScope,
            page: $page,
            perPage: 12,
            blogId: $blogId,
            status: $status,
            searchQuery: $q,
            sort: $sort,
            categoryId: $categoryId,
            tagId: $tagId,
            workflowState: $workflowFilter,
        );

        $views = $this->postViews($blog, $result['data']);
        foreach ($result['data'] as &$p) {
            $p['tags'] = $this->model->tags((int) $p['id']);
            $p['can'] = $this->postAbilities($p, $blog, $user);
            $p['views'] = (int) ($views[(int) $p['id']]['views'] ?? 0);
        }
        unset($p);

        $counts = $this->postListing->countsByStatusForAuthor(
            authorId: $authorScope,
            blogId: $blogId,
            searchQuery: $q,
            categoryId: $categoryId,
            tagId: $tagId,
        );

        $settings = $this->blogSettingsModel->findByBlogId($blogId);
        $returnToken = $this->issueReturnToken($this->buildReturnUrl('/dashboard/post'), ['/dashboard/post']);

        return $this->view([
            'posts' => $result['data'],
            'pagination' => $result['pagination'],
            'returnToken' => $returnToken,
            'blogSlug' => $blog->slug(),
            'managesAll' => $managesAll,
            'canCreate' => Gate::allows('createPost', $blog, $user),
            'status' => $workflowFilter !== '' ? $workflowFilter : $status,
            'q' => $q,
            'sort' => $sort,
            'counts' => $counts,
            'blogCategories' => $this->categoryModel->getByBlogId($blogId),
            'blogTags' => $this->tagModel->getByBlogId($blogId),
            'categoryId' => $categoryId,
            'tagId' => $tagId,
            'activeTag' => $activeTag,
            'workflowEnabled' => !empty($settings['workflow_enabled']),
        ]);
    }

    /**
     * The active blog, or null when none is set or the user can no longer see it.
     */
    private function activeBlogOrNull(int $userId): ?BlogResource
    {
        $blogId = (int) ($this->preference->getDefaultBlogId($userId) ?? 0);
        $blog = $blogId > 0 ? $this->blogModel->getBlog($blogId) : false;

        return $blog !== false && Gate::allows('view', $blog, auth()->user()) ? $blog : null;
    }

    /**
     * What the card may offer for one post, decided by the same policies its actions use.
     *
     * @param  array<string, mixed>  $post  List row
     * @param  array<string, mixed>  $user
     * @return array{update: bool, publish: bool, delete: bool}
     */
    private function postAbilities(array $post, BlogResource $blog, array $user): array
    {
        $resource = new PostResource($post, $blog);

        return [
            'update' => Gate::allows('update', $resource, $user),
            'publish' => Gate::allows('publish', $resource, $user),
            'delete' => Gate::allows('delete', $resource, $user),
        ];
    }

    /**
     * Show post creation form.
     */
    public function new(): Response
    {
        $user = auth()->user();
        $userId = (int) $user['id'];

        // ?blog_id= names the blog to write in; it becomes the active blog once authorized.
        $requestedBlogId = (int) $this->request->getParam('blog_id', 0);
        $blogId = $requestedBlogId > 0
            ? $requestedBlogId
            : (int) ($this->preference->getDefaultBlogId($userId) ?? 0);

        if ($blogId <= 0) {
            $this->flash('error', 'Choose a blog to write in first.');

            return $this->redirect('/dashboard/blog');
        }

        $blog = $this->getBlog($blogId);
        Gate::authorize('createPost', $blog, $user);
        $this->activeBlog->follow($user, $blog);

        $blogSettings = $this->blogSettingsModel->findByBlogId((int) $blog->id());
        $workflowEnabled = !empty($blogSettings['workflow_enabled']);

        return $this->view([
            'blog' => $blog->toArray(),
            'backUrl' => $this->backUrlPath(),
            'categories' => $this->categoryModel->getByBlogId((int) $blog->id()),
            'allTags' => $this->tagModel->getByBlogId((int) $blog->id()),
            'postTags' => [],
            'workflowEnabled' => $workflowEnabled,
            'canSetVisibility' => $this->canSetVisibility($blog, $userId),
            'actions' => PostActionPresenter::for('draft', $this->workflowService->canPublish($blog, $userId), $workflowEnabled),
        ] + $this->authorFieldData($blog, $user, (int) $user['id']));
    }

    /**
     * Create new post.
     */
    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();

        // For default-blog and shared-blog flows.
        $formBlogId = (int) ($this->request->postParam('blog_id') ?? 0);
        $blogId = $formBlogId > 0
            ? $formBlogId
            : (int) ($this->preference->getDefaultBlogId($user['id']) ?? 0);

        $blog = $this->getBlog($blogId);

        Gate::authorize('createPost', $blog, $user);

        $validator = $this->validateOrFail([
            'published_at' => 'max:20',
        ] + PostFieldRules::CORE + self::SEO_RULES);

        $data = $validator->validated();
        $data['content'] = $this->contentSanitizer->clean((string) $data['content']);
        $data['comments_enabled'] = !empty($data['comments_enabled']) ? 1 : 0;
        $data = $this->normalizeSeoFields($data);

        // Convert published_at to UTC before resolving the intent, because
        // whether the date is in the future decides publish versus schedule.
        if (!empty($data['published_at'])) {
            $utc = TimezoneHelper::localToUtc((string) $data['published_at'], (string) ($data['timezone'] ?? 'UTC'));
            if ($utc === null) {
                return $this->rejectField('published_at', TimezoneHelper::INVALID_PUBLISH_DATE);
            }
            $data['published_at'] = $utc;
        } else {
            unset($data['published_at']);
        }

        $data['status'] = $this->workflowService->constrainStatus(
            $this->requestedStatus('draft', $data['published_at'] ?? null),
            $this->workflowService->canPublish($blog, (int) $user['id']),
            (int) $blog->id()
        );

        $data['blog_id'] = $blog->id();
        $data['slug'] = $this->model->availableSlug((int) $blog->id(), $data['slug']);

        if ($visibility = $this->requestedVisibility($blog, (int) $user['id'])) {
            $data['visibility'] = $visibility;
        }

        try {
            $data['author_id'] = $this->postAuthors->resolve($blog, $user, $this->request->postParam('author_id'), (int) $user['id']);
        } catch (\InvalidArgumentException $e) {
            return $this->rejectField('author_id', $e->getMessage());
        }

        $outside = $this->mediaGuard->newExternalSources((string) $data['content']);
        if ($outside !== []) {
            return $this->rejectField('content', $this->mediaGuard->rejectionMessage($outside));
        }

        $data['category_id'] = $this->resolveCategoryId((int) $blog->id());

        [$featuredImagePath, $imageFailure] = $this->storeFeaturedImageSafely((int) $user['id'], $blog);
        if ($featuredImagePath) {
            $data['featured_image'] = $featuredImagePath;
        }

        $okInsert = $this->model->insert($data);
        $postId = $this->model->getInsertID();

        if ($okInsert) {
            $this->tagModel->syncForPost($postId, (int) $blog->id(), $this->parsePostTags());
            $this->model->setFeatured($postId, (int) $blog->id(), !empty($this->request->post['is_featured']));

            // No-op unless the post was created straight into published
            $this->subscriberNotifier->notifyPostPublished($postId);

            $this->workflowService->submitIfRequested((int) $postId, (int) $blog->id(), $data['status'], 'draft', (int) $user['id']);

            audit()->log(
                $user['id'],
                'post.created',
                'post',
                $postId,
                ['title' => $data['title'], 'status' => $data['status']],
                $this->request->ip()
            );

            if ($imageFailure !== null) {
                return $this->redirectAfterImageFailure($postId, $imageFailure);
            }

            $this->flash('success', 'Post saved.');

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
        $this->activeBlog->follow($user, $blog);

        // Convert published_at to display timezone
        $displayDate = null;
        if (!empty($post->publishedAt())) {
            $dt = new DateTime($post->publishedAt(), new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone($post->timezone()));
            $displayDate = $dt->format('d.m.y H:i');
        }

        $workflowState = $post->workflowState();
        $status = $post->status();

        $postUrl = base_url().'/blog/'.$blog->toArray()['blog_slug'].'/'.$post->toArray()['slug'];

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

        $canRequestReview = $workflowEnabled && in_array($workflowState, ['draft', 'needs_changes'], true);

        // Surface the most recent review feedback so the author sees it without visiting the review page
        $latestReview = $workflowEnabled ? $this->reviewModel->findLatestByPost((int) $post->id()) : null;

        // Localized posts: the edit page grows language tabs when the blog opted in.
        $translationsEnabled = !empty($blogSettings['translations_enabled']);

        return $this->view([
            'post' => $postArray,
            'blog' => $blog->toArray(),
            'postAnalytics' => $this->postAnalytics($blog, (int) $post->id(), (string) ($postArray['status'] ?? '')),
            'translationsEnabled' => $translationsEnabled,
            'translations' => $translationsEnabled ? $this->translationModel->findForPost((int) $post->id()) : [],
            'defaultLocale' => (string) ($blogSettings['default_locale'] ?? 'en'),
            'availableLocales' => $this->localeRegistry->supported(),
            'postUrl' => $postUrl,
            // Not the Referer: after a save that is this very page.
            'backUrl' => $this->returnTokenUrl($this->request->getParam('r'), '/dashboard/post', ['/dashboard/post']),
            'workflowState' => $workflowState,
            'status' => $status,
            'workflowEnabled' => $workflowEnabled,
            'canRequestReview' => $canRequestReview,
            'latestReview' => $latestReview,
            'categories' => $this->categoryModel->getByBlogId((int) $blog->id()),
            'allTags' => $this->tagModel->getByBlogId((int) $blog->id()),
            'postTags' => $postTags,
            'returnToken' => $this->request->getParam('r'),
            'canSetVisibility' => $this->canSetVisibility($blog, (int) $user['id']),
            ...$this->authorFieldData($blog, $user, $post->authorId()),
            'actions' => PostActionPresenter::for(
                $status,
                $this->workflowService->canPublish($blog, (int) $user['id']),
                $workflowEnabled,
                $post->publishedAt() !== null && strtotime($post->publishedAt().' UTC') > time(),
                $workflowState
            ),
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
            'content' => 'required|max:60000',
            'excerpt' => 'max:300',
            'timezone' => 'timezone',
            'published_at' => 'max:20',
            'remove_featured_image' => 'boolean',
            'comments_enabled' => 'boolean',
        ] + self::SEO_RULES);

        $newData = $validator->validated();
        $newData['content'] = $this->contentSanitizer->clean((string) $newData['content']);
        $newData['comments_enabled'] = !empty($newData['comments_enabled']) ? 1 : 0;
        $newData = $this->normalizeSeoFields($newData);

        $timezone = $newData['timezone'] ?? 'UTC';

        // Normalize published_at to UTC for comparison
        if (!empty($newData['published_at'])) {
            $utc = TimezoneHelper::localToUtc((string) $newData['published_at'], $timezone);
            if ($utc === null) {
                return $this->rejectField('published_at', TimezoneHelper::INVALID_PUBLISH_DATE);
            }
            $newData['published_at'] = $utc;
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
            'visibility' => $post->toArray()['visibility'] ?? 'public',
        ];

        if ($visibility = $this->requestedVisibility($post->blog(), (int) $user['id'])) {
            $newData['visibility'] = $visibility;
        }

        // SEO/social fields join the same change-detection diff.
        $postSnapshot = $post->toArray();
        foreach (array_keys(self::SEO_RULES) as $seoField) {
            $originalData[$seoField] = $postSnapshot[$seoField] ?? null;
        }

        $newData['status'] = $this->workflowService->constrainStatus(
            $this->requestedStatus(
                (string) $originalData['status'],
                $newData['published_at'] ?? $originalData['published_at'] ?? null
            ),
            $this->workflowService->canPublish($post->blog(), (int) $user['id']),
            (int) $post->blogId(),
            $originalData['status']
        );

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

        try {
            $authorId = $this->postAuthors->resolve($blog, $user, $this->request->postParam('author_id'), $post->authorId());
        } catch (\InvalidArgumentException $e) {
            return $this->rejectField('author_id', $e->getMessage());
        }

        $outside = $this->mediaGuard->newExternalSources((string) $newData['content'], $post->content());
        if ($outside !== []) {
            return $this->rejectField('content', $this->mediaGuard->rejectionMessage($outside));
        }

        if ($authorId !== $post->authorId()) {
            $data['author_id'] = $authorId;
        }

        [$featuredImagePath, $imageFailure] = $this->storeFeaturedImageSafely((int) $user['id'], $blog);
        if ($featuredImagePath) {
            $data['featured_image'] = $featuredImagePath;
        }

        // The file stays in the media library, only the post lets go of it.
        if (($this->request->post['remove_featured_image'] ?? '0') === '1' && !$featuredImagePath) {
            $data['featured_image'] = null;
        }

        // Set published_at when transitioning to published
        if ($newStatus === 'published' && $oldStatus !== 'published' && empty($data['published_at'])) {
            $utcNow = new DateTime('now', new DateTimeZone('UTC'));
            $data['published_at'] = $utcNow->format('Y-m-d H:i:s');
        }

        $blogId = (int) $post->blogId();

        // Category
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
            $this->workflowService->concludeReview($post, $newStatus, (int) $user['id']);
            $this->model->update((int) $id, $data);

            // No-op unless the post just became published and was never announced
            $this->subscriberNotifier->notifyPostPublished((int) $id);

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

        $this->workflowService->submitIfRequested((int) $id, $blogId, $newStatus, (string) $post->workflowState(), (int) $user['id']);

        if ($imageFailure !== null) {
            return $this->redirectAfterImageFailure((int) $id, $imageFailure);
        }

        return $this->redirect($this->editUrl((int) $id));
    }

    /**
     * Resolve the submitted category_id, but only if it belongs to this blog.
     *
     * Returns null for "no category" or anything that isn't a real category of
     * the blog.
     */
    private function resolveCategoryId(int $blogId): ?int
    {
        $raw = $this->request->post['category_id'] ?? '';
        if ($raw === '') {
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
        if (!csrf()->isTokenValid($this->request->postParam('_token'))) {
            return $this->json(['success' => false, 'error' => 'Invalid CSRF token.'], 419);
        }

        $user = auth()->user();

        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        try {
            $validator = $this->validator($this->request->post);
            $validator->rules([
                'id' => 'integer',
                'title' => 'required|title|min:2|max:100',
                'slug' => 'slug|min:2|max:100',
                // No status rule: autosave never sends one, and the "in" rule
                // rejects an absent value rather than skipping it.
                'content' => 'required|max:60000',
                'excerpt' => 'max:300',
                'timezone' => 'timezone',
                'published_at' => 'max:20',
            ]);

            if ($validator->fails()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $postId = !empty($validated['id']) ? (int) $validated['id'] : null;

            $blogId = null;
            if ($postId !== null) {
                $post = $this->model->findResource($postId);
                if ($post !== false && !Gate::allows('update', $post, $user)) {
                    return $this->json(['success' => false, 'error' => 'You cannot edit this post.'], 403);
                }
            } else {
                $blogId = (int) ($this->request->post['blog_id'] ?? 0)
                    ?: (int) ($this->preference->getDefaultBlogId((int) $user['id']) ?? 0);
                $blog = $blogId > 0 ? $this->blogModel->getBlog($blogId) : null;
                if (!$blog || !Gate::allows('createPost', $blog, $user)) {
                    return $this->json(['success' => false, 'error' => 'You cannot write in this blog.'], 403);
                }
            }

            $result = $this->autosaveService->save($validated, (int) $user['id'], $postId, $blogId);

            $statusCode = $result['success'] ? 200 : 400;

            return $this->json($result, $statusCode);

        } catch (\Throwable $e) {
            error_log('Autosave failed: '.$e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'The server could not save your draft. Your text is still here, so keep the page open and try saving again.',
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
        $moderated = 0;

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

            if ($action !== 'delete' && $post->status() === 'moderated') {
                $moderated++;
                continue;
            }

            // Pending only means something when there is a queue to put the post in.
            if ($action === 'review'
                && !$this->workflowService->submitIfRequested($id, (int) $post->blogId(), 'pending', (string) $post->workflowState(), (int) $user['id'])) {
                $skipped++;
                continue;
            }

            if ($action === 'publish') {
                $this->workflowService->concludeReview($post, 'published', (int) $user['id']);
            }

            match ($action) {
                'publish' => $this->model->updateStatus($id, 'published'),
                'draft' => $this->model->updateStatus($id, 'draft'),
                'archive' => $this->model->updateStatus($id, 'archived'),
                'delete' => $this->model->delete($id),
                'review' => null,
            };

            if ($action === 'publish') {
                $this->subscriberNotifier->notifyPostPublished((int) $id);
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
        if ($moderated > 0) {
            $msg .= ", {$moderated} left as they are because a moderator hid them";
        }
        $this->flash('success', $msg.'.');

        return $this->redirect('/dashboard/post');
    }

    /**
     * Whether the user may decide how widely this blog's posts show.
     *
     * Same permission as the quick visibility actions, so the editor never
     * offers more than the post list does.
     */
    private function canSetVisibility(BlogResource $blog, int $userId): bool
    {
        return $blog->userCan($userId, 'publish_blog_posts');
    }

    /**
     * The visibility the editor form asked for, or null to leave it alone.
     *
     * Null covers an absent field, an unknown value and a user without the
     * permission, so a hand-built request cannot widen a post's reach.
     */
    private function requestedVisibility(BlogResource $blog, int $userId): ?string
    {
        $requested = (string) ($this->request->postParam('visibility') ?? '');

        if (!in_array($requested, PostModel::VISIBILITIES, true) || !$this->canSetVisibility($blog, $userId)) {
            return null;
        }

        return $requested;
    }

    /**
     * All-time views for each listed post, keyed by post id.
     *
     * @param  list<array<string, mixed>>  $posts
     * @return array<int, array{views: int, visitors: int}>
     */
    private function postViews(\App\Resources\BlogResource $blog, array $posts): array
    {
        $ids = array_map('intval', array_column($posts, 'id'));

        return $this->analyticsStats->lifetimeForPosts((int) $blog->id(), $ids);
    }

    /**
     * How the post is doing, for the editor sidebar: its views so far, and this
     * week's views, read rate and where readers came from. Null when the viewer
     * can't open Insights.
     *
     * @return array{views: int, weekViews: int, weekReadRate: ?float, weekSources: array<string, list<array<string, mixed>>>, url: string}|null
     */
    private function postAnalytics(\App\Resources\BlogResource $blog, int $postId, string $status): ?array
    {
        if ($status !== 'published' || !Gate::allows('viewAnalytics', $blog, auth()->user())) {
            return null;
        }

        $blogId = (int) $blog->id();
        $lifetime = $this->analyticsStats->lifetimeForPosts($blogId, [$postId])[$postId]
            ?? ['views' => 0, 'visitors' => 0];
        $today = new \DateTimeImmutable('today', new \DateTimeZone(blog_timezone($blogId)));
        [$from, $to] = [$today->modify('-6 days')->format('Y-m-d'), $today->format('Y-m-d')];
        $scope = AnalyticsScope::post($blogId, $postId);
        $week = $this->analyticsStats->totals($scope, $from, $to);

        return [
            'views' => $lifetime['views'],
            'weekViews' => $week['views'],
            'weekReadRate' => $week['views'] > 0 ? $week['read_views'] / $week['views'] : null,
            'weekSources' => [
                'source' => $this->analyticsStats->breakdown($scope, 'source', $from, $to, 1),
                'lexicon' => $this->analyticsStats->breakdown($scope, 'lexicon', $from, $to, 1),
                'channel' => $this->analyticsStats->breakdown($scope, 'channel', $from, $to, 3),
            ],
            'url' => lurl('/dashboard/blog/'.$blogId.'/insights/posts/'.$postId).'?range=7d',
        ];
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
        $picked = trim((string) ($this->request->post['featured_image_library_url'] ?? ''));
        if ($picked !== '') {
            if (!$this->mediaService->isLocalUploadUrl($picked)) {
                throw new \InvalidArgumentException('Featured images must come from your media library or an upload, not an outside address.');
            }

            $this->mediaService->register((int) $blog->id(), $userId, $picked, 'post_image');

            return $picked;
        }

        $uploadedFiles = $this->uploader->getUploadedFiles(
            $this->request->post['uploaded_featured_image_files'] ?? []
        );

        if (empty($uploadedFiles[0])) {
            return null;
        }

        return $this->mediaService->storeFeaturedImage((string) $uploadedFiles[0], $blog, $userId);
    }

    /**
     * Store the featured image without letting a failure throw away the post.
     *
     * @return array{0: string|null, 1: string|null} [stored path, reason it failed]
     */
    private function storeFeaturedImageSafely(int $userId, \App\Resources\BlogResource $blog): array
    {
        try {
            return [$this->handleFeaturedImageUpload($userId, $blog), null];
        } catch (\InvalidArgumentException $e) {
            return [null, $e->getMessage()];
        } catch (\Throwable $e) {
            error_log("Featured image failed for blog {$blog->id()}: ".$e->getMessage());

            return [null, 'The server could not store it. Please try again.'];
        }
    }

    /**
     * What the author field needs: the choices when the user may reassign, the
     * current author's handle either way.
     *
     * @param  array<string, mixed>  $user
     * @return array{canAssignAuthor: bool, authorOptions: array<int, string>, authorId: int, authorHandle: string}
     */
    private function authorFieldData(\App\Resources\BlogResource $blog, array $user, int $authorId): array
    {
        $candidates = $this->postAuthors->candidates($blog);
        $canAssign = Gate::allows('assignPostAuthor', $blog, $user);

        return [
            'canAssignAuthor' => $canAssign,
            'authorOptions' => $canAssign ? $candidates : [],
            'authorId' => $authorId,
            'authorHandle' => $candidates[$authorId] ?? (string) ($this->model->author($authorId)['handle'] ?? ''),
        ];
    }

    /**
     * Send the writer back to the form with one field's problem spelled out.
     */
    private function rejectField(string $field, string $message): Response
    {
        $oldInput = $this->request->all();
        unset($oldInput['_token']);
        $this->session->set('_old_input', $oldInput);
        $this->session->set('_errors', [$field => [$message]]);
        $this->flash('error', $message);

        return $this->redirectBack();
    }

    /**
     * Keep the writer on the post when its featured image did not save, saying why.
     */
    private function redirectAfterImageFailure(int $postId, string $reason): Response
    {
        $this->flash('error', 'Post saved, but the featured image could not be added. '.$reason);

        return $this->redirect($this->editUrl($postId));
    }

    /**
     * The editor for a post, carrying the list token so its Back link survives the save.
     */
    private function editUrl(int $postId): string
    {
        $returnToken = (string) ($this->request->postParam('r') ?? '');
        $query = $returnToken !== '' ? '?r='.rawurlencode($returnToken) : '';

        return "/dashboard/post/{$postId}/edit{$query}";
    }

    /**
     * Normalize the SEO / social fields for storage.
     *
     * Checkboxes arrive only when ticked, so the two robots flags are forced
     * to 0/1. Cleared text inputs arrive as empty strings and are stored as
     * NULL so the theme fallback chain (post > excerpt/title > blog) works.
     *
     * @param  array<string, mixed>  $data  Validated form data
     * @return array<string, mixed> Data with SEO fields normalized
     */
    private function normalizeSeoFields(array $data): array
    {
        $data['meta_noindex'] = !empty($data['meta_noindex']) ? 1 : 0;
        $data['meta_nofollow'] = !empty($data['meta_nofollow']) ? 1 : 0;

        $nullable = [
            'focus_keyword', 'meta_title', 'meta_description', 'canonical_url',
            'og_title', 'og_description', 'og_image', 'og_image_alt',
            'twitter_title', 'twitter_description', 'twitter_image',
        ];

        foreach ($nullable as $seoField) {
            if (isset($data[$seoField]) && trim((string) $data[$seoField]) === '') {
                $data[$seoField] = null;
            }
        }

        if (empty($data['twitter_card_type'])) {
            $data['twitter_card_type'] = 'summary_large_image';
        }

        return $data;
    }

    /**
     * Resolve the status the editor is asking for from the clicked button.
     *
     * The form submits an intent ("publish", "save_draft", ...) rather than a
     * status, so the buttons can stay readable while the mapping lives in one
     * place. Whatever comes back is still passed through
     * WorkflowService::constrainStatus(), which is what actually
     * enforces who may publish.
     *
     * The whitelist lives here rather than in the validation rules because a
     * form can be submitted with no submitter at all (pressing Enter in a
     * text field does it), and that should quietly mean "just save", not
     * reject the whole post.
     *
     * @param  string  $currentStatus  Status the post holds today
     * @param  string|null  $publishedAtUtc  Normalized publish date, when set
     * @return string The requested status
     */
    private function requestedStatus(string $currentStatus, ?string $publishedAtUtc): string
    {
        $intent = (string) ($this->request->postParam('intent') ?? '');

        if (!in_array($intent, PostActionPresenter::INTENTS, true)) {
            $intent = PostActionPresenter::INTENT_UPDATE;
        }

        $hasFutureDate = $publishedAtUtc !== null
            && strtotime($publishedAtUtc.' UTC') > time();

        return PostActionPresenter::statusForIntent($intent, $currentStatus, $hasFutureDate);
    }
}
