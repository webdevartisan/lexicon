<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CategoryModel;
use Framework\BaseController;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class CategoryController extends BaseController
{
    public function __construct(private CategoryModel $model) {}

    public function index(): Response
    {
        $categories = $this->model->findAll();

        return $this->view('Categories/index.lex.php', [
            'categories' => $categories,
        ]);
    }

    public function show(string $id): Response
    {
        $category = $this->getCategory($id);

        return $this->view('Categories/show.lex.php', [
            'category' => $category,
            'posts' => $this->model->posts((int) $category['id']),
        ]);
    }

    public function new(): Response
    {
        return $this->view('Categories/new.lex.php');
    }

    public function create(): Response
    {
        $data = [
            'name' => $this->request->post['name'],
            'slug' => $this->request->post['slug'],
        ];

        if ($this->model->insert($data)) {
            return $this->redirect("/categories/{$this->model->getInsertID()}/show");
        }

        return $this->view('Categories/new.lex.php', [
            'errors' => $this->model->getErrors(),
            'category' => $data,
        ]);
    }

    public function edit(string $id): Response
    {
        $category = $this->getCategory($id);

        return $this->view('Categories/edit.lex.php', [
            'category' => $category,
        ]);
    }

    public function update(string $id): Response
    {
        $category = $this->getCategory($id);

        $category['name'] = $this->request->post['name'];
        $category['slug'] = $this->request->post['slug'];

        if ($this->model->update($id, $category)) {
            return $this->redirect("/categories/{$id}/show");
        }

        return $this->view('Categories/edit.lex.php', [
            'errors' => $this->model->getErrors(),
            'category' => $category,
        ]);
    }

    public function delete(string $id): Response
    {
        $category = $this->getCategory($id);

        return $this->view('Categories/delete.lex.php', [
            'category' => $category,
        ]);
    }

    public function destroy(string $id)
    {
        $this->model->delete($id);

        return $this->redirect('/categories/index');
    }

    private function getCategory(string $id): array
    {
        $category = $this->model->find($id);

        if (!$category) {
            throw new PageNotFoundException("Category with ID: '$id' not found.");
        }

        return $category;
    }
}
