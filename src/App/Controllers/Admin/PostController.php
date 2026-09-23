<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Resources\SystemResource;
use App\Services\ExternalMediaGuard;
use App\Services\MediaService;
use App\Services\PostContentSanitizer;
use App\Services\PublicCacheInvalidator;
use App\ValueObjects\TableSort;
use Framework\Core\Response;
use Framework\Database;
use Framework\Exceptions\PageNotFoundException;

/**
 * Admin post management controller.
 *
 * Authorization is enforced by the /admin route group middleware
 * (auth + role:administrator) in config/routes.php.
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

        $result = $this->model->findAllForAdmin(
            $page, 20, $status, $q,
            $blogId > 0 ? $blogId : null,
            $featured, $visibility, $sort->orderBy()
        );

        return $this->view([
            'posts' => $result['data'],
            'pagination' => $result['pagination'],
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
        $blogs = $this->getBlogs();

        return $this->view('post.edit', [
            'post' => $post,
            'blogs' => $blogs,
        ]);
    }

    /**
     * Handle update submission
     */
    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $post = $this->getPost($id);

        $input = $this->validatePostInput();

        $rejected = $this->rejectOutsideMedia($input, (string) ($post['content'] ?? ''), (string) ($post['featured_image'] ?? ''));
        if ($rejected !== null) {
            return $rejected;
        }

        // Reversing a moderation hide belongs to its report case, where the
        // decision is recorded, so the edit form cannot republish the post.
        $staysHidden = ($post['status'] ?? '') === 'moderated';

        $data = [
            'title' => $input['title'],
            'slug' => $input['slug'] ?? $post['slug'],
            'content' => $input['content'],
            'excerpt' => $input['excerpt'] ?? null,
            'featured_image' => $input['featured_image'] ?? null,
            'status' => $staysHidden ? 'moderated' : $input['status'],
            'blog_id' => (int) $input['blog_id'],
        ];

        if ($this->model->update($id, $data)) {
            $this->flash('success', $staysHidden
                ? 'Post updated. It stays hidden because of a moderation decision; restore it from its report case.'
                : 'Post updated.');

            return $this->redirectToList('/admin/posts');
        }

        return $this->view('post.edit', [
            'errors' => ['Could not update the post. Please try again.'],
            'post' => $data,
        ]);
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
