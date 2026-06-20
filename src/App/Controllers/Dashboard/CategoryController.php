<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\CategoryModel;
use App\Models\TagModel;
use App\Resources\BlogResource;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Manages a blog's categories and tags from the dashboard.
 *
 * One page, two taxonomies. Every action is scoped to the blog in the URL and
 * authorized against it, so you can only touch the taxonomy of a blog you can
 * edit. Add/rename/delete all come through a single POST with type + action,
 * the same way the collaborators screen works.
 */
final class CategoryController extends AppController
{
    public function __construct(
        private CategoryModel $categories,
        private TagModel $tags,
        private BlogModel $blogModel
    ) {}

    public function index(string $blogId): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('update', $blog, $user);

        return $this->view([
            'blog' => $blog->toArray(),
            'categories' => $this->categories->getByBlogId((int) $blogId),
            'tags' => $this->tags->getByBlogId((int) $blogId),
        ]);
    }

    /**
     * Handle add / rename / delete for either a category or a tag.
     */
    public function handle(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('update', $blog, $user);

        $type = (string) ($this->request->post['type'] ?? '');
        $action = (string) ($this->request->post['action'] ?? '');
        $name = trim((string) ($this->request->post['name'] ?? ''));
        $id = (int) ($this->request->post['id'] ?? 0);

        if (!in_array($type, ['category', 'tag'], true)) {
            $this->flash('error', 'Unknown item type.');

            return $this->back((int) $blogId);
        }

        $model = $type === 'category' ? $this->categories : $this->tags;
        $label = ucfirst($type);

        switch ($action) {
            case 'add':
                if ($name === '') {
                    $this->flash('error', "{$label} name is required.");
                    break;
                }
                if ($type === 'category') {
                    $this->categories->findOrCreateForBlog((int) $blogId, $name);
                } else {
                    $this->tags->findOrCreateForBlog((int) $blogId, $name);
                }
                $this->flash('success', "{$label} added.");
                break;

            case 'rename':
                if (!$model->findForBlog($id, (int) $blogId)) {
                    $this->flash('error', "{$label} not found.");
                    break;
                }
                if ($model->renameForBlog($id, (int) $blogId, $name)) {
                    $this->flash('success', "{$label} renamed.");
                } else {
                    $this->flash('error', "Couldn't rename — that name may already be in use.");
                }
                break;

            case 'delete':
                if (!$model->findForBlog($id, (int) $blogId)) {
                    $this->flash('error', "{$label} not found.");
                    break;
                }
                $model->delete($id);
                $this->flash('success', "{$label} deleted.");
                break;

            default:
                $this->flash('error', 'Unknown action.');
        }

        return $this->back((int) $blogId);
    }

    private function back(int $blogId): Response
    {
        return $this->redirect("/dashboard/blog/{$blogId}/categories");
    }

    private function getBlog(int $id): BlogResource
    {
        $blog = $this->blogModel->getBlog($id);
        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$id}' not found.");
        }

        return $blog;
    }
}
