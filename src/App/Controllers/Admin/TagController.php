<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\BlogModel;
use App\Models\TagModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class TagController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageTaxonomy';

    public function __construct(
        private TagModel $model,
        private BlogModel $blogModel
    ) {}

    public function index(): Response
    {
        $q = trim((string) $this->request->getParam('q', ''));
        $page = max(1, (int) $this->request->getParam('page', 1));

        $result = $this->model->findAllForAdmin($page, 20, $q);

        return $this->view('tag.index', [
            'tags' => $result['data'],
            'pagination' => $result['pagination'],
            'q' => $q,
        ]);
    }

    public function new(): Response
    {
        return $this->view('tag.new', [
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
            $this->flash('success', 'Tag created.');

            return $this->redirect('/admin/tags');
        }

        return $this->view('tag.new', [
            'errors' => ['Could not create the tag. Please try again.'],
            'tag' => $data,
            'blogs' => $this->blogModel->getAllBlogsWithOwnerAndCounts(),
        ]);
    }

    public function edit(string $id): Response
    {
        $tag = $this->getTag($id);

        return $this->view('tag.edit', [
            'tag' => $tag,
        ]);
    }

    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $tag = $this->getTag($id);

        // blog_id deliberately stays fixed: moving a tag between blogs
        // would strand the posts that reference it
        $data = [
            'name' => $this->request->post['name'] ?? $tag['name'],
            'slug' => $this->request->post['slug'] ?? $tag['slug'],
        ];

        if ($this->model->update($id, $data)) {
            $this->flash('success', 'Tag updated.');

            return $this->redirect('/admin/tags');
        }

        return $this->view('tag.edit', [
            'errors' => ['Could not update the tag. Please try again.'],
            'tag' => $data,
        ]);
    }

    public function delete(string $id): Response
    {
        $tag = $this->getTag($id);

        return $this->view('tag.delete', [
            'tag' => $tag,
        ]);
    }

    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $tag = $this->getTag($id);

        $this->model->delete($id);

        audit()->log(
            (int) auth()->user()['id'],
            'tag.deleted',
            'tag',
            (int) $id,
            ['name' => $tag['name'] ?? null],
            $this->request->ip()
        );

        $this->flash('success', 'Tag deleted.');

        return $this->redirect('/admin/tags');
    }

    /**
     * @return array<string, mixed> Tag record
     */
    private function getTag(string $id): array
    {
        $tag = $this->model->find($id);

        if (!$tag) {
            throw new PageNotFoundException("Tag with ID '$id' not found.");
        }

        return $tag;
    }
}
