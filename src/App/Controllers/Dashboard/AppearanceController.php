<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\GuidedSetupMarkModel;
use App\Resources\BlogResource;
use App\Services\BlogCacheInvalidator;
use App\Services\BlogLocales;
use App\Services\BlogThemeService;
use App\Services\FrontPageTexts;
use App\Services\LocaleRegistry;
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
        private GuidedSetupMarkModel $setupMarks,
        private BlogThemeService $blogThemes,
        private BlogLocales $blogLocales,
        private FrontPageTexts $frontTexts,
        private BlogCacheInvalidator $blogCache,
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
        ] + $this->textLanguages($blog->id(), $settings, (string) ($this->request->get['lang'] ?? '')));
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

        $this->blogThemes->activate((int) $blogId, $key, (int) $user['id'], $this->request->ip());
        $this->setupMarks->mark((int) $user['id'], (int) $blogId, 'look_chosen');
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

        $rules = FrontPageTexts::RULES + [
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
            'social_links' => $socialLinks === [] ? null : json_encode($socialLinks, JSON_UNESCAPED_SLASHES),
        ];

        $settingsData = array_merge(
            $settingsData,
            $this->handleBrandingUploads($userId, $blog)
        );

        $currentSettings = $this->settings->findOrCreate($id);
        $textLocale = $this->textLocale(
            $this->blogLocales->for($id, $currentSettings),
            $currentSettings,
            (string) $this->request->postParam('text_locale', '')
        );
        $texts = array_fill_keys(array_keys(FrontPageTexts::RULES), '');
        $textChanges = $this->frontTexts->save($blog, $currentSettings, $textLocale, array_replace(
            $texts,
            array_intersect_key($validated, $texts)
        ));

        // Removing only empties the slot. The file stays in the media library,
        // where a post may still be using it.
        foreach (['banner', 'logo', 'favicon'] as $type) {
            if (!empty($validated['remove_'.$type])) {
                $settingsData[$type.'_path'] = null;
            }
        }

        $settingsChanges = changedFields($settingsData, $currentSettings);

        if (!empty($settingsChanges)) {
            $this->settings->updateForBlog($id, $settingsChanges);
            // Branding renders on every public page of the blog.
            $this->blogCache->forgetBlog($blog->slug());
        }

        if ($settingsChanges !== [] || $textChanges !== []) {
            $logged = $textChanges === [] ? $settingsChanges : $settingsChanges + $textChanges + ['locale' => $textLocale];
            audit()->log($userId, 'blog.updated', 'blog', $id, $logged, $this->request->ip());
        }

        $this->setupMarks->mark($userId, $id, 'look_chosen');
        $this->flash('success', 'Appearance updated.');

        $lang = $textLocale === BlogLocales::base($currentSettings) ? '' : '?lang='.$textLocale;

        return $this->redirect('/dashboard/blog/'.$blogId.'/appearance'.$lang.'#branding');
    }

    /**
     * The languages the front-page texts can be written in, and the texts in the one picked.
     *
     * @param  array<string, mixed>  $settings  Blog settings row
     * @return array{textLocales: array<string, string>, textLocale: string, texts: array<string, array{value: string, fallback: string}>}
     */
    private function textLanguages(int $blogId, array $settings, string $requested): array
    {
        $registry = LocaleRegistry::instance();
        $locales = $this->blogLocales->for($blogId, $settings);
        $locale = $this->textLocale($locales, $settings, $requested);

        $names = [];
        foreach ($locales as $code) {
            $names[$code] = $registry->nativeName($code);
        }

        return [
            'textLocales' => $names,
            'textLocale' => $locale,
            'texts' => $this->frontTexts->forEditing($blogId, $settings, $locale),
        ];
    }

    /**
     * The asked-for language when the blog's pages exist in it, else the blog's own.
     *
     * @param  string[]  $locales  Languages the blog's pages exist in
     * @param  array<string, mixed>  $settings  Blog settings row
     */
    private function textLocale(array $locales, array $settings, string $requested): string
    {
        return in_array($requested, $locales, true)
            ? $requested
            : BlogLocales::base($settings);
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
     * @param  BlogResource  $blog  The blog being branded
     * @return array<string, string> Paths keyed by 'banner_path', 'logo_path', 'favicon_path'
     */
    private function handleBrandingUploads(int $userId, BlogResource $blog): array
    {
        $blogId = $blog->id();
        $paths = [];

        // Library picks beat freshly-uploaded files, if the user selected an
        // existing image, drop it straight into the corresponding _path slot.
        foreach (['banner', 'logo', 'favicon'] as $type) {
            $picked = trim((string) ($this->request->post[$type.'_library_url'] ?? ''));
            if ($picked === '') {
                continue;
            }

            try {
                $paths[$type.'_path'] = $this->mediaService->usePicked($blog, $picked, 'branding');
            } catch (\InvalidArgumentException $e) {
                $this->flash('error', ucfirst($type).' not changed: '.$e->getMessage());
            }
        }

        $uploadedFiles = [
            'uploaded_banner_files' => $this->request->post['uploaded_banner_files'] ?? '',
            'uploaded_logo_files' => $this->request->post['uploaded_logo_files'] ?? '',
            'uploaded_favicon_files' => $this->request->post['uploaded_favicon_files'] ?? '',
        ];

        $uploadedFileNames = $this->uploader->getUploadedFiles($uploadedFiles);

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
                $path = $this->uploader->moveTempToBlog($fileName, $userId, $type, $blog->mediaKey());
                $paths[$type.'_path'] = $path;

                if ($path) {
                    $this->mediaService->register($blog, $userId, $path, 'branding');
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
