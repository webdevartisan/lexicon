<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth;
use App\Gate;
use App\Models\BlogModel;
use App\Models\NotificationModel;
use App\Models\UserPreferencesModel;
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
        NotificationModel $notificationModel
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

        // determine which navigation area to use based on the URL path
        $area = str_starts_with($path, '/admin') ? 'admin'
            : (str_starts_with($path, '/dashboard') ? 'back' : 'front');

        $selectedBlog = null;
        $userBlogs = []; // id => ['name','status'] for the topbar blog switcher (owned only)
        $selectedBlogId = 0;
        $isCollaborator = false;

        // fetch the selected blog for dashboard area if user is authenticated
        if ($area === 'back' && $this->auth->check()) {
            $userId = $this->auth->user()['id'];
            $defaultBlogId = $this->preferencesModel->getDefaultBlogId($userId);
            $selectedBlogId = (int) ($defaultBlogId ?? 0);

            // Topbar switcher is the "active owned workspace" selector. Shared
            // blogs have their own surface at /dashboard/shared — mixing them
            // here would re-create the confusion the Shared/Team split fixed.
            try {
                foreach ($this->blogModel->getBlogsByOwnerId((int) $userId) as $b) {
                    $userBlogs[(int) $b['id']] = [
                        'name' => (string) ($b['blog_name'] ?? 'Untitled blog'),
                        'status' => (string) ($b['status'] ?? 'draft'),
                    ];
                }
            } catch (\Throwable $e) {
                error_log('Blog switcher list failed: '.$e->getMessage());
            }

            // Drives visibility of the global "Shared" nav item.
            try {
                $isCollaborator = $this->blogModel->userIsCollaborator((int) $userId);
            } catch (\Throwable $e) {
                error_log('isCollaborator check failed: '.$e->getMessage());
            }

            if ($defaultBlogId !== null && $defaultBlogId > 0) {
                try {
                    // use your existing BlogModel::getBlog method which returns BlogResource
                    $blog = $this->blogModel->getBlog($defaultBlogId);

                    if ($blog !== false) {
                        $user = $this->auth->user();

                        // verify access using your existing Gate/Policy system
                        try {
                            Gate::authorize('view', $blog, $user);
                            $selectedBlog = $blog;
                        } catch (\Exception $e) {
                            // clear invalid blog preference if access denied
                            error_log("User {$userId} lost access to blog {$defaultBlogId}: ".$e->getMessage());
                            $this->preferencesModel->clearDefaultBlogId($userId);
                        }
                    } else {
                        // clear preference if blog was deleted
                        $this->preferencesModel->clearDefaultBlogId($userId);
                    }
                } catch (\Exception $e) {
                    // log but don't break the request if blog loading fails
                    error_log("Failed to load blog {$defaultBlogId} for user {$userId}: ".$e->getMessage());
                }
            }
        }

        // generate navigation items with blog context and per-user predicates
        $items = $this->nav->for($area, $path, $selectedBlog, [
            'isCollaborator' => $isCollaborator,
        ]);
        $user = $this->auth->user();

        // Build notifications payload for the topbar bell (back area only).
        $notifications = ['enabled' => false, 'items' => [], 'count' => 0];
        if ($area === 'back' && $user !== null) {
            try {
                $unreadCount = $this->notificationModel->unreadCount((int) $user['id']);
                $notifItems = $this->notificationModel->findForUser((int) $user['id'], 8, onlyUnread: true);
                $notifications = [
                    'enabled' => true,
                    'items' => $notifItems,
                    'count' => $unreadCount,
                ];
            } catch (\Throwable $e) {
                error_log('Notifications query failed: '.$e->getMessage());
            }
        }

        // Per-user sidebar cache key — keeps the Shared item visibility correct
        // when a user gains/loses collaborator access mid-cache-window.
        $sidebarCacheKey = $area.':sidebar:nav-structure:u-'.(int) ($user['id'] ?? 0).':b-'.$selectedBlogId;

        // add navigation globals to all templates
        $this->viewer->addGlobals([
            'nav_items' => $items,
            'nav_area' => $area,
            'current_user' => $user,
            'selected_blog' => $selectedBlog, // BlogResource or null
            'has_blog_context' => $selectedBlog !== null, // Convenient boolean flag
            'area' => $area,
            'notifications' => $notifications,
            'user_blogs' => $userBlogs, // id => ['name','status','role'] for topbar switcher
            'selected_blog_id' => $selectedBlogId,
            'is_collaborator' => $isCollaborator, // user has any shared blog access
            'sidebar_cache_key' => $sidebarCacheKey,
        ]);

        return $handler->handle($request);
    }
}
