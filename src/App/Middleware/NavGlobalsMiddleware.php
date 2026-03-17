<?php

namespace App\Middleware;

use App\Auth;
use App\Gate;
use App\Models\BlogModel;
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
     * Constructor
     *
     * inject all dependencies needed to determine navigation context.
     *
     * @param  NavigationService  $nav  Navigation service
     * @param  Auth  $auth  Authentication service
     * @param  TemplateViewerInterface  $viewer  Template viewer
     * @param  UserPreferencesModel  $preferencesModel  User preferences model
     * @param  BlogModel  $blogModel  Blog model
     */
    public function __construct(
        NavigationService $nav,
        Auth $auth,
        TemplateViewerInterface $viewer,
        UserPreferencesModel $preferencesModel,
        BlogModel $blogModel
    ) {
        $this->nav = $nav;
        $this->auth = $auth;
        $this->viewer = $viewer;
        $this->preferencesModel = $preferencesModel;
        $this->blogModel = $blogModel;
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

        // fetch the selected blog for dashboard area if user is authenticated
        if ($area === 'back' && $this->auth->check()) {
            $userId = $this->auth->user()['id'];
            $defaultBlogId = $this->preferencesModel->getDefaultBlogId($userId);

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

        // generate navigation items with blog context and policy awareness
        $items = $this->nav->for($area, $path, $selectedBlog);
        $user = $this->auth->user();

        // add navigation globals to all templates
        if (method_exists($this->viewer, 'addGlobals')) {
            $this->viewer->addGlobals([
                'nav_items' => $items,
                'nav_area' => $area,
                'current_user' => $user,
                'selected_blog' => $selectedBlog, // BlogResource or null
                'has_blog_context' => $selectedBlog !== null, // Convenient boolean flag
                'area' => $area,
            ]);
        }

        return $handler->handle($request);
    }
}
