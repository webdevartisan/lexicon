<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\BlogModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class BlogController extends AppController
{
    public function __construct(private BlogModel $blogModel) {}

    public function index(): Response
    {
        if (auth()->hasRole('administrator')) {
            $blogs = $this->blogModel->getAllBlogsWithOwnerAndCounts();
        } else {

        }

        return $this->view('blog.index', ['blogs' => $blogs]);
    }

    public function new(): Response
    {
        $this->requirePermission('create_blogs');

        return $this->view('blog.new');
    }

    public function create(): Response
    {
        $this->requirePermission('create_blogs');

        $blogName = $this->request->post['blog_name'] ?? '';
        $description = $this->request->post['description'] ?? '';
        $blogSlug = $this->generateSlug($blogName);

        try {
            $this->blogModel->insert([
                'blog_name' => $blogName,
                'blog_slug' => $blogSlug,
                'description' => $description,
                'owner_id' => auth()->user()['id'],
            ]);

            $blogId = $this->blogModel->getInsertID();

            $this->blogModel->logActivity(
                auth()->user()['id'],
                'create_blog',
                'blog',
                $blogId,
                "Created blog: $blogName",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            return $this->redirect("/blogs/$blogId/show");
        } catch (\PDOException $e) {
            // dd($e);
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
        $this->requireRole(['author', 'editor', 'administrator']);

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
        $this->requireRole(['author', 'editor', 'administrator']);

        $blog = $this->getBlog($id);

        $data = [
            'blog_name' => $this->request->post['blog_name'] ?? $blog['blog_name'],
            'blog_slug' => $this->request->post['blog_slug'] ?? $blog['blog_slug'],
            'description' => $this->request->post['description'] ?? $blog['description'],
            'is_active' => $this->request->post['is_active'] ?? $blog['is_active'],
        ];

        if ($this->blogModel->update($id, $data)) {
            return $this->redirect('/blogs');
        }

        return $this->view('blog.edit', [
            'errors' => $this->blogModel->getErrors(),
            'blog' => $data,
        ]);
    }

    public function show($id)
    {
        $blog = $this->blogModel->getBlog($id);
        $posts = $this->blogModel->getBlogPosts($id);
        $blogUsers = $this->blogModel->getBlogUsers($id);
        $availableUsers = $this->blogModel->getAvailableUsers($id);

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
        $this->requireRole(['author', 'editor', 'administrator']);

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
        $this->requireRole(['author', 'editor', 'administrator']);

        $this->blogModel->delete($id);

        return $this->redirect('/blogs');
    }

    private function getBlog(string $id): object
    {
        $blog = $this->blogModel->findResource($id);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '$id' not found.");
        }

        return $blog;
    }

    /*
        public function assignAuthor($blogId)
        {
            $this->requireAuth();
            if (!auth()->ownsBlog($blogId) && !auth()->hasRole('administrator')) {
                return $this->json(['error' => 'Not authorized']);
            }
            $authorId = $this->request->post['author_id'] ?? null;
            if (!$authorId) {
                return $this->json(['error' => 'Author ID required'], 400);
            }

            $success = $this->blogModel->assignAuthor($blogId, $authorId, auth()->user()['id']);
            if ($success) {
                $this->logActivity('assign_author', 'blog', $blogId, "Assigned author ID: $authorId");
                return $this->json(['success' => true]);
            }
            return $this->json(['error' => 'Could not assign author'], 500);
        }

        public function removeAuthor($blogId, $authorId)
        {
            $this->requireAuth();
            if (!auth()->ownsBlog($blogId) && !auth()->hasRole('administrator')) {
                return $this->json(['error' => 'Not authorized']);
            }

            $success = $this->blogModel->removeAuthor($blogId, $authorId);
            if ($success) {
                //$this->logActivity('remove_author', 'blog', $blogId, "Removed author ID: $authorId");
                return $this->json(['success' => true]);
            }
            return $this->json(['error' => 'Could not remove author'], 500);
        }*/

    private function generateSlug($name)
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

        return substr($slug, 0, 200);
    }
}
