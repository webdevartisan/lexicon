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
use App\Services\WorkflowService;
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
    public function __construct(
        private BlogModel $blogModel,
        private PostModel $post,
        private UserModel $user,
        private BlogSettingsModel $settings,
        private UserPreferencesModel $preference,
        private BlogDeletionService $blogDeletion,
        private WorkflowService $workflowService,
    ) {}

    /**
     * List user's blogs with stats.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $q = trim((string) ($this->request->get['q'] ?? ''));
        $status = trim((string) ($this->request->get['status'] ?? ''));
        $sort = (string) ($this->request->get['sort'] ?? 'updated');
        if (!in_array($sort, ['updated', 'created', 'posts', 'name'], true)) {
            $sort = 'updated';
        }

        // "All Blogs" is the owner workspace listing. Shared blogs live on
        // /dashboard/shared and shouldn't appear here.
        $blogs = $this->blogModel->getBlogsByOwnerWithCounts((int) $user['id']);

        // Merge settings into each blog (banner/logo/theme/locale live there)
        $blogs = array_map(function (array $blog): array {
            $settings = $this->settings->findByBlogId((int) $blog['id']);

            return array_merge($blog, $settings ?? []);
        }, $blogs);

        // Owner's blogs are few, so filter/sort in PHP rather than re-querying.
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $blogs = array_filter($blogs, static function (array $b) use ($needle): bool {
                return str_contains(mb_strtolower((string) ($b['blog_name'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($b['blog_slug'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($b['description'] ?? '')), $needle);
            });
        }

        if (in_array($status, ['draft', 'published', 'archived'], true)) {
            $blogs = array_filter($blogs, static fn (array $b): bool => ($b['status'] ?? '') === $status);
        }

        usort($blogs, static function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'created' => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')),
                'posts' => ((int) ($b['post_count'] ?? 0)) <=> ((int) ($a['post_count'] ?? 0)),
                'name' => strcasecmp((string) ($a['blog_name'] ?? ''), (string) ($b['blog_name'] ?? '')),
                default => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')),
            };
        });

        return $this->view([
            'blogs' => $blogs,
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'selectedBlogId' => (int) ($this->preference->getDefaultBlogId((int) $user['id']) ?? 0),
        ]);
    }

    /**
     * Show blog creation form.
     *
     * Only collects the essentials (name, slug, description); everything
     * optional lives on the settings page shown right after creation.
     */
    public function new(): Response
    {
        $user = auth()->user();
        Gate::authorize('create', BlogResource::class, $user);

        return $this->view();
    }

    /**
     * Create new blog from the essentials, then hand off to settings.
     *
     * New blogs always start as drafts with default settings; the settings
     * page is where visibility, theme, and the rest get configured.
     */
    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $userId = $user['id'];

        Gate::authorize('create', BlogResource::class, $user);

        $validator = $this->validateOrFail([
            'name' => 'required|title|min:2|max:50',
            'slug' => 'required|slug|min:2|max:50|unique:blogs,blog_slug',
            'description' => 'max:1000',
        ]);

        $validated = $validator->validated();

        $identity = [
            'blog_name' => $validated['name'],
            'blog_slug' => $validated['slug'],
            'description' => $validated['description'] ?? '',
            'owner_id' => $userId,
            'status' => 'draft',
            'published_at' => null,
            'archived_at' => null,
        ];

        try {
            $this->blogModel->insert($identity);
            $blogId = $this->blogModel->getInsertID();

            // Default settings; the settings page takes over from here
            $this->settings->createDefaultForBlog($blogId, []);

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

            $this->flash('success', 'Blog created. Configure it below whenever you are ready.');

            return $this->redirect('/dashboard/blog/'.$blogId.'/settings');

        } catch (\PDOException $e) {
            return $this->view('blog.new', [
                'error' => 'Blog slug already exists or database error',
                'old' => $this->request->post,
            ]);
        }
    }

    /**
     * Legacy edit URL, configuration moved to the sectioned settings page.
     *
     * @param  string  $id  Blog ID
     */
    public function edit(string $id): Response
    {
        return $this->redirect('/dashboard/blog/'.$id.'/settings');
    }

    /**
     * Show blog settings grouped into sections.
     *
     * Sections: General, Appearance, SEO, Discussion & Workflow. All sections
     * post to the same update action, so saving from any tab is safe.
     *
     * @param  string  $id  Blog ID
     */
    public function settings(string $id): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog($id);
        Gate::authorize('update', $blog, $user);

        if ($blog->effectiveRoleForUser((int) $user['id']) === 'editor') {
            breadcrumbs()->set([
                ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
                ['label' => 'Shared', 'url' => '/dashboard/shared', 'key' => 'breadcrumbs.shared'],
                ['label' => 'Blog Overview', 'url' => '/dashboard/blog/'.$blog->id().'/show', 'key' => 'breadcrumbs.blogOverview'],
                ['label' => 'Blog Settings', 'url' => null, 'key' => 'breadcrumbs.blogSettings'],
            ], true);
        }

        // Load settings with defaults
        $settings = $this->settings->findByBlogId((int) $id) ?? [
            'theme' => 'folio',
            'banner_path' => '',
            'default_locale' => strtolower($_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'en'),
            'meta_title' => '',
            'meta_description' => '',
            'indexable' => 1,
            'timezone' => 'UTC',
            'comments_enabled' => 1,
            'comments_auto_publish' => 0,
            'replies_auto_publish' => 1,
            'workflow_enabled' => 0,
        ];

        return $this->view([
            'blog' => $blog->toArray(),
            'settings' => $settings,
            'locales' => ['en', 'fr', 'de', 'el', 'ar'],
            'current_locale' => $settings['default_locale'],
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
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $blog = $this->getBlog($id);
        Gate::authorize('update', $blog, $user);

        $userId = $user['id'];
        $blogId = (int) $id;

        $rules = [
            'name' => 'required|title|min:2|max:50',
            'status' => 'in:draft,published,archived',
            'description' => 'max:1000',
            'locale' => 'max:10',
            'timezone' => 'max:100',
            'meta_title' => 'max:200',
            'meta_description' => 'max:500',
            'allow_indexing' => 'boolean',
            'allow_comments' => 'boolean',
            'comments_auto_publish' => 'boolean',
            'replies_auto_publish' => 'boolean',
            'workflow_enabled' => 'boolean',
            'translations_enabled' => 'boolean',
        ];

        $validator = $this->validateOrFail($rules, [
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

        $currentSettings = $this->settings->findByBlogId($blogId) ?? [];

        $settingsData = [
            'default_locale' => $validated['locale'] ?? 'en',
            'timezone' => $validated['timezone'] ?? 'UTC',
            'theme' => $currentSettings['theme'] ?? 'folio',
            'meta_title' => $validated['meta_title'] ?? '',
            'meta_description' => $validated['meta_description'] ?? '',
            'indexable' => isset($validated['allow_indexing']) ? 1 : 0,
            'comments_enabled' => isset($validated['allow_comments']) ? 1 : 0,
            'comments_auto_publish' => isset($validated['comments_auto_publish']) ? 1 : 0,
            'replies_auto_publish' => isset($validated['replies_auto_publish']) ? 1 : 0,
            'workflow_enabled' => isset($validated['workflow_enabled']) ? 1 : 0,
            'translations_enabled' => isset($validated['translations_enabled']) ? 1 : 0,
        ];

        // Update only changed settings
        $settingsChanges = changedFields($settingsData, $currentSettings);

        if (!empty($settingsChanges)) {
            if (!empty($currentSettings)) {
                $this->settings->updateForBlog($blogId, $settingsChanges);
            } else {
                $this->settings->createDefaultForBlog($blogId, $settingsData);
            }

            // Settings feed the public theme (texts, branding), so purge the
            // blog's cached pages including the landing page itself.
            cache()->deletePattern('*:GET:/blog/'.$blog->slug().'*');
        }

        // When the owner disables the workflow mid-flight, reset any in-review/needs_changes posts to draft.
        $workflowWasEnabled = (bool) ($currentSettings['workflow_enabled'] ?? false);
        $workflowNowEnabled = (bool) ($settingsData['workflow_enabled'] ?? false);
        if ($workflowWasEnabled && !$workflowNowEnabled) {
            $this->workflowService->disableWorkflow($blogId, $userId);
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

        // Land back on the tab the user saved from
        $section = (string) $this->request->postParam('active_section');
        $anchor = in_array($section, ['general', 'seo', 'discussion'], true) ? '#'.$section : '';

        return $this->redirect('/dashboard/blog/'.$blogId.'/settings'.$anchor);
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

        // Overview is the admin surface. Reviewers, authors and contributors
        // have role-specific landings on /dashboard/shared and shouldn't drop
        // into blog configuration by URL.
        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);
        if (!in_array($blogRole, ['owner', 'editor'], true)) {
            throw new PageNotFoundException("Blog overview not available for role: {$blogRole}.");
        }

        // Editors arrive through Shared; the static pattern assumes All Blogs.
        if ($blogRole === 'editor') {
            breadcrumbs()->set([
                ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
                ['label' => 'Shared', 'url' => '/dashboard/shared', 'key' => 'breadcrumbs.shared'],
                ['label' => 'Blog Overview', 'url' => null, 'key' => 'breadcrumbs.blogOverview'],
            ], true);
        }

        $settings = $this->settings->findByBlogId((int) $id);

        // Blog overview shows all posts in the blog, not just the viewer's own.
        $stats = [
            'published' => $this->post->countByBlogIdAndStatus((int) $id, 'published'),
            'draft' => $this->post->countByBlogIdAndStatus((int) $id, 'draft'),
            'pending' => $this->post->countByBlogIdAndStatus((int) $id, 'pending'),
            'archived' => $this->post->countByBlogIdAndStatus((int) $id, 'archived'),
            'comments' => $this->post->countCommentsByBlogIdAndStatus((int) $id, 'approved'),
            'comments_pending' => $this->post->countCommentsByBlogIdAndStatus((int) $id, 'pending'),
        ];
        $stats['total'] = $stats['published'] + $stats['draft'] + $stats['pending'];

        // Recent published posts blog-wide (all authors).
        $recent = $this->post->findByAuthorWithFiltersPagination(
            authorId: (int) $user['id'], page: 1, perPage: 6,
            blogId: (int) $id, status: 'published'
        )['data'];

        return $this->view([
            'user' => $user,
            'blog' => $blog->toArray(),
            'settings' => $settings,
            'stats' => $stats,
            'recent' => $recent,
            'blogRole' => $blogRole,
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
        csrf()->assertValid($this->request->postParam('_token'));

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

            return $this->redirect("/dashboard/blog/{$blogId}/settings");
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

            return $this->redirect("/dashboard/blog/{$blogId}/settings");
        }
    }

    /**
     * Unpublish blog.
     *
     * @param  string  $id  Blog ID (from route)
     */
    public function unpublish(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

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
        csrf()->assertValid($this->request->postParam('_token'));

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
        // Collaborator management was consolidated into CollaboratorController.
        // Redirect the legacy route to the canonical team page.
        return $this->redirect(lurl("/dashboard/blog/{$id}/team"));
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
}
