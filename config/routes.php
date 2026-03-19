<?php

declare(strict_types=1);

use Framework\Core\Router;

$router = new Router();

$router->add('/csrf-token', [
    'controller' => 'HomeController',
    'action' => 'csrfToken',
    'method' => 'GET',
]);

$router->add('/geo', [
    'controller' => 'GeoController',
    'action' => 'timezone',
    'method' => 'GET',
]);

$router->add('/debug-cache', [
    'controller' => 'HomeController',
    'action' => 'debugCache',
    'method' => 'GET',
]);

$router->add('/consent', [
    'controller' => 'ConsentController',
    'action' => 'store',
    'method' => 'POST',
]);

$router->add('/consent/withdraw', [
    'controller' => 'ConsentController',
    'action' => 'withdraw',
    'method' => 'POST',
]);

// Public routes
$router->add('/', ['controller' => 'HomeController',    'action' => 'index', 'method' => 'GET']);
$router->add('/home', ['controller' => 'HomeController',    'action' => 'index', 'method' => 'GET']);

$router->add('/blogs', ['controller' => 'BlogController',   'action' => 'index', 'method' => 'GET']);
$router->add('/products', ['controller' => 'Products', 'action' => 'index', 'method' => 'GET']);

// Route with slug parameter, only allowing word characters and hyphen
$router->add('/product/{slug:[\w-]+}', ['controller' => 'Products', 'action' => 'show',  'method' => 'GET']);

// More specific product page route with parameters to avoid conflicts
$router->add('/{title}/{id:\d+}/{page:\d+}', ['controller' => 'Products', 'action' => 'showPage', 'method' => 'GET']);

// Profile route.
$router->add('/profile/{slug:[A-Za-z0-9_\-]+}', [
    'controller' => 'PublicProfileController',
    'action' => 'show',
    'method' => 'GET',
]);

$router->group([
    'prefix' => '/',
    'namespace' => 'Auth',
], function (Router $r) {
    $r->add('/login', ['controller' => 'AuthController',   'action' => 'index', 'method' => 'GET']);
    $r->add('/login/submit', ['controller' => 'AuthController',   'action' => 'submit', 'method' => 'POST']);
    $r->add('/logout', ['controller' => 'AuthController',   'action' => 'logout', 'method' => 'GET']);

    $r->add('/register', ['controller' => 'RegisterController', 'action' => 'show', 'method' => 'GET']);
    $r->add('/register/submit', ['controller' => 'RegisterController', 'action' => 'submit', 'method' => 'POST']);

    // Password Reset Routes
    $r->add('/password/forgot', ['controller' => 'PasswordController',   'action' => 'showForgotForm', 'method' => 'GET']);
    $r->add('/password/forgot', ['controller' => 'PasswordController',   'action' => 'submit', 'method' => 'POST']);
    $r->add('/password/reset/{token}', ['controller' => 'PasswordController',   'action' => 'showResetForm', 'method' => 'GET']);
    $r->add('/password/reset', ['controller' => 'PasswordController',   'action' => 'resetPassword', 'method' => 'POST']);

});

$router->group([
    'prefix' => '/account',
    'namespace' => 'Account',
    'middleware' => ['auth'],
], function (Router $r) {
    $r->add('/settings', ['controller' => 'AccountSettingsController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/settings', ['controller' => 'AccountSettingsController', 'action' => 'update', 'method' => 'POST']);
});

// Grouped routes for user dashboard - all require authentication middleware
$router->group([
    'prefix' => '/dashboard',
    'namespace' => 'Dashboard',
    'middleware' => ['auth'],
], function (Router $r) {
    $r->add('/', ['controller' => 'HomeController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/search', ['controller' => 'HomeController', 'action' => 'search', 'method' => 'POST']);
    $r->add('/setDefaultBlog', ['controller' => 'HomeController', 'action' => 'setDefaultBlog', 'method' => 'POST']);
    $r->add('/profile', ['controller' => 'ProfileController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/profile/update', ['controller' => 'ProfileController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/profile/update/password', ['controller' => 'ProfileController', 'action' => 'updatePassword', 'method' => 'POST']);
    $r->add('/profile/avatar', ['controller' => 'ProfileController', 'action' => 'uploadAvatar', 'method' => 'POST']);
    $r->add('/profile/avatar/remove', ['controller' => 'ProfileController', 'action' => 'removeAvatar', 'method' => 'POST']);
    $r->add('/blog', ['controller' => 'BlogController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/blog/new', ['controller' => 'BlogController', 'action' => 'new', 'method' => 'GET']);
    $r->add('/post', ['controller' => 'PostController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/post/{id:\d+}/review', ['controller' => 'PostController', 'action' => 'review', 'method' => 'GET']);
    $r->add('/post/new', ['controller' => 'PostController', 'action' => 'new', 'method' => 'GET']);
    $r->add('/post/create', ['controller' => 'PostController', 'action' => 'create', 'method' => 'POST']);
    $r->add('/posts/image-upload', ['controller' => 'UploadController', 'action' => 'tinymceImage', 'method' => 'POST']);
    $r->add('/export', ['controller' => 'DataExport', 'action' => 'start', 'method' => 'GET']);
    $r->add('/delete-account', [
        'controller' => 'AccountDeletionController',
        'action' => 'confirm',
        'method' => 'GET',
    ]);
    $r->add('/delete-account', [
        'controller' => 'AccountDeletionController',
        'action' => 'destroy',
        'method' => 'POST',
    ]);

    $r->add('/upload', [
        'controller' => 'FileUploadController',
        'action' => 'upload',
        'method' => 'POST',
    ]);
    // API Routes - Blog deletion stats for confirmation modal
    $r->add('/api/blog/{id:\d+}/deletion-stats', [
        'controller' => 'Api\BlogApiController',
        'action' => 'getDeletionStats',
        'method' => 'GET',
    ]);
    $r->add('/post/autosave', [
        'controller' => 'PostController',
        'action' => 'autosave',
        'method' => 'POST',
    ]);

    // Generic CRUD routes for dashboard.
    //if (env('APP_DEBUG', false)) {
        $r->add('/{controller}/create', ['action' => 'create', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/users', ['action' => 'users', 'method' => 'GET']);
        $r->add('/{controller}/{id:\d+}/users', ['action' => 'updateUsers', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/show', ['action' => 'show', 'method' => 'GET']);
        $r->add('/{controller}/{id:\d+}/edit', ['action' => 'edit', 'method' => 'GET']);
        $r->add('/{controller}/{id:\d+}/update', ['action' => 'update', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/delete', ['action' => 'delete', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/destroy', ['action' => 'destroy', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/draft', ['action' => 'draft', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/archive', ['action' => 'archive', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/publish', ['action' => 'publish', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/workflow/request-review', ['action' => 'requestReview', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/workflow/needs-changes', ['action' => 'markNeedsChanges', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/workflow/approve', ['action' => 'approve', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/workflow/reset', ['action' => 'resetWorkflowToDraft', 'method' => 'POST']);
    //}
});

// Admin route group with authentication and admin role enforced
$router->group([
    'prefix' => '/admin',
    'namespace' => 'Admin',
    'middleware' => ['auth', 'role:administrator'],
], function (Router $r) {
    $r->add('/', ['controller' => 'ControlPanelController', 'action' => 'index', 'method' => 'GET']);

    $r->add('/cache', ['controller' => 'CacheController', 'action' => 'index']);
    $r->add('/cache/prune', ['controller' => 'CacheController', 'action' => 'prune', 'method' => 'POST']);
    $r->add('/cache/clear', ['controller' => 'CacheController', 'action' => 'clear', 'method' => 'POST']);
    $r->add('/cache/delete-pattern', ['controller' => 'CacheController', 'action' => 'delete-pattern', 'method' => 'POST']);

    // Email testing routes
    $r->add('/email-test', ['controller' => 'EmailTestController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/email-test/preview', ['controller' => 'EmailTestController', 'action' => 'preview', 'method' => 'GET']);
    $r->add('/email-test/render-html', ['controller' => 'EmailTestController', 'action' => 'renderHtml', 'method' => 'GET']);
    $r->add('/email-test/send-test', ['controller' => 'EmailTestController', 'action' => 'sendTest', 'method' => 'POST']);
    $r->add('/email-test/test-config', ['controller' => 'EmailTestController', 'action' => 'testConfig', 'method' => 'POST']);

    // Comment moderation custom routes (before generic patterns)
    $r->add('/comment/{id:\d+}/approve', ['controller' => 'CommentController', 'action' => 'approve', 'method' => 'POST']);

    // Generic admin routes are powerful and should not be exposed in production.
    if (env('APP_DEBUG', false)) {
        $r->add('/{controller}/{action}');
        $r->add('/{controller}/{id:\d+}/show', ['action' => 'show', 'method' => 'GET']);
        $r->add('/{controller}/{id:\d+}/edit', ['action' => 'edit', 'method' => 'GET']);
        $r->add('/{controller}/{id:\d+}/update', ['action' => 'update', 'method' => 'POST']);
        $r->add('/{controller}/{id:\d+}/delete', ['action' => 'delete', 'method' => 'GET']);
        $r->add('/{controller}/{id:\d+}/destroy', ['action' => 'destroy', 'method' => 'POST']);
    }
});

// Blog routes under a blog-specific slug with theme middleware
$router->group([
    'prefix' => '/blog/{blogSlug:[A-Za-z0-9_-]+}',
    'middleware' => ['theme'],
], function (Router $r) {
    $r->add('/', ['controller' => 'BlogController', 'action' => 'showBlog', 'method' => 'GET']);
    $r->add('/{postSlug}', ['controller' => 'BlogController', 'action' => 'showBlogPost', 'method' => 'GET']);
});

// Final fallback catch-all route - only enabled in debug to avoid unintentionally
// exposing new controllers/actions via automatic routing in production.
if (env('APP_DEBUG', false)) {
    $router->add('/{controller}/{action}');
}

return $router;
