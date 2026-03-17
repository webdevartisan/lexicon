<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Helpers\TimezoneHelper;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Resources\BlogResource;
use App\Services\BlogDeletionService;
use App\Services\UploadService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Dashboard blog management controller.
 *
 * Handles CRUD operations, team management, and file uploads for blogs.
 * Authorization via Gate + BlogPolicy. Multi-table operations delegated to BlogDeletionService.
 */
final class BlogController extends AppController
{
    /**
     * Assignable blog roles for team management.
     *
     * Excludes owner/administrator - those are system-level roles.
     */
    private const ASSIGNABLE_ROLES = ['editor', 'author', 'contributor', 'reviewer', 'viewer'];

    public function __construct(
        private BlogModel $blogModel,
        private PostModel $post,
        private UserModel $user,
        private BlogSettingsModel $settings,
        private UploadService $uploader,
        private UserPreferencesModel $preference,
        private BlogDeletionService $blogDeletion
    ) {}

    /**
     * List user's blogs with stats.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $blogs = $this->blogModel->getBlogsByOwnerWithCounts($user['id']);

        // Merge settings into each blog
        $blogs = array_map(function (array $blog): array {
            $settings = $this->settings->findByBlogId((int) $blog['id']);

            return array_merge($blog, $settings ?? []);
        }, $blogs);

        return $this->view([
            'blogs' => $blogs,
            'status' => 'draft',
            'sort' => 'created',
        ]);
    }

    /**
     * Show blog creation form.
     */
    public function new(): Response
    {
        $user = auth()->user();
        Gate::authorize('create', BlogResource::class, $user);

        return $this->view([
            'themes' => ['default', 'light', 'dark', 'closest'],
            'locales' => ['en', 'fr', 'de', 'el', 'ar'],
            'timezones' => TimezoneHelper::getGroupedTimezones(),
        ]);
    }

    /**
     * Create new blog.
     */
    public function create(): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $userId = $user['id'];

        Gate::authorize('create', BlogResource::class, $user);

        $validator = $this->validateOrFail([
            'name' => 'required|title|min:2|max:50',
            'slug' => 'required|slug|min:2|max:50|unique:blogs,blog_slug',
            'status' => 'in:draft,published,archived,rejected,approved,pending_review',
            'description' => 'max:1000',
            'locale' => 'max:200',
            'timezone' => 'max:200',
            'theme' => 'max:50',
            'meta_title' => 'max:200',
            'meta_description' => 'max:500',
            'allow_indexing' => 'boolean',
        ]);

        $validated = $validator->validated();

        // Prepare blog identity
        $identity = [
            'blog_name' => $validated['name'],
            'blog_slug' => $validated['slug'],
            'description' => $validated['description'] ?? '',
            'owner_id' => $userId,
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'published' ? date('Y-m-d H:i:s') : null,
            'archived_at' => $validated['status'] === 'archived' ? date('Y-m-d H:i:s') : null,
        ];

        // Prepare settings
        $settings = [
            'default_locale' => $validated['locale'] ?? 'en',
            'timezone' => $validated['timezone'] ?? 'UTC',
            'theme' => $validated['theme'] ?? 'default',
            'meta_title' => $validated['meta_title'] ?? '',
            'meta_description' => $validated['meta_description'] ?? '',
            'indexable' => isset($validated['allow_indexing']) ? 1 : 0,
        ];

        try {
            // Insert blog
            $this->blogModel->insert($identity);
            $blogId = $this->blogModel->getInsertID();

            // Handle branding uploads
            $brandingPaths = $this->handleBrandingUploads($userId, $blogId);
            $settings = array_merge($settings, $brandingPaths);

            // Create settings
            $this->settings->createDefaultForBlog($blogId, $settings);

            // Set as default blog
            $this->preference->setDefaultBlogId($userId, $blogId);

            audit()->log(
                $userId,
                'blog.created',
                'blog',
                $blogId,
                ['blog_name' => $validated['name'], 'blog_slug' => $validated['slug']],
                $this->request->ip()
            );

            $this->flash('success', 'Blog created successfully.');

            return $this->redirect('/dashboard');

        } catch (\PDOException $e) {
            return $this->view('blog.new', [
                'error' => 'Blog slug already exists or database error',
                'old' => $this->request->post,
            ]);
        }
    }

    /**
     * Show blog edit form.
     *
     * @param  string  $id  Blog ID
     */
    public function edit(string $id): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog($id);
        Gate::authorize('view', $blog, $user);

        // Load settings with defaults
        $settings = $this->settings->findByBlogId((int) $id) ?? [
            'theme' => 'default',
            'banner_path' => '',
            'default_locale' => strtolower($_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'en'),
            'meta_title' => '',
            'meta_description' => '',
            'indexable' => 1,
            'timezone' => 'UTC',
        ];

        return $this->view([
            'blog' => $blog->toArray(),
            'settings' => $settings,
            'locales' => ['en', 'fr', 'de', 'el', 'ar'],
            'current_locale' => $settings['default_locale'],
            'themes' => ['default', 'light', 'dark', 'closest'],
            'timezones' => TimezoneHelper::getGroupedTimezones(),
        ]);
    }

    /**
     * Update blog.
     *
     * @param  string  $id  Blog ID
     */
    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $blog = $this->getBlog($id);
        Gate::authorize('update', $blog, $user);

        $userId = $user['id'];
        $ownerId = $blog->ownerId();
        $blogId = (int) $id;

        $validator = $this->validateOrFail([
            'name' => 'required|title|min:2|max:50',
            'status' => 'in:draft,published,archived',
            'description' => 'max:1000',
            'theme' => 'max:50',
            'locale' => 'max:10',
            'timezone' => 'max:100',
            'meta_title' => 'max:200',
            'meta_description' => 'max:500',
            'allow_indexing' => 'boolean',
            'allow_comments' => 'boolean',
            'remove_banner' => 'boolean',
            'remove_logo' => 'boolean',
            'remove_favicon' => 'boolean',
        ], [
            'name.required' => 'Blog name is required.',
            'name.title' => 'Blog name contains invalid characters.',
            'status.in' => 'Invalid status value.',
        ]);

        $validated = $validator->validated();

        // Update identity (blogs table)
        $identityChanges = changedFields([
            'blog_name' => $validated['name'] ?? '',
            'description' => $validated['description'] ?? '',
            'status' => $validated['status'] ?? 'draft',
        ], [
            'blog_name' => $blog->name(),
            'description' => $blog->description(),
            'status' => $blog->status(),
        ]);

        // Handle status-related timestamps
        if (isset($identityChanges['status'])) {
            if ($identityChanges['status'] === 'published' && !$blog->publishedAt()) {
                $identityChanges['published_at'] = date('Y-m-d H:i:s');
            }

            if ($identityChanges['status'] === 'archived' && !$blog->archivedAt()) {
                $identityChanges['archived_at'] = date('Y-m-d H:i:s');
            }

            // Clear timestamps when moving away from states
            if ($identityChanges['status'] !== 'published') {
                $identityChanges['published_at'] = null;
            }
            if ($identityChanges['status'] !== 'archived') {
                $identityChanges['archived_at'] = null;
            }
        }

        if (!empty($identityChanges)) {
            $this->blogModel->update($blogId, $identityChanges);
        }

        // Update settings (blog_settings table)
        $currentSettings = $this->settings->findByBlogId($blogId) ?? [];

        $settingsData = [
            'default_locale' => $validated['locale'] ?? 'en',
            'timezone' => $validated['timezone'] ?? 'UTC',
            'theme' => $validated['theme'] ?? 'default',
            'meta_title' => $validated['meta_title'] ?? '',
            'meta_description' => $validated['meta_description'] ?? '',
            'indexable' => isset($validated['allow_indexing']) ? 1 : 0,
            'comments_enabled' => isset($validated['allow_comments']) ? 1 : 0,
        ];

        // Handle branding uploads
        $brandingPaths = $this->handleBrandingUploads($userId, $blogId, $ownerId);
        $settingsData = array_merge($settingsData, $brandingPaths);

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

        // Update only changed settings
        $settingsChanges = changedFields($settingsData, $currentSettings);

        if (!empty($settingsChanges)) {
            if (!empty($currentSettings)) {
                $this->settings->updateForBlog($blogId, $settingsChanges);
            } else {
                $this->settings->createDefaultForBlog($blogId, $settingsData);
            }
        }

        audit()->log(
            $userId,
            'blog.updated',
            'blog',
            $blogId,
            array_merge($identityChanges, $settingsChanges),
            $this->request->ip()
        );

        $this->flash('success', 'Blog updated successfully.');

        return $this->redirect('/dashboard/blog/'.$blogId.'/edit');
    }

    /**
     * Show blog details.
     *
     * @param  string  $id  Blog ID
     */
    public function show(string $id): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog($id);

        Gate::authorize('view', $blog, $user);

        $settings = $this->settings->findByBlogId((int) $id);

        return $this->view([
            'user' => $user,
            'blog' => $blog->toArray(),
            'posts' => $blog->posts(),
            'settings' => $settings,
        ]);
    }

    /**
     * Show blog deletion confirmation.
     *
     * @param  string  $id  Blog ID
     */
    public function delete(string $id): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog($id);
        Gate::authorize('delete', $blog, $user);

        $settings = $this->settings->findByBlogId((int) $id) ?? [];

        // Gather deletion impact stats
        $stats = [
            'postCount' => $this->post->countByBlogId((int) $id),
            'commentCount' => $this->post->countCommentsByBlogId((int) $id),
            'collaboratorCount' => $this->blogModel->countCollaborators((int) $id),
        ];

        return $this->view([
            'blog' => $blog->toArray(),
            'settings' => $settings,
            'stats' => $stats,
        ]);
    }

    /**
     * Process blog deletion.
     *
     * Requires password confirmation for security. Delegates to BlogDeletionService
     * for cascading deletion across 6 tables and file cleanup.
     *
     * @param  string  $id  Blog ID
     */
    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $blog = $this->getBlog($id);
        Gate::authorize('delete', $blog, $user);

        $userId = (int) $user['id'];
        $blogId = (int) $id;
        $ownerId = $blog->ownerId();
        $blogArray = $blog->toArray();

        // Require password confirmation for security
        $password = $this->request->post['password'] ?? '';
        if (!$this->user->verifyPassword($userId, $password)) {
            $this->flash('error', 'Incorrect password. Blog deletion cancelled.');

            return $this->redirect("/dashboard/blog/{$blogId}/edit");
        }

        try {
            // Delegate to service (handles 6 tables + files + preferences)
            $stats = $this->blogDeletion->deleteBlog($blogId, $userId, $ownerId);

            audit()->log(
                $userId,
                'blog.deleted',
                'blog',
                $blogId,
                [
                    'blog_name' => $blogArray['blog_name'] ?? 'Unnamed Blog',
                    'blog_slug' => $blogArray['blog_slug'] ?? '',
                    'deleted_posts' => $stats['deleted_posts'],
                    'deleted_comments' => $stats['deleted_comments'],
                ],
                $this->request->ip()
            );

            $this->flash('success', 'Blog "'.($blogArray['blog_name'] ?? 'Unnamed').'" has been permanently deleted.');

            return $this->redirect('/dashboard');

        } catch (\Exception $e) {
            error_log("Blog deletion failed for blog {$blogId}: ".$e->getMessage());
            $this->flash('error', 'Failed to delete blog. Please try again or contact support.');

            return $this->redirect("/dashboard/blog/{$blogId}/edit");
        }
    }

    /**
     * Unpublish blog.
     *
     * @param  string  $id  Blog ID (from route)
     */
    public function unpublish(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $blogId = (int) $id;
        $blog = $this->getBlog($blogId);
        Gate::authorize('update', $blog, $user);

        $this->blogModel->unpublishBlog($blogId);

        audit()->log(
            $user['id'],
            'blog.unpublished',
            'blog',
            $blogId,
            ['status' => 'draft'],
            $this->request->ip()
        );

        return $this->redirect("/dashboard/blogs/{$blogId}/show");
    }

    /**
     * Publish blog.
     *
     * @param  string  $id  Blog ID (from route)
     */
    public function publish(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $blogId = (int) $id;
        $blog = $this->getBlog($blogId);
        Gate::authorize('update', $blog, $user);

        $this->blogModel->publishBlog($blogId);

        audit()->log(
            $user['id'],
            'blog.published',
            'blog',
            $blogId,
            ['status' => 'published'],
            $this->request->ip()
        );

        return $this->redirect("/dashboard/blogs/{$blogId}/show");
    }

    /**
     * Show team management screen.
     *
     * Requires manageUsers permission to prevent privilege escalation.
     *
     * @param  string  $id  Blog ID
     */
    public function users(string $id): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog($id);
        Gate::authorize('manageUsers', $blog, $user);

        $assigned = $this->blogModel->getBlogUsers((int) $id);
        $availableUsers = $this->blogModel->getAvailableUsers((int) $id);

        return $this->view('blog.users', [
            'blog' => $blog->toArray(),
            'assigned' => $assigned,
            'availableUsers' => $availableUsers,
            'assignableRoles' => self::ASSIGNABLE_ROLES,
        ]);
    }

    /**
     * Add/remove blog team members.
     *
     * Validates role against whitelist. Uses CSRF protection.
     *
     * @param  string  $id  Blog ID
     */
    public function updateUsers(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $blog = $this->getBlog($id);
        Gate::authorize('manageUsers', $blog, $user);

        $action = $this->request->post['action'] ?? '';
        $targetUserId = (int) ($this->request->post['user_id'] ?? 0);
        $role = trim($this->request->post['role'] ?? '');

        if ($action === 'add') {
            if (!in_array($role, self::ASSIGNABLE_ROLES, true)) {
                throw new \InvalidArgumentException('Invalid role.');
            }
            $this->blogModel->addUserToBlog((int) $id, $targetUserId, $role, $user['id']);

            audit()->log(
                $user['id'],
                'blog.user_added',
                'blog',
                (int) $id,
                ['target_user_id' => $targetUserId, 'role' => $role],
                $this->request->ip()
            );

        } elseif ($action === 'remove') {
            $this->blogModel->revokeUserFromBlog((int) $id, $targetUserId);

            audit()->log(
                $user['id'],
                'blog.user_removed',
                'blog',
                (int) $id,
                ['target_user_id' => $targetUserId],
                $this->request->ip()
            );
        }

        return $this->redirect("/dashboard/blogs/{$id}/users");
    }

    /**
     * Get blog resource or throw 404.
     *
     * @param  string|int  $id  Blog ID
     *
     * @throws PageNotFoundException
     */
    private function getBlog(string|int $id): BlogResource
    {
        $blog = $this->blogModel->getBlog($id);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '$id' not found.");
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

        $uploadedFiles = [
            'uploaded_banner_files' => $this->request->post['uploaded_banner_files'] ?? '',
            'uploaded_logo_files' => $this->request->post['uploaded_logo_files'] ?? '',
            'uploaded_favicon_files' => $this->request->post['uploaded_favicon_files'] ?? '',
        ];

        $uploadedFileNames = $this->uploader->getUploadedFiles($uploadedFiles);

        [$dir, $baseUrl] = $this->uploader->blogBrandingPath($ownerId, $blogId);

        $paths = [];

        foreach ($uploadedFileNames as $fieldName => $fileName) {
            if (empty($fileName)) {
                continue;
            }

            // Extract type: uploaded_banner_files to banner
            $parts = explode('_', $fieldName);
            $type = $parts[1];

            try {
                $paths[$type.'_path'] = $this->uploader->moveTempToBranding(
                    $fileName,
                    $ownerId,
                    $blogId,
                    $type,
                    $dir,
                    $baseUrl
                );
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
