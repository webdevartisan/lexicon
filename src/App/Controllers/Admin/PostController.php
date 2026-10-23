<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\CategoryModel;
use App\Models\PostListingModel;
use App\Models\PostModel;
use App\Models\TagModel;
use App\Resources\SystemResource;
use App\Services\ExternalMediaGuard;
use App\Services\MediaService;
use App\Services\PostContentSanitizer;
use App\Services\PublicCacheInvalidator;
use App\Services\UploadService;
use App\ValueObjects\PageSize;
use App\ValueObjects\TableSort;
use Framework\Core\Response;
use Framework\Database;
use Framework\Exceptions\PageNotFoundException;

/**
 * Admin post management controller.
 *
 * Authorization is the managePosts area ability, checked for every action
 * by AppController::beforeAction().
 */
class PostController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'managePosts';

    public function __construct(
        private PostModel $model,
        private BlogModel $blogModel,
        protected Database $database,
        private PublicCacheInvalidator $publicCache,
        private ExternalMediaGuard $mediaGuard,
        private PostContentSanitizer $contentSanitizer,
        private MediaService $media,
        private PostListingModel $postListing,
        private CategoryModel $categories,
        private TagModel $tags,
        private UploadService $uploader,
    ) {}

    /**
     * Toggle the front page showcase flag on a post.
     *
     * This is the only gate between user content and the site front page,
     * so the change is audit logged and the cached front page is purged
     * immediately.
     */
    public function featureHome(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $post = $this->getPost($id);
        $on = !((int) ($post['featured_on_home'] ?? 0) === 1);

        if ($on && ($post['status'] !== 'published' || ($post['visibility'] ?? 'public') !== 'public')) {
            $this->flash('error', 'Only published, public posts can be featured on the front page.');

            return $this->redirectToList('/admin/posts');
        }

        $this->model->setFeaturedOnHome((int) $post['id'], $on);

        audit()->log(
            (int) auth()->user()['id'],
            $on ? 'post.featured_on_home' : 'post.unfeatured_from_home',
            'post',
            (int) $post['id'],
            ['title' => $post['title'] ?? ''],
            $this->request->ip()
        );

        $this->publicCache->purgeHome();
        $this->flash('success', $on
            ? 'Post is now featured on the front page.'
            : 'Post removed from the front page.');

        return $this->redirectToList('/admin/posts');
    }

    /**
     * Publish a post.
     */
    public function publish(string $id): Response
    {
        return $this->changeStatus($id, 'published', 'Post published.');
    }

    /**
     * Move a post back to draft.
     */
    public function draft(string $id): Response
    {
        return $this->changeStatus($id, 'draft', 'Post moved to draft.');
    }

    /**
     * Archive a post.
     */
    public function archive(string $id): Response
    {
        return $this->changeStatus($id, 'archived', 'Post archived.');
    }

    /**
     * Make a post publicly visible.
     */
    public function makePublic(string $id): Response
    {
        return $this->changeVisibility($id, 'public', 'Post is now public.');
    }

    /**
     * Restrict a post to its collaborators.
     */
    public function makePrivate(string $id): Response
    {
        return $this->changeVisibility($id, 'private', 'Post is now private.');
    }

    /**
     * Keep a post reachable only by direct link.
     */
    public function unlist(string $id): Response
    {
        return $this->changeVisibility($id, 'unlisted', 'Post is now unlisted.');
    }

    /**
     * Shared body for the three quick status actions above.
     *
     * Status changes on a moderated post belong to its report case; a quick
     * action here would either silently no-op against the model's own guard
     * or, worse, look like it worked.
     */
    private function changeStatus(string $id, string $status, string $successMessage): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $post = $this->getPost($id);

        if (($post['status'] ?? '') === 'moderated') {
            $this->flash('error', 'This post is hidden by a moderation decision. Change its status from the report case instead.');

            return $this->redirectToList('/admin/posts');
        }

        $this->model->updateStatus((int) $id, $status);

        audit()->log(
            (int) auth()->user()['id'],
            'post.status_changed',
            'post',
            (int) $id,
            ['status' => $status],
            $this->request->ip()
        );

        $this->flash('success', $successMessage);

        return $this->redirectToList('/admin/posts');
    }

    /**
     * Shared body for the three quick visibility actions above.
     */
    private function changeVisibility(string $id, string $visibility, string $successMessage): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $this->getPost($id);
        $this->model->updateVisibility((int) $id, $visibility);

        audit()->log(
            (int) auth()->user()['id'],
            'post.visibility_changed',
            'post',
            (int) $id,
            ['visibility' => $visibility],
            $this->request->ip()
        );

        $this->flash('success', $successMessage);

        return $this->redirectToList('/admin/posts');
    }

    /**
     * List posts across every blog with status filter, search, and paging.
     */
    public function index(): Response
    {
        $status = trim((string) ($this->request->get['status'] ?? ''));
        $q = trim((string) ($this->request->get['q'] ?? ''));
        $featured = trim((string) ($this->request->get['featured'] ?? ''));
        $visibility = trim((string) ($this->request->get['visibility'] ?? ''));
        $blogId = (int) ($this->request->get['blog_id'] ?? 0);
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        $sort = TableSort::fromRequest($this->request, [
            'id' => 'p.id',
            'title' => 'p.title',
            'blog' => 'b.blog_name',
            'author' => 'au.handle',
            'status' => 'p.status',
            'comments' => 'comment_count',
            'published' => 'p.published_at',
            'updated' => 'p.updated_at',
        ], defaultKey: 'updated', defaultDirection: 'desc', tiebreaker: 'p.id DESC',
            // Front-page picks lead the list, so an admin can see what the site
            // is currently showing without hunting for it through the pages.
            pinned: 'p.featured_on_home DESC');

        $perPage = PageSize::fromRequest($this->request, 20);
        $result = $this->postListing->findAllForAdmin(
            $page, $perPage, $status, $q,
            $blogId > 0 ? $blogId : null,
            $featured, $visibility, $sort->orderBy()
        );

        return $this->view([
            'posts' => $result['data'],
            'pagination' => $result['pagination'],
            'perPage' => $perPage,
            'status' => $status,
            'q' => $q,
            'featured' => $featured,
            'visibility' => $visibility,
            'blogId' => $blogId,
            'statusOptions' => PostModel::STATUSES,
            'visibilityOptions' => PostModel::VISIBILITIES,
            'blogOptions' => $this->blogModel->getAllForSelect(),
            'sort' => $sort,
            'canHandleReports' => Gate::allows('handleReports', SystemResource::class, auth()->user() ?? []),
        ]);
    }

    /**
     * Show a single post in admin
     */
    public function show(string $id): Response
    {
        $post = $this->getPost($id);

        return $this->view([
            'post' => $post,
        ]);
    }

    /**
     * Show new post form
     */
    public function new(): Response
    {
        $post['status'] = 'draft';

        $blogs = $this->getBlogs();

        return $this->view([
            'post' => $post,
            'blogs' => $blogs,
        ]);
    }

    /**
     * Handle new post submission
     */
    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $input = $this->validatePostInput();

        $rejected = $this->rejectOutsideMedia($input, '');
        if ($rejected !== null) {
            return $rejected;
        }

        $data = [
            'title' => $input['title'],
            'slug' => $input['slug'] ?? '',
            'content' => $input['content'],
            'excerpt' => $input['excerpt'] ?? null,
            'featured_image' => $input['featured_image'] ?? null,
            'status' => $input['status'],
            'blog_id' => (int) $input['blog_id'],
            'author_id' => auth()->user()['id'],
        ];

        if ($this->model->insert($data)) {
            $this->flash('success', 'Post created.');

            return $this->redirectToList('/admin/posts');
        }

        // On error, return form view again
        return $this->view([
            'errors' => ['Could not create the post. Please try again.'],
            'post' => $data,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(string $id): Response
    {
        $post = $this->getPost($id);
        $blogId = (int) $post['blog_id'];

        return $this->view('post.edit', [
            'post' => $post,
            'blogs' => $this->getBlogs(),
            'categoryChoices' => ['' => 'No category'] + array_column($this->categories->getByBlogId($blogId), 'name', 'id'),
            'blogTags' => $this->tags->getByBlogId($blogId),
            'postTagIds' => array_map(static fn (array $tag): int => (int) $tag['id'], $this->model->tags((int) $id)),
            'author' => $this->model->author((int) $post['author_id']),
        ]);
    }

    /**
     * Save the post's content, organisation, publishing state and SEO fields.
     *
     * Category and tags belong to one blog, so moving a post to another blog
     * clears them rather than leaving links into the wrong blog.
     */
    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $post = $this->getPost($id);
        $section = preg_replace('/[^a-z]/', '', (string) ($this->request->post['_section'] ?? '')) ?: 'content';
        $backTo = '/admin/posts/'.(int) $id.'/edit#'.$section;

        $input = $this->validatePostInput();

        $rejected = $this->rejectOutsideMedia($input, (string) ($post['content'] ?? ''), (string) ($post['featured_image'] ?? ''));
        if ($rejected !== null) {
            return $rejected;
        }

        $seo = $this->validateOrFail([
            'meta_title' => 'max:200',
            'meta_description' => 'max:500',
            'focus_keyword' => 'max:100',
            'canonical_url' => 'max:500',
            'og_title' => 'max:200',
            'og_description' => 'max:500',
        ])->validated();

        $canonical = trim((string) ($seo['canonical_url'] ?? ''));
        if ($canonical !== '' && filter_var($canonical, FILTER_VALIDATE_URL) === false) {
            $this->flash('error', 'The canonical URL must be a full address, such as https://example.com/post.');

            return $this->redirect($backTo);
        }

        // Reversing a moderation hide belongs to its report case, where the
        // decision is recorded, so the edit form cannot republish the post.
        $staysHidden = ($post['status'] ?? '') === 'moderated';
        $status = $staysHidden ? 'moderated' : $input['status'];
        $blogId = (int) $input['blog_id'];
        $moved = $blogId !== (int) $post['blog_id'];
        $submitted = $this->request->post;

        $visibility = (string) ($submitted['visibility'] ?? '');
        $categoryId = (int) ($submitted['category_id'] ?? 0);

        $data = [
            'title' => $input['title'],
            'slug' => $input['slug'] ?? $post['slug'],
            'content' => $input['content'],
            'excerpt' => $input['excerpt'] ?? null,
            'featured_image' => $this->resolveFeaturedImage($post, $blogId),
            'status' => $status,
            'blog_id' => $blogId,
            'visibility' => in_array($visibility, PostModel::VISIBILITIES, true) ? $visibility : $post['visibility'],
            'category_id' => !$moved && $categoryId > 0 && $this->categories->findForBlog($categoryId, $blogId) !== null ? $categoryId : null,
            'comments_enabled' => !empty($submitted['comments_enabled']) ? 1 : 0,
            'meta_title' => trim((string) ($seo['meta_title'] ?? '')) ?: null,
            'meta_description' => trim((string) ($seo['meta_description'] ?? '')) ?: null,
            'focus_keyword' => trim((string) ($seo['focus_keyword'] ?? '')) ?: null,
            'canonical_url' => $canonical ?: null,
            'meta_noindex' => !empty($submitted['meta_noindex']) ? 1 : 0,
            'meta_nofollow' => !empty($submitted['meta_nofollow']) ? 1 : 0,
            'og_title' => trim((string) ($seo['og_title'] ?? '')) ?: null,
            'og_description' => trim((string) ($seo['og_description'] ?? '')) ?: null,
        ];

        if ($status === 'published' && empty($post['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        if (!$this->model->update($id, $data)) {
            $this->flash('error', 'Could not update the post. Please try again.');

            return $this->redirect($backTo);
        }

        $wanted = array_map('intval', (array) ($submitted['tag_ids'] ?? []));
        $allowed = array_map(static fn (array $tag): int => (int) $tag['id'], $this->tags->getByBlogId($blogId));
        $this->tags->syncIdsForPost((int) $id, $moved ? [] : array_values(array_intersect($wanted, $allowed)));

        audit()->log((int) auth()->user()['id'], 'post.updated', 'post', (int) $id, ['title' => $data['title']], $this->request->ip());

        $this->flash('success', $staysHidden
            ? 'Post updated. It stays hidden because of a moderation decision; restore it from its report case.'
            : 'Post updated.');

        return $this->redirect($backTo);
    }

    /**
     * Show delete confirmation
     */
    public function delete(string $id): Response
    {
        $post = $this->getPost($id);

        return $this->view('post.delete', [
            'post' => $post,
        ]);
    }

    /**
     * Handle deletion
     */
    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $post = $this->getPost($id);

        $this->model->delete($id);

        audit()->log(
            (int) auth()->user()['id'],
            'post.deleted',
            'post',
            (int) $id,
            ['title' => $post['title'] ?? null, 'blog_id' => $post['blog_id'] ?? null],
            $this->request->ip()
        );

        $this->flash('success', 'Post deleted.');

        return $this->redirectToList('/admin/posts');
    }

    /**
     * The featured image after this save: a fresh upload, an explicit removal,
     * or what the post already had.
     *
     * A failed upload keeps the old image and says so, instead of throwing the
     * rest of the edit away.
     *
     * @param  array<string, mixed>  $post  The post as stored
     */
    private function resolveFeaturedImage(array $post, int $blogId): ?string
    {
        $current = $post['featured_image'] ?? null;

        if (($this->request->post['remove_featured_image'] ?? '') === '1') {
            $current = null;
        }

        $files = $this->uploader->getUploadedFiles($this->request->post['uploaded_featured_image_files'] ?? []);
        $blog = $files === [] ? null : $this->blogModel->getBlog($blogId);

        if ($blog === null || $blog === false) {
            return $current;
        }

        try {
            return $this->media->storeFeaturedImage((string) $files[0], $blog, (int) auth()->user()['id']);
        } catch (\Throwable $e) {
            error_log('Featured image failed for post in blog '.$blogId.': '.$e->getMessage());
            $this->flash('error', 'The featured image could not be stored, so the old one was kept.');

            return $current;
        }
    }

    /**
     * Refuse new outside images or embeds in the body, and a featured image that
     * is not one of this site's uploads unless the post already had it.
     *
     * @param  array<string, mixed>  $input  Validated fields
     */
    private function rejectOutsideMedia(array $input, string $previousContent, string $previousImage = ''): ?Response
    {
        $errors = [];

        $outside = $this->mediaGuard->newExternalSources((string) $input['content'], $previousContent);
        if ($outside !== []) {
            $errors['content'] = [$this->mediaGuard->rejectionMessage($outside)];
        }

        $image = trim((string) ($input['featured_image'] ?? ''));
        if ($image !== '' && $image !== $previousImage && !$this->media->isLocalUploadUrl($image)) {
            $errors['featured_image'] = ['The featured image must be one of this site\'s uploads, for example /uploads/....'];
        }

        if ($errors === []) {
            return null;
        }

        $this->session->set('_errors', $errors);
        $this->flash('error', implode(' ', array_merge(...array_values($errors))));

        return $this->redirectBack();
    }

    /**
     * Shared validation for create and update submissions.
     *
     * @return array<string, mixed> Validated input fields
     */
    private function validatePostInput(): array
    {
        $input = $this->validateOrFail([
            'title' => 'required|min:3|max:200',
            'slug' => 'max:220',
            'content' => 'required',
            'excerpt' => 'max:500',
            'featured_image' => 'max:255',
            'status' => 'required|in:draft,published,archived',
            'blog_id' => 'required|integer|exists:blogs,id',
        ])->validated();

        $input['content'] = $this->contentSanitizer->clean((string) $input['content']);

        return $input;
    }

    /**
     * Utility: fetch post or 404
     *
     * @return array<string, mixed> Post record
     */
    private function getPost(string $id): array
    {
        $post = $this->model->find($id);

        if (!$post) {
            throw new PageNotFoundException("Post with ID '$id' not found.");
        }

        return $post;
    }

    /**
     * @return array<int, array<string, mixed>> Blog rows for the form dropdown
     */
    private function getBlogs(): array
    {
        return $this->blogModel->getAllBlogsWithOwnerAndCounts();
    }
}
