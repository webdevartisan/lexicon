<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\TagModel;
use Framework\BaseController;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class Tags extends BaseController
{
    public function __construct(private TagModel $model) {}

    public function index(): Response
    {
        $tags = $this->model->findAll();

        return $this->view('Tags/index.lex.php', [
            'tags' => $tags,
        ]);
    }

    public function show(string $id): Response
    {
        $tag = $this->getTag($id);

        return $this->view('Tags/show.lex.php', [
            'tag' => $tag,
            'posts' => $this->model->posts((int) $tag['id']),
        ]);
    }

    public function new(): Response
    {
        return $this->view('Tags/new.lex.php');
    }

    public function create(): Response
    {
        $data = [
            'name' => $this->request->post['name'],
            'slug' => $this->request->post['slug'],
        ];

        if ($this->model->insert($data)) {
            return $this->redirect("/tags/{$this->model->getInsertID()}/show");
        }

        return $this->view('Tags/new.lex.php', [
            'errors' => $this->model->getErrors(),
            'tag' => $data,
        ]);
    }

    public function edit(string $id): Response
    {
        $tag = $this->getTag($id);

        return $this->view('Tags/edit.lex.php', [
            'tag' => $tag,
        ]);
    }

    public function update(string $id): Response
    {
        $tag = $this->getTag($id);

        $tag['name'] = $this->request->post['name'];
        $tag['slug'] = $this->request->post['slug'];

        if ($this->model->update($id, $tag)) {
            return $this->redirect("/tags/{$id}/show");
        }

        return $this->view('Tags/edit.lex.php', [
            'errors' => $this->model->getErrors(),
            'tag' => $tag,
        ]);
    }

    public function delete(string $id): Response
    {
        $tag = $this->getTag($id);

        return $this->view('Tags/delete.lex.php', [
            'tag' => $tag,
        ]);
    }

    public function destroy(string $id)
    {
        $this->model->delete($id);

        return $this->redirect('/tags/index');
    }

    private function getTag(string $id): array
    {
        $tag = $this->model->find($id);

        if (!$tag) {
            throw new PageNotFoundException("Tag with ID: '$id' not found.");
        }

        return $tag;
    }
}
