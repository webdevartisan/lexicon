<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\BlogModel;
use App\Services\PublicCacheInvalidator;
use App\Services\ThemeService;
use App\ValueObjects\TableSort;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class BlogController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageBlogs';

    public function __construct(
        private BlogModel $blogModel,
        private PublicCacheInvalidator $publicCache,
        private ThemeService $themes
    ) {}

    /**
     * Toggle the explore page featured flag on a blog.
     *
     * Curation gate for a platform owned surface, so it is audit logged and
     * the cached explore page is purged right away.
     */
    public function featureExplore(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->blogModel->find($id);
        if (!$blog) {
            throw new PageNotFoundException('Blog not found.', 404);
        }

        $on = !((int) ($blog['is_featured'] ?? 0) === 1);

        if ($on && ($blog['status'] ?? '') !== 'published') {
            $this->flash('error', 'Only published blogs can be featured on the explore page.');

            return $this->redirectToList('/admin/blogs');
        }

        $this->blogModel->setExploreFeatured((int) $blog['id'], $on);

        audit()->log(
            (int) auth()->user()['id'],
            $on ? 'blog.featured_on_explore' : 'blog.unfeatured_from_explore',
            'blog',
            (int) $blog['id'],
            ['blog_name' => $blog['blog_name'] ?? ''],
            $this->request->ip()
        );

        $this->publicCache->purgeExplore();
        $this->flash('success', $on
            ? 'Blog is now featured on the explore page.'
            : 'Blog removed from the explore page featured section.');

        return $this->redirectToList('/admin/blogs');
    }

    public function index(): Response
    {
        $q = trim((string) $this->request->getParam('q', ''));
        $status = trim((string) $this->request->getParam('status', ''));
        $featured = trim((string) $this->request->getParam('featured', ''));
        $page = max(1, (int) $this->request->getParam('page', 1));

        $themes = $this->themes->available();

        // An installed theme wins over the "unset" sentinel, so a theme whose
        // directory really is called "none" stays selectable. Anything else is
        // dropped rather than passed through to the query.
        $theme = trim((string) $this->request->getParam('theme', ''));
        if (!isset($themes[$theme]) && $theme !== 'none') {
            $theme = '';
        }

        $sort = TableSort::fromRequest($this->request, [
            'id' => 'b.id',
            'name' => 'b.blog_name',
            'owner' => 'u.username',
            'posts' => 'post_count',
            'team' => 'author_count',
            'status' => 'b.status',
            'theme' => 'bs.theme',
            'created' => 'b.created_at',
        ], defaultKey: 'created', defaultDirection: 'desc', tiebreaker: 'b.id DESC');

        $result = $this->blogModel->findAllForAdmin($page, 20, $q, $status, $featured, $sort->orderBy(), $theme);

        $themeChoices = ['' => 'All themes', 'none' => 'No theme set'];
        foreach ($themes as $key => $meta) {
            $themeChoices[$key] = (string) $meta['name'];
        }

        return $this->view('blog.index', [
            'blogs' => $result['data'],
            'pagination' => $result['pagination'],
            'q' => $q,
            'status' => $status,
            'featured' => $featured,
            'theme' => $theme,
            'themeChoices' => $themeChoices,
            'statusOptions' => BlogModel::STATUSES,
            'sort' => $sort,
        ]);
    }

    public function new(): Response
    {
        return $this->view('blog.new');
    }

    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blogName = $this->request->post['blog_name'] ?? '';
        $description = $this->request->post['description'] ?? '';
        $blogSlug = trim((string) ($this->request->post['blog_slug'] ?? '')) ?: $this->generateSlug($blogName);

        try {
            $this->blogModel->insert([
                'blog_name' => $blogName,
                'blog_slug' => $blogSlug,
                'description' => $description,
                'is_active' => !empty($this->request->post['is_active']) ? 1 : 0,
                'owner_id' => auth()->user()['id'],
            ]);

            $blogId = $this->blogModel->getInsertID();

            audit()->log(
                (int) auth()->user()['id'],
                'blog.created',
                'blog',
                (int) $blogId,
                ['blog_name' => $blogName],
                $this->request->ip()
            );

            $this->flash('success', 'Blog created.');

            return $this->redirect("/admin/blogs/$blogId/show");
        } catch (\PDOException $e) {
            return $this->view('blog.new', [
                'error' => 'Blog slug already exists or database error',
                'old' => $this->request->post,
            ]);
        }
    }

    /**
     * Show edit form
     */
    public function edit(string $id): Response
    {
        $blog = $this->getBlog($id);

        return $this->view('blog.edit', [
            'blog' => $blog,
        ]);
    }

    /**
     * Handle update submission
     */
    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->getBlog($id);

        $data = [
            'blog_name' => $this->request->post['blog_name'] ?? $blog['blog_name'],
            'blog_slug' => $this->request->post['blog_slug'] ?? $blog['blog_slug'],
            'description' => $this->request->post['description'] ?? $blog['description'],
            // Unchecked checkboxes are absent from the payload, so absence means deactivate
            'is_active' => !empty($this->request->post['is_active']) ? 1 : 0,
        ];

        if ($this->blogModel->update($id, $data)) {
            $this->flash('success', 'Blog updated.');

            return $this->redirectToList('/admin/blogs');
        }

        return $this->view('blog.edit', [
            'errors' => ['Could not update the blog. Please try again.'],
            'blog' => $data,
        ]);
    }

    public function show(string $id): Response
    {
        $blog = $this->blogModel->getBlog((int) $id);

        if ($blog === false) {
            throw new PageNotFoundException("Blog with ID '$id' not found.");
        }

        $posts = $this->blogModel->getBlogPosts((int) $id);
        $blogUsers = $this->blogModel->getBlogUsers((int) $id);
        $availableUsers = $this->blogModel->getAvailableUsers((int) $id);

        return $this->view('blog.show', [
            'blog' => $blog->toArray(),
            'posts' => $posts,
            'blogUsers' => $blogUsers,
            'availableUsers' => $availableUsers,
        ]);
    }

    /**
     * Show delete confirmation
     */
    public function delete(string $id): Response
    {
        $blog = $this->getBlog($id);

        return $this->view('blog.delete', [
            'blog' => $blog,
        ]);
    }

    /**
     * Handle deletion
     */
    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->getBlog($id);

        $this->blogModel->delete($id);

        audit()->log(
            (int) auth()->user()['id'],
            'blog.deleted',
            'blog',
            (int) $id,
            ['blog_name' => $blog['blog_name'] ?? null],
            $this->request->ip()
        );

        $this->flash('success', 'Blog deleted.');

        return $this->redirectToList('/admin/blogs');
    }

    /**
     * @return array<string, mixed> Blog record as array
     */
    private function getBlog(string $id): array
    {
        $blog = $this->blogModel->getBlog((int) $id);

        if ($blog === false) {
            throw new PageNotFoundException("Blog with ID '$id' not found.");
        }

        return $blog->toArray();
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

        return substr($slug, 0, 200);
    }
}
