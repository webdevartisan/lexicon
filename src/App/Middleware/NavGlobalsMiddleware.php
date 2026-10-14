<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth;
use App\Gate;
use App\Models\BlogModel;
use App\Models\NotificationModel;
use App\Models\UserPreferencesModel;
use App\Services\LocaleState;
use App\Services\NavigationService;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * NavGlobalsMiddleware
 *
 * populates global template variables for navigation menus.
 * This middleware determines the navigation area, fetches the selected blog
 * using existing model methods, and passes structured nav data to all views.
 */
class NavGlobalsMiddleware
{
    /** Cache-busting stamp for the rendered sidebar markup. */
    private const SIDEBAR_MARKUP_VERSION = 8;

    /**
     * @var NavigationService Navigation service instance
     */
    private NavigationService $nav;

    /**
     * @var Auth Authentication service
     */
    private Auth $auth;

    /**
     * @var TemplateViewerInterface Template viewer for adding globals
     */
    private TemplateViewerInterface $viewer;

    /**
     * @var UserPreferencesModel User preferences model
     */
    private UserPreferencesModel $preferencesModel;

    /**
     * @var BlogModel Blog model
     */
    private BlogModel $blogModel;

    /**
     * @var NotificationModel Notification model
     */
    private NotificationModel $notificationModel;

    /**
     * Constructor
     *
     * inject all dependencies needed to determine navigation context.
     *
     * @param  NavigationService  $nav  Navigation service
     * @param  Auth  $auth  Authentication service
     * @param  TemplateViewerInterface  $viewer  Template viewer
     * @param  UserPreferencesModel  $preferencesModel  User preferences model
     * @param  BlogModel  $blogModel  Blog model
     * @param  NotificationModel  $notificationModel  Notification model
     */
    public function __construct(
        NavigationService $nav,
        Auth $auth,
        TemplateViewerInterface $viewer,
        UserPreferencesModel $preferencesModel,
        BlogModel $blogModel,
        NotificationModel $notificationModel,
        private \App\Services\ViewerContext $viewerContext,
        private \App\Services\ActiveBlog $activeBlog,
        private \App\Services\BlogRoleLabels $roleLabels,
        private \App\Models\BlogSettingsModel $blogSettings,
    ) {
        $this->nav = $nav;
        $this->auth = $auth;
        $this->viewer = $viewer;
        $this->preferencesModel = $preferencesModel;
        $this->blogModel = $blogModel;
        $this->notificationModel = $notificationModel;
    }

    /**
     * Handle middleware processing
     *
     * determine the navigation area, load the selected blog if any
     * (using your existing BlogResource pattern), and add navigation globals.
     *
     * @param  mixed  $request  Request object
     * @param  mixed  $handler  Next handler in the chain
     * @return mixed Response from handler
     */
    public function process($request, $handler)
    {
        $path = $request->uri ?? '/';

        // determine which navigation area to use based on the URL path.
        $area = str_starts_with($path, '/admin') ? 'admin'
            : (str_starts_with($path, '/dashboard') ? 'back' : 'front');

        $selectedBlog = null;
        $userBlogs = []; // id => ['name', 'status', 'role', 'roleLabel', 'shared'] for the topbar switcher
        $selectedBlogId = 0;
        $isReader = false;

        if ($area === 'back' && $this->auth->check()) {
            $user = $this->auth->user();
            $userId = (int) $user['id'];

            foreach ($this->blogModel->listAccessibleBlogs($userId) as $b) {
                $role = (string) $b['user_role'];
                $userBlogs[(int) $b['id']] = [
                    'name' => (string) $b['blog_name'],
                    'status' => (string) $b['status'],
                    'role' => $role,
                    'roleLabel' => $this->roleLabels->label($role),
                    'shared' => (bool) $b['shared'],
                ];
            }
            $isReader = $userBlogs === [];

            $selectedBlogId = (int) ($this->preferencesModel->getDefaultBlogId($userId) ?? 0);

            // A page about one blog makes that blog the active one, so the sidebar always matches the page.
            if ($request->method === 'GET' && preg_match('#^/dashboard/blog/(\d+)(?:/|$)#', $path, $m) === 1
                && (int) $m[1] !== $selectedBlogId) {
                $urlBlog = $this->blogModel->getBlog((int) $m[1]);
                if ($urlBlog !== false && $this->activeBlog->follow($user, $urlBlog, $selectedBlogId)) {
                    $selectedBlog = $urlBlog;
                    $selectedBlogId = $urlBlog->id();
                }
            }

            if ($selectedBlog === null && $selectedBlogId > 0) {
                $blog = $this->blogModel->getBlog($selectedBlogId);
                if ($blog !== false && Gate::allows('view', $blog, $user)) {
                    $selectedBlog = $blog;
                } else {
                    // Deleted, left or revoked: forget it, and the pages send the user to Blogs.
                    $this->preferencesModel->clearDefaultBlogId($userId);
                    $selectedBlogId = 0;
                }
            }
        }

        // generate navigation items with blog context and per-user predicates
        $items = $this->nav->for($area, $path, $selectedBlog, [
            'isReader' => $isReader,
            'isCreator' => !$isReader,
            'workflowEnabled' => $selectedBlog !== null && !empty($this->blogSettings->findByBlogId($selectedBlog->id())['workflow_enabled']),
        ]);
        $user = $this->auth->user();

        // The topbar bell. Dashboard pages show the person's own inbox with the
        // same count ViewerContext gives the front and every theme, so the two
        // bells cannot disagree. Admin pages show the separate operations inbox.
        // Rows are not rendered here: the shared panel loads them on first open.
        $notifications = ['enabled' => false, 'count' => 0, 'listPath' => '/dashboard/notifications'];

        if (($area === 'back' || $area === 'admin') && $user !== null) {
            try {
                $notifications = [
                    'enabled' => true,
                    'count' => $area === 'admin'
                        ? $this->notificationModel->unreadCount((int) $user['id'], 'admin')
                        : (int) ($this->viewerContext->current()['unread_notifications'] ?? 0),
                    'listPath' => $area === 'admin' ? '/admin/notifications' : '/dashboard/notifications',
                ];
            } catch (\Throwable $e) {
                // Logged and hidden: a broken count must not take the page down with it.
                error_log('Notifications query failed: '.$e->getMessage());
            }
        }

        // Per-user sidebar cache key. The active blog is in it, and the links hash
        // follows the role, so switching blog or role swaps the sidebar. The
        // mode suffix swaps the cached sidebar the moment a reader turns creator.
        // The locale suffix matters because the sidebar labels are translated and
        // the entry lives for a year: without it, changing the interface language
        // would leave the navigation in the old one until the cache expired.
        // Bump SIDEBAR_MARKUP_VERSION whenever the partial's markup changes:
        // entries live for a year, so without it everyone already holding one
        // keeps the old sidebar until it expires.
        // The links hash covers what the user and blog alone don't decide, such as a
        // blog's second author opening its Authors page.
        $chromeLocale = LocaleState::get()->chromeLocale;
        $sidebarCacheKey = $area.':sidebar:nav-structure:v'.self::SIDEBAR_MARKUP_VERSION.':u-'.(int) ($user['id'] ?? 0).':b-'.$selectedBlogId.($isReader ? ':m-r' : ':m-c').':l-'.$chromeLocale.':n-'.self::linksHash($items);

        // add navigation globals to all templates
        $this->viewer->addGlobals([
            'nav_items' => $items,
            'nav_area' => $area,
            'current_user' => $user,
            'selected_blog' => $selectedBlog, // BlogResource or null
            'has_blog_context' => $selectedBlog !== null, // Convenient boolean flag
            'area' => $area,
            'notifications' => $notifications,
            'user_blogs' => $userBlogs,
            'selected_blog_id' => $selectedBlogId,
            'is_reader' => $isReader, // no owned or shared blogs: reading-hub mode
            'sidebar_cache_key' => $sidebarCacheKey,
            // The masthead menu is the same on the Lexicon front and inside
            // every blog theme, so its viewer summary is a global rather than
            // something each controller remembers to pass. Memoised in the
            // service, so the blog controller asking for it again is free.
            'viewer' => $this->viewerContext->current(),
            // Locale-free, since pre-routing has already stripped the prefix.
            'current_path' => parse_url($path, PHP_URL_PATH) ?: '/',
        ]);

        return $handler->handle($request);
    }

    /**
     * A short hash of every link the sidebar shows, ignoring which one is current,
     * so the cached markup changes exactly when the visible links do.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private static function linksHash(array $items): string
    {
        $links = [];
        foreach ($items as $item) {
            $links[] = $item['href'];
            foreach ($item['children'] ?? [] as $child) {
                $links[] = $child['href'];
            }
        }

        return substr(md5(implode('|', $links)), 0, 8);
    }
}
