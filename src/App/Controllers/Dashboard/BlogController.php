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
use App\Presenters\BlogActionPresenter;
use App\Resources\BlogResource;
use App\Services\BlogDeletionService;
use App\Services\BlogRoleLabels;
use App\Services\LocaleRegistry;
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
        private BlogRoleLabels $roleLabels,
    ) {}

    /**
     * Every blog the user works in, owned first, then shared, with the role
     * they hold on each. Opening one makes it the active blog.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $userId = (int) $user['id'];
        $accessible = $this->blogModel->getAccessibleBlogs($userId);

        // Same rule as the Overview: no owned or shared blogs means there is
        // no workspace to show, only the blank slate at "/".
        if ($accessible === []) {
            return $this->redirect('/?welcome=1');
        }

        $q = trim((string) $this->request->getParam('q', ''));
        $status = trim((string) $this->request->getParam('status', ''));
        $sort = (string) $this->request->getParam('sort', 'updated');
        if (!in_array($sort, ['updated', 'created', 'posts', 'name'], true)) {
            $sort = 'updated';
        }

        $blogs = $this->filterAndSort($accessible, $q, $status, $sort);
        $activeBlogId = (int) ($this->preference->getDefaultBlogId($userId) ?? 0);

        $groups = ['owned' => [], 'shared' => []];
        foreach ($blogs as $blog) {
            $groups[$blog['user_role'] === 'owner' ? 'owned' : 'shared'][] = $this->blogCard($blog, $activeBlogId);
        }

        return $this->view([
            'groups' => $groups,
            'hasOwned' => in_array('owner', array_column($accessible, 'user_role'), true),
            'hasShared' => array_filter($accessible, static fn (array $b): bool => $b['user_role'] !== 'owner') !== [],
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
        ]);
    }

    /**
     * The user's blogs are few, so filtering and sorting happen here rather than in SQL.
     *
     * @param  array<int, array<string, mixed>>  $blogs
     * @return array<int, array<string, mixed>>
     */
    private function filterAndSort(array $blogs, string $q, string $status, string $sort): array
    {
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $blogs = array_filter($blogs, static fn (array $b): bool => str_contains(mb_strtolower((string) $b['blog_name']), $needle)
                || str_contains(mb_strtolower((string) $b['blog_slug']), $needle)
                || str_contains(mb_strtolower((string) ($b['description'] ?? '')), $needle));
        }

        if (in_array($status, ['draft', 'published', 'archived'], true)) {
            $blogs = array_filter($blogs, static fn (array $b): bool => $b['status'] === $status);
        }

        usort($blogs, static fn (array $a, array $b): int => match ($sort) {
            'created' => strcmp((string) $b['created_at'], (string) $a['created_at']),
            'posts' => (int) $b['post_count'] <=> (int) $a['post_count'],
            'name' => strcasecmp((string) $a['blog_name'], (string) $b['blog_name']),
            default => strcmp((string) $b['updated_at'], (string) $a['updated_at']),
        });

        return $blogs;
    }

    /**
     * One card on the Blogs page. The settings and team shortcuts follow the
     * role's permissions; those pages still authorize on their own.
     *
     * @param  array<string, mixed>  $blog  Row from getAccessibleBlogs()
     * @return array<string, mixed>
     */
    private function blogCard(array $blog, int $activeBlogId): array
    {
        $role = (string) $blog['user_role'];
        $permissions = $this->blogModel->permissionsForRole($role === 'owner' ? 'blog_owner' : $role);
        $settings = $this->settings->findByBlogId((int) $blog['id']) ?? [];

        return $blog + [
            'banner_path' => $settings['banner_path'] ?? '',
            'is_active' => (int) $blog['id'] === $activeBlogId,
            'is_owner' => $role === 'owner',
            'role_label' => $this->roleLabels->label($role),
            'can_settings' => in_array('edit_own_blog', $permissions, true),
            'can_team' => in_array('manage_team', $permissions, true),
        ];
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

        // Without JavaScript nothing fills the address in, so build it from the name the same way.
        if (trim((string) ($this->request->post['slug'] ?? '')) === '') {
            $this->request->post['slug'] = slugify((string) ($this->request->post['name'] ?? ''));
        }

        $validator = $this->validateOrFail([
            'name' => 'required|title|min:2|max:50',
            'slug' => 'required|slug|min:2|max:50|unique:blogs,blog_slug',
            'description' => 'max:1000',
        ], [
            'slug.required' => 'We could not build a web address from that name. Please type one using letters, numbers and hyphens.',
            'slug.slug' => 'The address can only use lowercase letters, numbers and single hyphens, with no hyphen at the start or end.',
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

        // Load settings with defaults
        $settings = $this->settings->findByBlogId((int) $id) ?? [
            'theme' => 'folio',
            'banner_path' => '',
            'default_locale' => LocaleRegistry::instance()->normalize($this->session->get('locale')) ?? LocaleRegistry::instance()->default(),
            'meta_title' => '',
            'meta_description' => '',
            'indexable' => 1,
            'timezone' => 'UTC',
            'comments_enabled' => 1,
            'comments_auto_publish' => 1,
            'replies_auto_publish' => 1,
            'workflow_enabled' => 0,
        ];

        return $this->view([
            'blog' => $blog->toArray(),
            'blogUrl' => base_url().'/blog/'.$blog->slug(),
            'actions' => BlogActionPresenter::for($blog->status()),
            'backUrl' => '/dashboard',
            'settings' => $settings,
            'locales' => LocaleRegistry::instance()->names(),
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
        ]);

        $validated = $validator->validated();

        // Update identity (blogs table)
        $identityChanges = changedFields([
            'blog_name' => $validated['name'] ?? '',
            'description' => $validated['description'] ?? '',
            'status' => BlogActionPresenter::statusForIntent($this->request->postParam('intent'), $blog->status()),
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
            // Only a language the site offers, so a tampered form cannot strand the blog on one.
            'default_locale' => LocaleRegistry::instance()->normalize($validated['locale'] ?? null) ?? LocaleRegistry::instance()->default(),
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
        $workflowNowEnabled = (bool) $settingsData['workflow_enabled'];
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
        $blogArray = $blog->toArray();

        // Require password confirmation for security
        $password = $this->request->post['password'] ?? '';
        if (!$this->user->verifyPassword($userId, $password)) {
            $this->flash('error', 'Incorrect password. Blog deletion cancelled.');

            return $this->redirect("/dashboard/blog/{$blogId}/settings");
        }

        try {
            // Delegate to service (handles 6 tables + files + preferences)
            $stats = $this->blogDeletion->deleteBlog($blogId, $userId);

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
