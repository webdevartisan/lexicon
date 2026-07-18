<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Resources\BlogResource;
use App\Services\MediaService;
use App\Services\ThemeService;
use App\Services\UploadService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Per-blog appearance hub: theme browser plus branding and front-page texts.
 *
 * Settings stays behavioural (general, SEO, discussion); everything about how
 * the blog looks lives here. Authorization mirrors blog settings: anyone who
 * can update the blog can change how it looks.
 */
final class AppearanceController extends AppController
{
    public function __construct(
        private BlogModel $blogModel,
        private BlogSettingsModel $settings,
        private ThemeService $themes,
        private UploadService $uploader,
        private MediaService $mediaService,
    ) {}

    /**
     * Show the appearance hub (theme browser + branding form).
     *
     * @param  string  $blogId  Blog ID
     */
    public function index(string $blogId): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog($blogId);
        Gate::authorize('update', $blog, $user);

        // Editors arrive through Shared; the static pattern assumes All Blogs.
        if ($blog->effectiveRoleForUser((int) $user['id']) === 'editor') {
            breadcrumbs()->set([
                ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
                ['label' => 'Shared', 'url' => '/dashboard/shared', 'key' => 'breadcrumbs.shared'],
                ['label' => 'Appearance', 'url' => null, 'key' => 'breadcrumbs.appearance'],
            ], true);
        }

        $settings = $this->settings->findByBlogId((int) $blogId) ?? [];
        $themes = $this->themes->available();

        // Settings rows predating the browser may hold a key that no longer
        // matches an installed theme; treat those as folio, the platform default.
        $currentKey = (string) ($settings['theme'] ?? '');
        if (!isset($themes[$currentKey])) {
            $currentKey = 'folio';
        }

        return $this->view([
            'blog' => $blog->toArray(),
            'settings' => $settings,
            'themes' => $themes,
            'currentKey' => $currentKey,
            'currentTheme' => $themes[$currentKey] ?? null,
            'socialLinks' => BlogSettingsModel::decodeSocialLinks($settings['social_links'] ?? null),
            'socialPlatforms' => BlogSettingsModel::SOCIAL_PLATFORMS,
        ]);
    }

    /**
     * Activate a theme for a blog.
     *
     * @param  string  $blogId  Blog ID
     */
    public function activate(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $blog = $this->getBlog($blogId);
        Gate::authorize('update', $blog, $user);

        $key = (string) $this->request->postParam('theme');

        if (!$this->themes->isValidKey($key)) {
            $this->flash('error', 'That theme is not installed.');

            return $this->redirect('/dashboard/blog/'.$blogId.'/appearance');
        }

        $themeName = $this->themes->available()[$key]['name'];
        $currentSettings = $this->settings->findByBlogId((int) $blogId);

        if (($currentSettings['theme'] ?? null) === $key) {
            $this->flash('success', $themeName.' is already the active theme.');

            return $this->redirect('/dashboard/blog/'.$blogId.'/appearance');
        }

        if ($currentSettings === null) {
            $this->settings->createDefaultForBlog((int) $blogId, ['theme' => $key]);
        } else {
            $this->settings->updateForBlog((int) $blogId, ['theme' => $key]);
        }

        // The theme decides every public page of the blog, so purge them all.
        cache()->deletePattern('*:GET:/blog/'.$blog->slug().'*');

        audit()->log(
            (int) $user['id'],
            'blog.theme_changed',
            'blog',
            (int) $blogId,
            ['from' => $currentSettings['theme'] ?? null, 'to' => $key],
            $this->request->ip()
        );

        $this->flash('success', $themeName.' is now the active theme.');

        return $this->redirect('/dashboard/blog/'.$blogId.'/appearance');
    }

    /**
     * Save branding, front-page texts and social links.
     *
     * @param  string  $blogId  Blog ID
     */
    public function update(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $blog = $this->getBlog($blogId);
        Gate::authorize('update', $blog, $user);

        $userId = (int) $user['id'];
        $id = (int) $blogId;

        $rules = [
            'tagline' => 'max:160',
            'subtitle' => 'max:255',
            'about_text' => 'max:500',
            'founded_year' => 'max:20',
            'newsletter_heading' => 'max:255',
            'newsletter_text' => 'max:255',
            'remove_banner' => 'boolean',
            'remove_logo' => 'boolean',
            'remove_favicon' => 'boolean',
        ];

        foreach (BlogSettingsModel::SOCIAL_PLATFORMS as $platform) {
            $rules['social_'.$platform] = 'url|max:255';
        }

        $validated = $this->validateOrFail($rules)->validated();

        // Collapse the per-platform inputs into one JSON column; empty map clears it
        $socialLinks = [];
        foreach (BlogSettingsModel::SOCIAL_PLATFORMS as $platform) {
            $url = trim((string) ($validated['social_'.$platform] ?? ''));
            if ($url !== '') {
                $socialLinks[$platform] = $url;
            }
        }

        $settingsData = [
            'tagline' => trim((string) ($validated['tagline'] ?? '')),
            'subtitle' => trim((string) ($validated['subtitle'] ?? '')),
            'about_text' => trim((string) ($validated['about_text'] ?? '')),
            'founded_year' => trim((string) ($validated['founded_year'] ?? '')),
            'newsletter_heading' => trim((string) ($validated['newsletter_heading'] ?? '')),
            'newsletter_text' => trim((string) ($validated['newsletter_text'] ?? '')),
            'social_links' => $socialLinks === [] ? null : json_encode($socialLinks, JSON_UNESCAPED_SLASHES),
        ];

        $settingsData = array_merge(
            $settingsData,
            $this->handleBrandingUploads($userId, $id, $blog->ownerId())
        );

        $currentSettings = $this->settings->findByBlogId($id);
        if ($currentSettings === null) {
            $this->settings->createDefaultForBlog($id, []);
            $currentSettings = [];
        }

        // Handle removal checkboxes
        foreach (['remove_banner', 'remove_logo', 'remove_favicon'] as $removeKey) {
            if (!empty($validated[$removeKey])) {
                $type = explode('_', $removeKey)[1]; // banner, logo, favicon
                $filePathKey = $type.'_path';

                // Delete physical file
                if (!empty($currentSettings[$filePathKey])) {
                    $oldFile = ROOT_PATH.'/public'.$currentSettings[$filePathKey];
                    if (file_exists($oldFile)) {
                        @unlink($oldFile);
                    }
                }

                $settingsData[$filePathKey] = null;
            }
        }

        $settingsChanges = changedFields($settingsData, $currentSettings);

        if (!empty($settingsChanges)) {
            $this->settings->updateForBlog($id, $settingsChanges);

            // Branding and front-page texts render on every public page of the
            // blog, so purge them all, the landing page included.
            cache()->deletePattern('*:GET:/blog/'.$blog->slug().'*');

            audit()->log(
                $userId,
                'blog.updated',
                'blog',
                $id,
                $settingsChanges,
                $this->request->ip()
            );
        }

        $this->flash('success', 'Appearance updated.');

        return $this->redirect('/dashboard/blog/'.$blogId.'/appearance#branding');
    }

    /**
     * Get blog resource or throw 404.
     *
     * @param  string  $id  Blog ID
     *
     * @throws PageNotFoundException
     */
    private function getBlog(string $id): BlogResource
    {
        $blog = $this->blogModel->getBlog($id);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$id}' not found.");
        }

        return $blog;
    }

    /**
     * Handle branding file uploads (banner, logo, favicon).
     *
     * Extracts uploaded files from POST, moves from temp to branding directory,
     * and returns path array for settings merge.
     *
     * @param  int  $userId  User ID (for temp cleanup)
     * @param  int  $blogId  Blog ID
     * @param  int|null  $ownerId  Owner ID (defaults to userId)
     * @return array<string, string> Paths keyed by 'banner_path', 'logo_path', 'favicon_path'
     */
    private function handleBrandingUploads(int $userId, int $blogId, ?int $ownerId = null): array
    {
        $ownerId ??= $userId;

        $paths = [];

        // Library picks beat freshly-uploaded files, if the user selected an
        // existing image, drop it straight into the corresponding _path slot.
        foreach (['banner', 'logo', 'favicon'] as $type) {
            $picked = trim((string) ($this->request->post[$type.'_library_url'] ?? ''));
            if ($picked !== '') {
                $paths[$type.'_path'] = $picked;
                $this->mediaService->register($blogId, $userId, $picked, 'branding');
            }
        }

        $uploadedFiles = [
            'uploaded_banner_files' => $this->request->post['uploaded_banner_files'] ?? '',
            'uploaded_logo_files' => $this->request->post['uploaded_logo_files'] ?? '',
            'uploaded_favicon_files' => $this->request->post['uploaded_favicon_files'] ?? '',
        ];

        $uploadedFileNames = $this->uploader->getUploadedFiles($uploadedFiles);

        [$dir, $baseUrl] = $this->uploader->blogBrandingPath($ownerId, $blogId);

        foreach ($uploadedFileNames as $fieldName => $fileName) {
            if (empty($fileName)) {
                continue;
            }

            // Extract type: uploaded_banner_files to banner
            $parts = explode('_', $fieldName);
            $type = $parts[1];

            // Library pick already filled this slot, don't clobber it.
            if (isset($paths[$type.'_path'])) {
                continue;
            }

            try {
                $path = $this->uploader->moveTempToBranding(
                    $fileName,
                    $ownerId,
                    $blogId,
                    $type,
                    $dir,
                    $baseUrl
                );
                $paths[$type.'_path'] = $path;

                if ($path) {
                    $this->mediaService->register($blogId, $userId, $path, 'branding');
                }
            } catch (\Throwable $e) {
                error_log("{$type} upload failed for blog {$blogId}: ".$e->getMessage());
                $this->flash('error', ucfirst($type).' upload failed: '.$e->getMessage());
            }
        }

        // Cleanup temp files
        $this->uploader->cleanupTempFiles($userId);

        return $paths;
    }
}
