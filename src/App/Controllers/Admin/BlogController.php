<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\BlogModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class BlogController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageBlogs';

    public function __construct(private BlogModel $blogModel) {}

    public function index(): Response
    {
        $q = trim((string) $this->request->getParam('q', ''));
        $page = max(1, (int) $this->request->getParam('page', 1));

        $result = $this->blogModel->findAllForAdmin($page, 20, $q);

        return $this->view('blog.index', [
            'blogs' => $result['data'],
            'pagination' => $result['pagination'],
            'q' => $q,
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

            return $this->redirect('/admin/blogs');
        }

        return $this->view('blog.edit', [
            'errors' => $this->blogModel->getErrors(),
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

        return $this->redirect('/admin/blogs');
    }

    private function getBlog(string $id): array
    {
        $blog = $this->blogModel->getBlog((int) $id);

        if ($blog === false) {
            throw new PageNotFoundException("Blog with ID '$id' not found.");
        }

        return $blog->toArray();
    }

    private function generateSlug($name)
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

        return substr($slug, 0, 200);
    }
}
