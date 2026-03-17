<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\CategoryModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class CategoryController extends AppController
{
    public function __construct(private CategoryModel $model) {}

    public function index(): Response
    {
        $this->requireRole(['editor', 'administrator']); // authors can’t manage categories
        $categories = $this->model->findAll();

        return $this->view('Admin/Categories/index.lex.php', [
            'categories' => $categories,
        ]);
    }

    public function new(): Response
    {
        $this->requireRole(['editor', 'administrator']);

        return $this->view('Admin/Categories/new.lex.php');
    }

    public function create(): Response
    {
        $this->requireRole(['editor', 'administrator']);

        $data = [
            'name' => $this->request->post['name'] ?? '',
            'slug' => $this->request->post['slug'] ?? '',
        ];

        if ($this->model->insert($data)) {
            return $this->redirect('/admin/categories');
        }

        return $this->view('Admin/Categories/new.lex.php', [
            'errors' => $this->model->getErrors(),
            'category' => $data,
        ]);
    }

    public function edit(string $id): Response
    {
        $this->requireRole(['editor', 'administrator']);
        $category = $this->getCategory($id);

        return $this->view('Admin/Categories/edit.lex.php', [
            'category' => $category,
        ]);
    }

    public function update(string $id): Response
    {
        $this->requireRole(['editor', 'administrator']);
        $category = $this->getCategory($id);

        $data = [
            'name' => $this->request->post['name'] ?? $category['name'],
            'slug' => $this->request->post['slug'] ?? $category['slug'],
        ];

        if ($this->model->update($id, $data)) {
            return $this->redirect('/admin/categories');
        }

        return $this->view('Admin/Categories/edit.lex.php', [
            'errors' => $this->model->getErrors(),
            'category' => $data,
        ]);
    }

    public function delete(string $id): Response
    {
        $this->requireRole(['editor', 'administrator']);
        $category = $this->getCategory($id);

        return $this->view('Admin/Categories/delete.lex.php', [
            'category' => $category,
        ]);
    }

    public function destroy(string $id): Response
    {
        $this->requireRole(['editor', 'administrator']);
        $this->model->delete($id);

        return $this->redirect('/admin/categories');
    }

    private function getCategory(string $id): array
    {
        $category = $this->model->find($id);

        if (!$category) {
            throw new PageNotFoundException("Category with ID '$id' not found.");
        }

        return $category;
    }
}
