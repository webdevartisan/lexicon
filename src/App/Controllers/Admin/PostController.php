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
        protected Database $database
    ) {}

    /**
     * List posts across every blog with status filter, search, and paging.
     */
    public function index(): Response
    {
        $status = trim((string) ($this->request->get['status'] ?? ''));
        $q = trim((string) ($this->request->get['q'] ?? ''));
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        $result = $this->model->findAllForAdmin($page, 20, $status, $q);

        return $this->view([
            'posts' => $result['data'],
            'pagination' => $result['pagination'],
            'status' => $status,
            'q' => $q,
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

            return $this->redirect('/admin/posts');
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

        $data = [
            'title' => $input['title'],
            'slug' => $input['slug'] ?? $post['slug'],
            'content' => $input['content'],
            'excerpt' => $input['excerpt'] ?? null,
            'featured_image' => $input['featured_image'] ?? null,
            'status' => $input['status'],
            'blog_id' => (int) $input['blog_id'],
        ];

        if ($this->model->update($id, $data)) {
            $this->flash('success', 'Post updated.');

            return $this->redirect('/admin/posts');
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

        return $this->redirect('/admin/posts');
    }

    /**
     * Shared validation for create and update submissions.
     *
     * @return array<string, mixed> Validated input fields
     */
    private function validatePostInput(): array
    {
        return $this->validateOrFail([
            'title' => 'required|min:3|max:200',
            'slug' => 'max:220',
            'content' => 'required',
            'excerpt' => 'max:500',
            'featured_image' => 'max:255',
            'status' => 'required|in:draft,published,archived',
            'blog_id' => 'required|integer|exists:blogs,id',
        ])->validated();
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
