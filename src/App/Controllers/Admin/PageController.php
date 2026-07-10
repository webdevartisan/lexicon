<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\PageModel;
use App\Services\PublicCacheInvalidator;
use App\Services\UploadService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Manage static pages (about, contact, legal, guides).
 *
 * The page set is fixed because each slug needs an explicit public route;
 * admins edit content and translations but cannot invent new slugs here.
 * Page content is trusted HTML from settings-level admins, the same trust
 * boundary as the settings screen itself.
 */
final class PageController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageSettings';

    public function __construct(
        private PageModel $pages,
        private PublicCacheInvalidator $publicCache,
        private UploadService $uploader
    ) {}

    public function index(): Response
    {
        return $this->view([
            'pagesBySlug' => $this->pages->allGroupedBySlug(),
            'locales' => $this->supportedLocales(),
        ]);
    }

    public function edit(string $id): Response
    {
        return $this->view([
            'page' => $this->findOrFail($id),
        ]);
    }

    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $page = $this->findOrFail($id);

        $validator = $this->validateOrFail([
            'title' => 'required|min:2|max:200',
            'content' => 'required',
            'meta_description' => 'max:160',
        ]);

        $data = $validator->validated();
        $data['is_published'] = !empty($this->request->postParam('is_published')) ? 1 : 0;
        $data['updated_by'] = (int) auth()->user()['id'];

        $thumbnailChange = $this->handleThumbnailUpload($page);
        if ($thumbnailChange !== false) {
            $data['thumbnail_path'] = $thumbnailChange;
        }

        $this->pages->update((int) $page['id'], $data);

        audit()->log(
            (int) auth()->user()['id'],
            'page.updated',
            'page',
            (int) $page['id'],
            ['slug' => $page['slug'], 'locale' => $page['locale']],
            $this->request->ip()
        );

        $this->publicCache->purgePage($page['slug']);
        // Guides also render on the getting started index
        $this->publicCache->purgePage('getting-started');

        $this->flash('success', 'Page saved.');

        return $this->redirect('/admin/pages/'.$page['id'].'/edit');
    }

    /**
     * Create a missing translation row for a page, copying the English
     * version as the starting point. Lands the admin straight in the editor.
     */
    public function translate(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $source = $this->findOrFail($id);
        $locale = (string) $this->request->postParam('locale');

        if (!in_array($locale, $this->supportedLocales(), true)) {
            $this->flash('error', 'Unknown locale.');

            return $this->redirect('/admin/pages');
        }

        $existing = $this->pages->findBySlugAndLocale($source['slug'], $locale);
        if ($existing !== null) {
            return $this->redirect('/admin/pages/'.$existing['id'].'/edit');
        }

        $this->pages->insert([
            'slug' => $source['slug'],
            'locale' => $locale,
            'title' => $source['title'],
            'content' => $source['content'],
            'meta_description' => $source['meta_description'],
            // Draft until the admin actually translates it
            'is_published' => 0,
            'updated_by' => (int) auth()->user()['id'],
        ]);

        $newId = $this->pages->getInsertID();

        return $this->redirect('/admin/pages/'.$newId.'/edit');
    }

    /**
     * Apply the thumbnail dropzone result: a fresh upload, an explicit
     * removal, or no change at all.
     *
     * @param  array<string, mixed>  $page  The page row being updated
     * @return string|null|false New public URL, null to clear the column, or
     *                           false (sentinel) when the column shouldn't be touched
     */
    private function handleThumbnailUpload(array $page): string|null|false
    {
        if (!empty($this->request->postParam('remove_thumbnail'))) {
            $this->deleteThumbnailFile($page['thumbnail_path'] ?? null);

            return null;
        }

        $uploaded = $this->uploader->getUploadedFiles(
            (string) ($this->request->post['uploaded_thumbnail_files'] ?? '')
        );
        $tempFilename = $uploaded[0] ?? null;

        if (empty($tempFilename)) {
            return false;
        }

        $userId = (int) auth()->user()['id'];
        $url = $this->uploader->moveTempToPageThumbnail((string) $tempFilename, $userId);

        $this->deleteThumbnailFile($page['thumbnail_path'] ?? null);

        return $url;
    }

    private function deleteThumbnailFile(?string $path): void
    {
        if (empty($path)) {
            return;
        }

        [$dir] = $this->uploader->pageThumbnailPath();
        $file = $dir.'/'.basename($path);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * @return array<string, mixed> The page row
     */
    private function findOrFail(string $id): array
    {
        $page = $this->pages->find($id);

        if (!$page) {
            throw new PageNotFoundException('Page not found.', 404);
        }

        return $page;
    }

    /**
     * @return array<int, string> Lowercased supported locale codes
     */
    private function supportedLocales(): array
    {
        $cfg = require ROOT_PATH.'/config/localization.php';

        return array_map('strtolower', $cfg['supported'] ?? ['en']);
    }
}
