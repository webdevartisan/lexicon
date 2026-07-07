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

// Public comment submission
$router->add('/comments/create', [
    'controller' => 'CommentController',
    'action' => 'create',
    'method' => 'POST',
]);

// Public blog invitation landing (reached from the email link; no auth required)
$router->add('/invite/{token:[a-f0-9]+}', ['controller' => 'InviteController', 'action' => 'show', 'method' => 'GET']);
$router->add('/invite/{token:[a-f0-9]+}/accept', ['controller' => 'InviteController', 'action' => 'accept', 'method' => 'POST']);
$router->add('/invite/{token:[a-f0-9]+}/decline', ['controller' => 'InviteController', 'action' => 'decline', 'method' => 'POST']);

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
    $r->add('/shared', ['controller' => 'SharedController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/profile', ['controller' => 'ProfileController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/profile/update', ['controller' => 'ProfileController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/profile/update/password', ['controller' => 'ProfileController', 'action' => 'updatePassword', 'method' => 'POST']);
    $r->add('/profile/avatar', ['controller' => 'ProfileController', 'action' => 'uploadAvatar', 'method' => 'POST']);
    $r->add('/profile/avatar/remove', ['controller' => 'ProfileController', 'action' => 'removeAvatar', 'method' => 'POST']);
    $r->add('/blog', ['controller' => 'BlogController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/blog/new', ['controller' => 'BlogController', 'action' => 'new', 'method' => 'GET']);
    $r->add('/post', ['controller' => 'PostController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/post/bulk', ['controller' => 'PostController', 'action' => 'bulk', 'method' => 'POST']);
    $r->add('/post/{id:\d+}/review', ['controller' => 'PostReviewController', 'action' => 'review', 'method' => 'GET']);
    $r->add('/post/{id:\d+}/feature', ['controller' => 'PostController', 'action' => 'feature', 'method' => 'POST']);
    $r->add('/post/new', ['controller' => 'PostController', 'action' => 'new', 'method' => 'GET']);
    $r->add('/post/create', ['controller' => 'PostController', 'action' => 'create', 'method' => 'POST']);

    // Review pipeline actions. Explicit routes (same URLs the forms already
    // post to) so these hit PostReviewController instead of the generic map.
    $r->add('/posts/{id:\d+}/workflow/review-decision', ['controller' => 'PostReviewController', 'action' => 'reviewDecision', 'method' => 'POST']);
    $r->add('/posts/{id:\d+}/workflow/request-review', ['controller' => 'PostReviewController', 'action' => 'requestReview', 'method' => 'POST']);
    $r->add('/posts/{id:\d+}/workflow/needs-changes', ['controller' => 'PostReviewController', 'action' => 'markNeedsChanges', 'method' => 'POST']);
    $r->add('/posts/{id:\d+}/workflow/approve', ['controller' => 'PostReviewController', 'action' => 'approve', 'method' => 'POST']);
    $r->add('/posts/{id:\d+}/workflow/reset', ['controller' => 'PostReviewController', 'action' => 'resetWorkflowToDraft', 'method' => 'POST']);
    $r->add('/posts/{id:\d+}/workflow/assign-reviewer', ['controller' => 'PostReviewController', 'action' => 'assignReviewer', 'method' => 'POST']);
    $r->add('/posts/{id:\d+}/workflow/unassign-reviewer', ['controller' => 'PostReviewController', 'action' => 'unassignReviewer', 'method' => 'POST']);
    $r->add('/posts/image-upload', ['controller' => 'UploadController', 'action' => 'tinymceImage', 'method' => 'POST']);

    // Comment moderation (blog-scoped).
    $r->add('/blog/{blogId:\d+}/comments', ['controller' => 'CommentController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/comment/bulk', ['controller' => 'CommentController', 'action' => 'bulk', 'method' => 'POST']);

    // Categories & tags management (blog-scoped).
    $r->add('/blog/{blogId:\d+}/categories', ['controller' => 'CategoryController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/blog/{blogId:\d+}/categories', ['controller' => 'CategoryController', 'action' => 'handle', 'method' => 'POST']);

    // Media library (blog-scoped).
    $r->add('/blog/{blogId:\d+}/media', ['controller' => 'MediaController', 'action' => 'index',   'method' => 'GET']);
    $r->add('/blog/{blogId:\d+}/media/list', ['controller' => 'MediaController', 'action' => 'list',    'method' => 'GET']);
    $r->add('/blog/{blogId:\d+}/media', ['controller' => 'MediaController', 'action' => 'store',   'method' => 'POST']);
    $r->add('/blog/{blogId:\d+}/media/{id:\d+}/destroy', ['controller' => 'MediaController', 'action' => 'destroy', 'method' => 'POST']);

    // Per-blog review queue (anyone with reviewer-capable role on the blog).
    $r->add('/blog/{blogId:\d+}/review-queue', ['controller' => 'PostReviewController', 'action' => 'reviewQueue', 'method' => 'GET']);

    // Per-blog posts index for owners and editors. Kept separate from the
    // personal /dashboard/post surface so the two contexts never mix.
    $r->add('/blog/{blogId:\d+}/posts', ['controller' => 'PostController', 'action' => 'blogPosts', 'method' => 'GET']);

    // Author/contributor workspace: their own writing inside one shared blog.
    $r->add('/blog/{blogId:\d+}/workspace', ['controller' => 'PostController', 'action' => 'workspace', 'method' => 'GET']);

    // Team / collaborator management (owner-only actions; leave is self-service).
    $r->add('/blog/{blogId:\d+}/team', ['controller' => 'CollaboratorController', 'action' => 'team', 'method' => 'GET']);
    $r->add('/blog/{blogId:\d+}/team/invite', ['controller' => 'CollaboratorController', 'action' => 'invite', 'method' => 'POST']);
    $r->add('/blog/{blogId:\d+}/team/cancel-invite', ['controller' => 'CollaboratorController', 'action' => 'cancelInvite', 'method' => 'POST']);
    $r->add('/blog/{blogId:\d+}/team/leave', ['controller' => 'CollaboratorController', 'action' => 'leave', 'method' => 'POST']);
    $r->add('/blog/{blogId:\d+}/team/{userId:\d+}/role', ['controller' => 'CollaboratorController', 'action' => 'changeRole', 'method' => 'POST']);
    $r->add('/blog/{blogId:\d+}/team/{userId:\d+}/revoke', ['controller' => 'CollaboratorController', 'action' => 'revoke', 'method' => 'POST']);
    $r->add('/comment/{id:\d+}/approve', ['controller' => 'CommentController', 'action' => 'approve', 'method' => 'POST']);
    $r->add('/comment/{id:\d+}/spam', ['controller' => 'CommentController', 'action' => 'spam', 'method' => 'POST']);
    $r->add('/comment/{id:\d+}/unapprove', ['controller' => 'CommentController', 'action' => 'unapprove', 'method' => 'POST']);
    $r->add('/comment/{id:\d+}/destroy', ['controller' => 'CommentController', 'action' => 'destroy', 'method' => 'POST']);

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

    // Notifications
    $r->add('/notifications', ['controller' => 'NotificationController', 'action' => 'index',        'method' => 'GET']);
    $r->add('/notifications/unread-count', ['controller' => 'NotificationController', 'action' => 'unreadCount',  'method' => 'GET']);
    $r->add('/notifications/read-all', ['controller' => 'NotificationController', 'action' => 'markAllRead',  'method' => 'POST']);
    $r->add('/notifications/{id:\d+}/read', ['controller' => 'NotificationController', 'action' => 'markRead',     'method' => 'POST']);
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
    // if (env('APP_DEBUG', false)) {
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
    // }
});

// Admin route group. Authentication is middleware; authorization is enforced
// per controller via AppController::beforeAction() + SystemPolicy abilities,
// so individual areas can be opened to non-admin roles through permissions.
$router->group([
    'prefix' => '/admin',
    'namespace' => 'Admin',
    'middleware' => ['auth'],
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

    // User management
    $r->add('/users', ['controller' => 'UserController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/users/new', ['controller' => 'UserController', 'action' => 'new', 'method' => 'GET']);
    $r->add('/users/create', ['controller' => 'UserController', 'action' => 'create', 'method' => 'POST']);
    $r->add('/users/{id:\d+}/edit', ['controller' => 'UserController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/users/{id:\d+}/update', ['controller' => 'UserController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/users/{id:\d+}/delete', ['controller' => 'UserController', 'action' => 'delete', 'method' => 'GET']);
    $r->add('/users/{id:\d+}/destroy', ['controller' => 'UserController', 'action' => 'destroy', 'method' => 'POST']);

    // Blog management
    $r->add('/blogs', ['controller' => 'BlogController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/blogs/new', ['controller' => 'BlogController', 'action' => 'new', 'method' => 'GET']);
    $r->add('/blogs/create', ['controller' => 'BlogController', 'action' => 'create', 'method' => 'POST']);
    $r->add('/blogs/{id:\d+}/show', ['controller' => 'BlogController', 'action' => 'show', 'method' => 'GET']);
    $r->add('/blogs/{id:\d+}/edit', ['controller' => 'BlogController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/blogs/{id:\d+}/update', ['controller' => 'BlogController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/blogs/{id:\d+}/delete', ['controller' => 'BlogController', 'action' => 'delete', 'method' => 'GET']);
    $r->add('/blogs/{id:\d+}/destroy', ['controller' => 'BlogController', 'action' => 'destroy', 'method' => 'POST']);

    // Post management
    $r->add('/posts', ['controller' => 'PostController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/posts/new', ['controller' => 'PostController', 'action' => 'new', 'method' => 'GET']);
    $r->add('/posts/create', ['controller' => 'PostController', 'action' => 'create', 'method' => 'POST']);
    $r->add('/posts/{id:\d+}/show', ['controller' => 'PostController', 'action' => 'show', 'method' => 'GET']);
    $r->add('/posts/{id:\d+}/edit', ['controller' => 'PostController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/posts/{id:\d+}/update', ['controller' => 'PostController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/posts/{id:\d+}/delete', ['controller' => 'PostController', 'action' => 'delete', 'method' => 'GET']);
    $r->add('/posts/{id:\d+}/destroy', ['controller' => 'PostController', 'action' => 'destroy', 'method' => 'POST']);

    // Taxonomy management
    $r->add('/categories', ['controller' => 'CategoryController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/categories/new', ['controller' => 'CategoryController', 'action' => 'new', 'method' => 'GET']);
    $r->add('/categories/create', ['controller' => 'CategoryController', 'action' => 'create', 'method' => 'POST']);
    $r->add('/categories/{id:\d+}/edit', ['controller' => 'CategoryController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/categories/{id:\d+}/update', ['controller' => 'CategoryController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/categories/{id:\d+}/delete', ['controller' => 'CategoryController', 'action' => 'delete', 'method' => 'GET']);
    $r->add('/categories/{id:\d+}/destroy', ['controller' => 'CategoryController', 'action' => 'destroy', 'method' => 'POST']);

    $r->add('/tags', ['controller' => 'TagController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/tags/new', ['controller' => 'TagController', 'action' => 'new', 'method' => 'GET']);
    $r->add('/tags/create', ['controller' => 'TagController', 'action' => 'create', 'method' => 'POST']);
    $r->add('/tags/{id:\d+}/edit', ['controller' => 'TagController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/tags/{id:\d+}/update', ['controller' => 'TagController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/tags/{id:\d+}/delete', ['controller' => 'TagController', 'action' => 'delete', 'method' => 'GET']);
    $r->add('/tags/{id:\d+}/destroy', ['controller' => 'TagController', 'action' => 'destroy', 'method' => 'POST']);

    // Comment moderation
    $r->add('/comments', ['controller' => 'CommentController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/comments/{id:\d+}/approve', ['controller' => 'CommentController', 'action' => 'approve', 'method' => 'POST']);
    $r->add('/comments/{id:\d+}/unapprove', ['controller' => 'CommentController', 'action' => 'unapprove', 'method' => 'POST']);
    $r->add('/comments/{id:\d+}/spam', ['controller' => 'CommentController', 'action' => 'spam', 'method' => 'POST']);
    $r->add('/comments/{id:\d+}/destroy', ['controller' => 'CommentController', 'action' => 'destroy', 'method' => 'POST']);
    $r->add('/comments/bulk', ['controller' => 'CommentController', 'action' => 'bulk', 'method' => 'POST']);

    // Roles and permissions
    $r->add('/roles', ['controller' => 'RoleController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/roles/new', ['controller' => 'RoleController', 'action' => 'new', 'method' => 'GET']);
    $r->add('/roles/create', ['controller' => 'RoleController', 'action' => 'create', 'method' => 'POST']);
    $r->add('/roles/{id:\d+}/show', ['controller' => 'RoleController', 'action' => 'show', 'method' => 'GET']);
    $r->add('/roles/{id:\d+}/edit', ['controller' => 'RoleController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/roles/{id:\d+}/update', ['controller' => 'RoleController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/roles/{id:\d+}/delete', ['controller' => 'RoleController', 'action' => 'delete', 'method' => 'GET']);
    $r->add('/roles/{id:\d+}/destroy', ['controller' => 'RoleController', 'action' => 'destroy', 'method' => 'POST']);
    $r->add('/roles/{id:\d+}/permissions', ['controller' => 'RoleController', 'action' => 'updatePermissions', 'method' => 'POST']);

    // Site settings
    $r->add('/settings', ['controller' => 'SettingController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/settings', ['controller' => 'SettingController', 'action' => 'update', 'method' => 'POST']);

    // Audit trail
    $r->add('/audit-log', ['controller' => 'AuditLogController', 'action' => 'index', 'method' => 'GET']);

    // System diagnostics
    $r->add('/system', ['controller' => 'SystemController', 'action' => 'index', 'method' => 'GET']);

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
    $r->add('/archive', ['controller' => 'BlogController', 'action' => 'archiveBlog', 'method' => 'GET']);
    $r->add('/category/{categorySlug:[A-Za-z0-9_-]+}', ['controller' => 'BlogController', 'action' => 'showCategory', 'method' => 'GET']);
    $r->add('/tag/{tagSlug:[A-Za-z0-9_-]+}', ['controller' => 'BlogController', 'action' => 'showTag', 'method' => 'GET']);
    $r->add('/index-feed', ['controller' => 'BlogController', 'action' => 'indexFeed', 'method' => 'GET']);
    $r->add('/{postSlug}', ['controller' => 'BlogController', 'action' => 'showBlogPost', 'method' => 'GET']);
});

// Final fallback catch-all route - only enabled in debug to avoid unintentionally
// exposing new controllers/actions via automatic routing in production.
if (env('APP_DEBUG', false)) {
    $router->add('/{controller}/{action}');

    $router->add('/debug-cache', [
        'controller' => 'HomeController',
        'action' => 'debugCache',
        'method' => 'GET',
    ]);
}

return $router;
