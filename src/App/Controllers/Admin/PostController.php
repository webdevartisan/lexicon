<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\BlogModel;
use App\Models\PostModel;
use Framework\Core\Response;
use Framework\Database;
use Framework\Exceptions\PageNotFoundException;

/**
 * Admin post management controller.
 *
 * All routes are behind the /admin prefix and administrator role middleware.
 * Authorization is enforced via explicit permission checks (e.g. manage_all_posts).
 */
class PostController extends AppController
{
    public function __construct(
        private PostModel $model,
        private BlogModel $blogModel,
        protected Database $database
    ) {}

    /**
     * List posts available to administrators.
     */
    public function index(): Response
    {
        $this->requirePermission('manage_all_posts');

        $posts = $this->model->findAll();

        return $this->view([
            'posts' => $posts,
            'user' => auth()->user(),
        ]);
    }

    /**
     * Show a single post in admin
     */
    public function show(string $id): Response
    {
        $this->requirePermission('manage_all_posts');

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
        $this->requirePermission('create_posts');

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
        // $this->requireRole(['author', 'editor', 'administrator']);
        $this->requirePermission('create_posts');

        // Get form data (assumes form is submitted as POST)
        $data = [
            'title' => $this->request->post['title'] ?? '',
            'slug' => $this->request->post['slug'] ?? '',
            'content' => $this->request->post['content'] ?? '',
            'excerpt' => $this->request->post['excerpt'] ?? null,
            'featured_image' => $this->request->post['featured_image'] ?? null,
            'status' => $this->request->post['status'] ?? 'draft',
            'blog_id' => $this->request->post['blog_id'] ?? null,  // important!
            'author_id' => auth()->user()['id'], // assumes auth()->user() is set in controller constructor
        ];

        // Call model’s insert; model should validate and handle DB insertion
        if ($this->model->insert($data)) {
            return $this->redirect('/admin/posts');
        }

        // On error, return form view again
        return $this->view([
            'errors' => $this->model->getErrors(),
            'post' => $data,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(string $id): Response
    {
        $this->requirePermission('edit_all_posts');

        $post = $this->getPost($id);
        $blogs = $this->getBlogs();

        return $this->view('Admin/Posts/edit.lex.php', [
            'post' => $post,
            'blogs' => $blogs,
        ]);
    }

    /**
     * Handle update submission
     */
    public function update(string $id): Response
    {
        $this->requirePermission('edit_all_posts');

        $post = $this->getPost($id);

        $data = [
            'title' => $this->request->post['title'],
            'slug' => $this->request->post['slug'],
            'content' => $this->request->post['content'],
            'excerpt' => $this->request->post['excerpt'] ?? null,
            'featured_image' => $this->request->post['featured_image'] ?? null,
            'status' => $this->request->post['status'] ?? 'draft',
            'blog_id' => $this->request->post['blog_id'],
            // 'author_id'     => auth()->user()['id'] // assumes auth()->user() is set in controller constructor
        ];

        if ($this->model->update($id, $data)) {
            return $this->redirect('/admin/posts');
        }

        return $this->view('Admin/Posts/edit.lex.php', [
            'errors' => $this->model->getErrors(),
            'post' => $data,
        ]);
    }

    /**
     * Show delete confirmation
     */
    public function delete(string $id): Response
    {
        $this->requirePermission('delete_all_posts');

        $post = $this->getPost($id);

        return $this->view('Admin/Posts/delete.lex.php', [
            'post' => $post,
        ]);
    }

    /**
     * Handle deletion
     */
    public function destroy(string $id): Response
    {
        $this->requirePermission('delete_all_posts');

        $this->model->delete($id);

        return $this->redirect('/admin/posts');
    }

    /**
     * Utility: fetch post or 404
     */
    private function getPost(string $id): array
    {
        $post = $this->model->find($id);

        if (!$post) {
            throw new PageNotFoundException("Post with ID '$id' not found.");
        }

        return $post;
    }

    private function getBlogs(): array
    {
        return $this->blogModel->getAllBlogsWithOwnerAndCounts();
    }
}
