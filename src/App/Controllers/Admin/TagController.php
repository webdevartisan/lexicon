<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\TagModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class TagController extends AppController
{
    public function __construct(private TagModel $model) {}

    public function index(): Response
    {
        // $this->requirePermission('manage_all_posts');
        $tags = $this->model->findAll();

        return $this->view('Admin/Tags/index.lex.php', [
            'tags' => $tags,
        ]);
    }

    public function new(): Response
    {
        $this->requireRole(['editor', 'administrator']);

        return $this->view('Admin/Tags/new.lex.php');
    }

    public function create(): Response
    {
        $this->requireRole(['editor', 'administrator']);

        $data = [
            'name' => $this->request->post['name'] ?? '',
            'slug' => $this->request->post['slug'] ?? '',
        ];

        if ($this->model->insert($data)) {
            return $this->redirect('/admin/tags');
        }

        return $this->view('Admin/Tags/new.lex.php', [
            'errors' => $this->model->getErrors(),
            'tag' => $data,
        ]);
    }

    public function edit(string $id): Response
    {
        $this->requireRole(['editor', 'administrator']);
        $tag = $this->getTag($id);

        return $this->view('Admin/Tags/edit.lex.php', [
            'tag' => $tag,
        ]);
    }

    public function update(string $id): Response
    {
        $this->requireRole(['editor', 'administrator']);
        $tag = $this->getTag($id);

        $data = [
            'name' => $this->request->post['name'] ?? $tag['name'],
            'slug' => $this->request->post['slug'] ?? $tag['slug'],
        ];

        if ($this->model->update($id, $data)) {
            return $this->redirect('/admin/tags');
        }

        return $this->view('Admin/Tags/edit.lex.php', [
            'errors' => $this->model->getErrors(),
            'tag' => $data,
        ]);
    }

    public function delete(string $id): Response
    {
        $this->requireRole(['editor', 'administrator']);
        $tag = $this->getTag($id);

        return $this->view('Admin/Tags/delete.lex.php', [
            'tag' => $tag,
        ]);
    }

    public function destroy(string $id): Response
    {
        $this->requireRole(['editor', 'administrator']);
        $this->model->delete($id);

        return $this->redirect('/admin/tags');
    }

    private function getTag(string $id): array
    {
        $tag = $this->model->find($id);

        if (!$tag) {
            throw new PageNotFoundException("Tag with ID '$id' not found.");
        }

        return $tag;
    }
}
