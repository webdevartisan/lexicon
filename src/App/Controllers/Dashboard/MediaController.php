<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\MediaModel;
use App\Resources\BlogResource;
use App\Services\MediaService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * The per-blog media library.
 *
 * `index` serves the management page; `list` is the JSON feed used by
 * both that page and the picker modal; `store` / `destroy` handle
 * deliberate uploads and deletions. Every action is scoped to the blog
 * in the URL and authorised against it.
 */
final class MediaController extends AppController
{
    public function __construct(
        private MediaModel $media,
        private MediaService $mediaService,
        private BlogModel $blogModel,
    ) {}

    public function index(string $blogId): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('view', $blog, $user);

        // Pull anything new on disk into the table the first time the library is opened for this blog
        $this->mediaService->backfill($blog->id(), $blog->ownerId(), (int) $user['id']);

        // Arriving with ?editUrl=... (e.g. "Edit" on the appearance page) indexes that
        // image if needed and tells the page to open the editor straight on it.
        $autoEditId = null;
        $editUrl = trim((string) ($this->request->get['editUrl'] ?? ''));
        if ($editUrl !== '') {
            $row = $this->mediaService->ensureIndexed($blog->id(), (int) $user['id'], $editUrl);
            $autoEditId = $row['id'] ?? null;
        }

        return $this->view([
            'blog' => $blog->toArray(),
            'autoEditId' => $autoEditId,
            // `$items` is reused by the breadcrumb partial in the layout,
            // so we hand the controller's list to the template under a
            // distinct name.
            'mediaItems' => $this->media->listForBlog($blog->id(), ['limit' => 24]),
            'total' => $this->media->countForBlog($blog->id()),
        ]);
    }

    public function list(string $blogId): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('view', $blog, $user);

        $source = (string) ($this->request->get['source'] ?? '');
        $filters = [
            'q' => trim((string) ($this->request->get['q'] ?? '')),
            'source' => $source,
            'sort' => (string) ($this->request->get['sort'] ?? 'newest'),
            'limit' => (int) ($this->request->get['limit'] ?? 24),
            'offset' => (int) ($this->request->get['offset'] ?? 0),
        ];

        // "Unused" is a different axis from the source enum, so route it separately.
        if ($source === 'unused') {
            $filters['source'] = '';
            $filters['usage'] = 'unused';
        }

        return $this->jsonSuccess([
            'items' => $this->media->listForBlog($blog->id(), $filters),
            'total' => $this->media->countForBlog($blog->id(), $filters),
        ]);
    }

    public function details(string $blogId, string $id): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('view', $blog, $user);

        $row = $this->mediaService->details((int) $id, $blog->id());
        if (!$row) {
            return $this->jsonError('Media item not found.', 404);
        }

        return $this->jsonSuccess($row);
    }

    public function process(string $blogId, string $id): Response
    {
        $token = $this->request->postParam('_token') ?? ($this->request->headers['x-csrf-token'] ?? null);
        if (!csrf()->isTokenValid($token)) {
            return $this->jsonError('Invalid CSRF token.', 419);
        }

        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('update', $blog, $user);

        $opts = $this->readEditOptions();

        try {
            $item = $this->mediaService->process((int) $id, $blog->id(), $blog->ownerId(), $opts);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->jsonError('Could not process the image.', 500);
        }

        return $this->jsonSuccess($item);
    }

    public function rescan(string $blogId): Response
    {
        $token = $this->request->postParam('_token') ?? ($this->request->headers['x-csrf-token'] ?? null);
        if (!csrf()->isTokenValid($token)) {
            return $this->jsonError('Invalid CSRF token.', 419);
        }

        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('update', $blog, $user);

        $added = $this->mediaService->backfill($blog->id(), $blog->ownerId(), (int) $user['id']);

        return $this->jsonSuccess(['added' => $added]);
    }

    public function optimize(string $blogId): Response
    {
        $token = $this->request->postParam('_token') ?? ($this->request->headers['x-csrf-token'] ?? null);
        if (!csrf()->isTokenValid($token)) {
            return $this->jsonError('Invalid CSRF token.', 419);
        }

        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('update', $blog, $user);

        $summary = $this->mediaService->optimizeOversized($blog->id(), $blog->ownerId());

        return $this->jsonSuccess($summary);
    }

    public function saveMeta(string $blogId, string $id): Response
    {
        $token = $this->request->postParam('_token') ?? ($this->request->headers['x-csrf-token'] ?? null);
        if (!csrf()->isTokenValid($token)) {
            return $this->jsonError('Invalid CSRF token.', 419);
        }

        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('update', $blog, $user);

        $alt = $this->request->postParam('alt_text');

        try {
            $item = $this->mediaService->saveMeta(
                (int) $id,
                $blog->id(),
                (string) $this->request->postParam('name', ''),
                $alt === null ? null : (string) $alt,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        }

        return $this->jsonSuccess($item);
    }

    /**
     * Pull the edit parameters out of the request into the shape MediaService wants.
     *
     * @return array<string, mixed>
     */
    private function readEditOptions(): array
    {
        $p = fn (string $k, $d = null) => $this->request->postParam($k, $d);

        $opts = [
            'mode' => $p('mode') === 'overwrite' ? 'overwrite' : 'new',
            'quality' => (int) $p('quality', 82),
            'width' => (int) $p('width', 0),
            'height' => (int) $p('height', 0),
        ];

        $format = $p('format');
        if (is_string($format) && $format !== '') {
            $opts['format'] = $format;
        }

        // a crop is only applied when all four numbers are present
        $cropKeys = ['crop_x', 'crop_y', 'crop_w', 'crop_h'];
        if (count(array_filter($cropKeys, fn ($k) => $p($k) !== null)) === 4) {
            $opts['crop'] = [
                'x' => (int) $p('crop_x'),
                'y' => (int) $p('crop_y'),
                'width' => (int) $p('crop_w'),
                'height' => (int) $p('crop_h'),
            ];
        }

        return $opts;
    }

    public function store(string $blogId): Response
    {
        $token = $this->request->postParam('_token') ?? ($this->request->headers['x-csrf-token'] ?? null);
        if (!csrf()->isTokenValid($token)) {
            return $this->jsonError('Invalid CSRF token.', 419);
        }

        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('update', $blog, $user);

        if (empty($this->request->files['file'])) {
            return $this->jsonError('No file uploaded.', 400);
        }

        try {
            $item = $this->mediaService->upload(
                $this->request->files['file'],
                (int) $user['id'],
                $blog->id(),
                $blog->ownerId(),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->jsonError('Upload failed.', 500);
        }

        return $this->jsonSuccess($item);
    }

    public function destroy(string $blogId, string $id): Response
    {
        $token = $this->request->postParam('_token') ?? ($this->request->headers['x-csrf-token'] ?? null);
        if (!csrf()->isTokenValid($token)) {
            return $this->jsonError('Invalid CSRF token.', 419);
        }

        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('update', $blog, $user);

        $ok = $this->mediaService->delete((int) $id, $blog->id());
        if (!$ok) {
            return $this->jsonError('Media item not found.', 404);
        }

        return $this->jsonSuccess(['deleted' => (int) $id]);
    }

    private function getBlog(int $id): BlogResource
    {
        $blog = $this->blogModel->getBlog($id);
        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$id}' not found.");
        }

        return $blog;
    }
}
