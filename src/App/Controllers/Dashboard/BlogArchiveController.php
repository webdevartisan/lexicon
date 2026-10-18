<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Resources\BlogResource;
use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveFormat;
use App\Services\Archive\ArchiveImportStore;
use App\Services\Archive\ArchivePlan;
use App\Services\Archive\BlogArchiveExporter;
use App\Services\Archive\BlogArchiveImporter;
use App\Services\Archive\BlogArchiveReader;
use App\Services\Archive\Foreign\ForeignArchiveBuilder;
use App\Services\Archive\Foreign\ForeignSource;
use App\Services\BlogArchiveRateLimiter;
use Framework\Core\FileDownloadResponse;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * The blog's Backup & restore page: download an archive, or preview and apply one.
 *
 * HTTP only. The exporter, reader and importer do the work and know nothing about
 * requests, so a background worker could replace this controller without them
 * changing. Owner only for every action, checked here on the server.
 */
final class BlogArchiveController extends AppController
{
    private const VIEW = 'areas/dashboard/BlogArchive/index.lex.php';

    private const IMPORT_SECONDS = 600;

    public function __construct(
        private BlogModel $blogModel,
        private BlogArchiveExporter $exporter,
        private BlogArchiveReader $reader,
        private BlogArchiveImporter $importer,
        private ArchiveImportStore $store,
        private BlogArchiveRateLimiter $limiter,
        private ForeignArchiveBuilder $foreign,
    ) {}

    /**
     * The page with the export and import cards.
     */
    public function index(string $blogId): Response
    {
        $blog = $this->ownedBlog($blogId);

        return $this->page($blog, []);
    }

    /**
     * Build the archive and send it as a download.
     */
    public function export(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));
        $blog = $this->ownedBlog($blogId);
        $userId = (int) auth()->user()['id'];

        if ($throttled = $this->throttled($blog, $userId, 'export')) {
            return $throttled;
        }
        $this->limiter->hit($userId, 'export');

        $path = $this->exportPath();
        try {
            $summary = $this->exporter->export($blog, $path, [
                'include_drafts' => $this->request->postParam('include_drafts') === '1',
            ]);
        } catch (ArchiveException $e) {
            return $this->backWithError($blog, $this->message($e));
        }

        audit()->log($userId, 'blog.archive_exported', 'blog', (int) $blog->id(), [
            'posts' => $summary['posts'],
            'media' => $summary['media'],
            'warnings' => count($summary['warnings']),
        ], $this->request->ip());

        return new FileDownloadResponse($path, ArchiveFormat::fileName($blog->slug()), 'application/zip', true);
    }

    /**
     * Take an uploaded archive, check it, and show what importing it would do.
     */
    public function preview(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));
        $blog = $this->ownedBlog($blogId);
        $userId = (int) auth()->user()['id'];

        if ($throttled = $this->throttled($blog, $userId, 'import')) {
            return $throttled;
        }
        $this->limiter->hit($userId, 'import');

        $source = $this->foreign->source((string) $this->request->postParam('source', ''));
        [$upload, $error] = $this->uploadedArchive($source?->extension() ?? 'zip');
        if ($upload === null) {
            $this->response->setStatusCode(422);

            return $this->page($blog, ['importError' => $error]);
        }

        try {
            $converted = $source !== null ? $this->convertForeign($source, $upload) : null;
            $importId = $this->store->store($converted ?? $upload, $userId, (int) $blog->id());
            $plan = $this->reader->read((string) $this->store->find($importId, $userId, (int) $blog->id()), $blog);
        } catch (ArchiveException $e) {
            if (isset($importId)) {
                $this->store->discard($importId, $userId);
            }
            $this->response->setStatusCode(422);

            return $this->page($blog, ['importError' => $this->message($e)]);
        } finally {
            if (isset($converted) && is_file($converted)) {
                unlink($converted);
            }
        }

        return $this->page($blog, ['plan' => $this->planView($plan), 'importId' => $importId]);
    }

    /**
     * Apply a previewed archive with the options the owner chose.
     */
    public function confirm(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));
        $blog = $this->ownedBlog($blogId);
        $userId = (int) auth()->user()['id'];

        $importId = (string) $this->request->postParam('import_id', '');
        $onClash = (string) $this->request->postParam('on_clash', BlogArchiveImporter::CLASH_SKIP);
        if (!in_array($onClash, [BlogArchiveImporter::CLASH_SKIP, BlogArchiveImporter::CLASH_COPY, BlogArchiveImporter::CLASH_OVERWRITE], true)) {
            return $this->backWithError($blog, $this->t('archive.errors.badOption'));
        }

        $selected = $this->selectedPosts();
        if ($selected === []) {
            return $this->backWithError($blog, $this->t('archive.errors.nonePicked'));
        }

        $zip = $this->store->find($importId, $userId, (int) $blog->id());
        if ($zip === null) {
            return $this->backWithError($blog, $this->t('archive.errors.expired'));
        }

        set_time_limit(self::IMPORT_SECONDS);
        try {
            $result = $this->importer->import($this->reader->read($zip, $blog), $zip, $blog, $userId, [
                'on_clash' => $onClash,
                'keep_status' => $this->request->postParam('keep_status') === '1',
                'only' => $selected,
                'work_dir' => $this->store->workDir($importId, $userId),
            ]);
        } catch (ArchiveException $e) {
            return $this->backWithError($blog, $this->message($e));
        } finally {
            $this->store->discard($importId, $userId);
        }

        $this->auditImport($userId, $blog, $result, $onClash);

        return $this->page($blog, ['result' => $result]);
    }

    /**
     * Abandon a previewed import and delete its upload.
     */
    public function cancel(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));
        $blog = $this->ownedBlog($blogId);

        $this->store->discard((string) $this->request->postParam('import_id', ''), (int) auth()->user()['id']);
        $this->flash('info', $this->t('archive.import.cancelled'));

        return $this->redirect(lurl($this->basePath($blog)));
    }

    /**
     * @throws PageNotFoundException
     */
    private function ownedBlog(string $blogId): BlogResource
    {
        $blog = $this->blogModel->getBlog($blogId);
        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$blogId}' not found.");
        }

        Gate::authorize('manageArchive', $blog, auth()->user());

        return $blog;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function page(BlogResource $blog, array $extra): Response
    {
        return $this->view(self::VIEW, $extra + [
            'blog' => $blog->toArray(),
            'basePath' => $this->basePath($blog),
            'maxMb' => intdiv(ArchiveFormat::MAX_ARCHIVE_BYTES, 1024 * 1024),
            'maxPosts' => ArchiveFormat::MAX_POSTS,
        ]);
    }

    /**
     * The uploaded file's temp path, or the reason it cannot be used.
     *
     * @return array{0: ?string, 1: ?string} [path, error]
     */
    private function uploadedArchive(string $extension = 'zip'): array
    {
        $file = $this->request->files['archive'] ?? null;
        $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE || !is_string($file['tmp_name'] ?? null)) {
            return [null, $this->t('archive.errors.noFile')];
        }
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || (int) ($file['size'] ?? 0) > ArchiveFormat::MAX_ARCHIVE_BYTES) {
            return [null, $this->t('archive.errors.tooLarge', ['size' => intdiv(ArchiveFormat::MAX_ARCHIVE_BYTES, 1024 * 1024).' MB'])];
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return [null, $this->t('archive.errors.uploadFailed')];
        }
        if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== $extension) {
            return [null, $extension === 'zip'
                ? $this->t('archive.errors.notZip')
                : $this->t('archive.foreign.wrongType', ['type' => '.'.$extension])];
        }

        return [$file['tmp_name'], null];
    }

    /**
     * Positions of the posts the owner ticked in the preview.
     *
     * @return list<int>
     */
    private function selectedPosts(): array
    {
        $raw = $this->request->postParam('selected', []);
        if (!is_array($raw)) {
            return [];
        }

        $positions = [];
        foreach ($raw as $value) {
            if (is_string($value) && ctype_digit($value)) {
                $positions[(int) $value] = (int) $value;
            }
        }

        return array_values($positions);
    }

    /**
     * Convert another platform's export into a temporary archive and return its path.
     *
     * @throws ArchiveException
     */
    private function convertForeign(ForeignSource $source, string $upload): string
    {
        $dest = $this->exportPath();
        try {
            $this->foreign->build($source, $upload, trim((string) $this->request->postParam('site_url', '')), $dest);
        } catch (\Throwable $e) {
            if (is_file($dest)) {
                unlink($dest);
            }
            throw $e;
        }

        return $dest;
    }

    /**
     * @return array<string, mixed>
     */
    private function planView(ArchivePlan $plan): array
    {
        $grouped = [];
        foreach ($plan->problems as $problem) {
            $grouped[$problem['type']][] = $problem;
        }

        return [
            'source' => $plan->source,
            'counts' => $plan->counts(),
            'posts' => array_map(static fn (array $p, int $i): array => [
                'index' => $i,
                'title' => (string) ($p['data']['title'] ?? ''),
                'slug' => (string) ($p['data']['slug'] ?? ''),
                'status' => (string) ($p['data']['status'] ?? ''),
                'date' => substr((string) ($p['data']['published_at'] ?? ''), 0, 10),
                'category' => $p['data']['category']['name'] ?? '',
                'tags' => count($p['data']['tags'] ?? []),
                'problem' => implode(' ', $p['errors']),
            ], $plan->posts, array_keys($plan->posts)),
            'clashes' => array_map(static fn (array $p): array => [
                'title' => $p['data']['title'],
                'slug' => $p['data']['slug'],
                'existing_title' => $p['clash']['title'],
                'existing_status' => $p['clash']['status'],
            ], $plan->clashes()),
            'problems' => $grouped,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function auditImport(int $userId, BlogResource $blog, array $result, string $onClash): void
    {
        audit()->log($userId, 'blog.archive_imported', 'blog', (int) $blog->id(), [
            'created' => count($result['created']),
            'overwritten' => count($result['overwritten']),
            'skipped' => count($result['skipped']),
            'failed' => count($result['failed']),
            'media' => $result['media']['stored'],
            'on_clash' => $onClash,
            'keep_status' => $this->request->postParam('keep_status') === '1',
        ], $this->request->ip());
    }

    private function throttled(BlogResource $blog, int $userId, string $kind): ?Response
    {
        if (!$this->limiter->tooManyAttempts($userId, $kind)) {
            return null;
        }

        $minutes = (int) ceil($this->limiter->availableIn($userId, $kind) / 60);
        $this->response->setStatusCode(429);

        return $this->page($blog, ['importError' => $this->t('archive.errors.throttled', ['minutes' => max(1, $minutes)])]);
    }

    private function backWithError(BlogResource $blog, string $message): Response
    {
        $this->flash('error', $message);

        return $this->redirect(lurl($this->basePath($blog)));
    }

    private function exportPath(): string
    {
        $dir = ROOT_PATH.'/storage/tmp/blog-archives/exports';
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException("Could not create {$dir}");
        }

        return $dir.'/'.bin2hex(random_bytes(16)).'.zip';
    }

    private function basePath(BlogResource $blog): string
    {
        return '/dashboard/blog/'.$blog->id().'/backup';
    }

    private function message(ArchiveException $e): string
    {
        return $e->key !== '' ? $this->t($e->key, $e->params) : $e->getMessage();
    }
}
