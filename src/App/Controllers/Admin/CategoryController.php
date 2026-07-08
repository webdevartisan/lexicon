<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\BlogModel;
use App\Models\CategoryModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class CategoryController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageTaxonomy';

    public function __construct(
        private CategoryModel $model,
        private BlogModel $blogModel
    ) {}

    public function index(): Response
    {
        $q = trim((string) $this->request->getParam('q', ''));
        $page = max(1, (int) $this->request->getParam('page', 1));

        $result = $this->model->findAllForAdmin($page, 20, $q);

        return $this->view('category.index', [
            'categories' => $result['data'],
            'pagination' => $result['pagination'],
            'q' => $q,
        ]);
    }

    public function new(): Response
    {
        return $this->view('category.new', [
            'blogs' => $this->blogModel->getAllBlogsWithOwnerAndCounts(),
        ]);
    }

    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $input = $this->validateOrFail([
            'name' => 'required|min:2|max:100',
            'slug' => 'max:120',
            'blog_id' => 'required|integer|exists:blogs,id',
        ])->validated();

        $data = [
            'name' => $input['name'],
            'slug' => $input['slug'] ?? '',
            'blog_id' => (int) $input['blog_id'],
        ];

        if ($this->model->insert($data)) {
            $this->flash('success', 'Category created.');

            return $this->redirect('/admin/categories');
        }

        return $this->view('category.new', [
            'errors' => ['Could not create the category. Please try again.'],
            'category' => $data,
            'blogs' => $this->blogModel->getAllBlogsWithOwnerAndCounts(),
        ]);
    }

    public function edit(string $id): Response
    {
        $category = $this->getCategory($id);

        return $this->view('category.edit', [
            'category' => $category,
        ]);
    }

    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $category = $this->getCategory($id);

        // blog_id deliberately stays fixed: moving a category between blogs
        // would strand the posts that reference it
        $data = [
            'name' => $this->request->post['name'] ?? $category['name'],
            'slug' => $this->request->post['slug'] ?? $category['slug'],
        ];

        if ($this->model->update($id, $data)) {
            $this->flash('success', 'Category updated.');

            return $this->redirect('/admin/categories');
        }

        return $this->view('category.edit', [
            'errors' => ['Could not update the category. Please try again.'],
            'category' => $data,
        ]);
    }

    public function delete(string $id): Response
    {
        $category = $this->getCategory($id);

        return $this->view('category.delete', [
            'category' => $category,
        ]);
    }

    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $category = $this->getCategory($id);

        $this->model->delete($id);

        audit()->log(
            (int) auth()->user()['id'],
            'category.deleted',
            'category',
            (int) $id,
            ['name' => $category['name'] ?? null],
            $this->request->ip()
        );

        $this->flash('success', 'Category deleted.');

        return $this->redirect('/admin/categories');
    }

    /**
     * @return array<string, mixed> Category record
     */
    private function getCategory(string $id): array
    {
        $category = $this->model->find($id);

        if (!$category) {
            throw new PageNotFoundException("Category with ID '$id' not found.");
        }

        return $category;
    }
}
