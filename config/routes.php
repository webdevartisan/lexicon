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

// Sent by the browser itself via the CSP report-uri directive; no token, no session.
$router->add('/csp-report', [
    'controller' => 'CspReportController',
    'action' => 'store',
    'method' => 'POST',
]);

// Only signed-in readers post here: their interface follows a stored preference,
// so switching language has to write it. Guests switch with a plain link.
$router->add('/language', [
    'controller' => 'LanguageController',
    'action' => 'switch',
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

// Static pages: explicit slugs only, so the pages table can never expose
// anything that was not deliberately routed.
$router->add('/about', ['controller' => 'PageController', 'action' => 'show', 'slug' => 'about', 'method' => 'GET']);
$router->add('/privacy', ['controller' => 'PageController', 'action' => 'show', 'slug' => 'privacy', 'method' => 'GET']);
$router->add('/terms', ['controller' => 'PageController', 'action' => 'show', 'slug' => 'terms', 'method' => 'GET']);
$router->add('/cookies', ['controller' => 'PageController', 'action' => 'show', 'slug' => 'cookies', 'method' => 'GET']);
$router->add('/contact', ['controller' => 'PageController', 'action' => 'contact', 'method' => 'GET']);
$router->add('/contact', ['controller' => 'PageController', 'action' => 'sendContact', 'method' => 'POST']);
$router->add('/getting-started', ['controller' => 'PageController', 'action' => 'gettingStarted', 'method' => 'GET']);
$router->add('/getting-started/{slug:[a-z0-9-]+}', ['controller' => 'PageController', 'action' => 'guide', 'method' => 'GET']);
$router->add('/sitemap.xml', ['controller' => 'SitemapController', 'action' => 'index', 'method' => 'GET']);
$router->add('/robots.txt', ['controller' => 'SitemapController', 'action' => 'robots', 'method' => 'GET']);

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

// Reader actions on an existing comment. Both need an identity: removal is
// scoped to your own comment or a blog you moderate, and a vote has to belong
// to somebody to be toggled off again.
$router->group([
    'prefix' => '/comments',
    'middleware' => ['auth'],
], function (Router $r) {
    $r->add('/{id:\d+}/delete', ['controller' => 'CommentController', 'action' => 'destroy', 'method' => 'POST']);
    $r->add('/{id:\d+}/vote', ['controller' => 'CommentController', 'action' => 'vote', 'method' => 'POST']);
    $r->add('/{id:\d+}/report', ['controller' => 'CommentController', 'action' => 'report', 'method' => 'POST']);
    $r->add('/{id:\d+}/pin', ['controller' => 'CommentController', 'action' => 'pin', 'method' => 'POST']);
});

// Blog subscriptions (guests allowed; unsubscribe is a signed token link from email)
$router->add('/blog/{blogSlug:[A-Za-z0-9_-]+}/subscribe', [
    'controller' => 'SubscriptionController',
    'action' => 'subscribe',
    'method' => 'POST',
]);
$router->add('/subscriptions/unsubscribe/{token:[a-f0-9]{64}}', [
    'controller' => 'SubscriptionController',
    'action' => 'unsubscribe',
    'method' => 'GET',
]);

// Post engagement (auth required; CSRF token in the POST body)
$router->group([
    'prefix' => '/posts',
    'middleware' => ['auth'],
], function (Router $r) {
    $r->add('/{id:\d+}/vote', ['controller' => 'PostEngagementController', 'action' => 'vote', 'method' => 'POST']);
    $r->add('/{id:\d+}/bookmark', ['controller' => 'PostEngagementController', 'action' => 'toggleBookmark', 'method' => 'POST']);
    $r->add('/{id:\d+}/report', ['controller' => 'PostEngagementController', 'action' => 'report', 'method' => 'POST']);
});

// Public blog invitation landing (reached from the email link; no auth required)
$router->add('/invite/{token:[a-f0-9]+}', ['controller' => 'InviteController', 'action' => 'show', 'method' => 'GET']);
$router->add('/invite/{token:[a-f0-9]+}/accept', ['controller' => 'InviteController', 'action' => 'accept', 'method' => 'POST']);
$router->add('/invite/{token:[a-f0-9]+}/decline', ['controller' => 'InviteController', 'action' => 'decline', 'method' => 'POST']);

$router->group([
    'prefix' => '/',
    'namespace' => 'Auth',
], function (Router $r) {
    $r->add('/login', ['controller' => 'AuthController',   'action' => 'index', 'method' => 'GET']);
    $r->add('/login', ['controller' => 'AuthController',   'action' => 'submit', 'method' => 'POST']);
    $r->add('/login/identify', ['controller' => 'AuthController',   'action' => 'identify', 'method' => 'POST']);
    $r->add('/auth/nav', ['controller' => 'AuthController',   'action' => 'nav', 'method' => 'GET']);
    $r->add('/logout', ['controller' => 'AuthController',   'action' => 'logout', 'method' => 'POST']);

    $r->add('/register', ['controller' => 'RegisterController', 'action' => 'show', 'method' => 'GET']);
    $r->add('/register/submit', ['controller' => 'RegisterController', 'action' => 'submit', 'method' => 'POST']);

    // Password Reset Routes
    $r->add('/password/forgot', ['controller' => 'PasswordController',   'action' => 'showForgotForm', 'method' => 'GET']);
    $r->add('/password/forgot', ['controller' => 'PasswordController',   'action' => 'submit', 'method' => 'POST']);
    $r->add('/password/reset/{token}', ['controller' => 'PasswordController',   'action' => 'showResetForm', 'method' => 'GET']);
    $r->add('/password/reset', ['controller' => 'PasswordController',   'action' => 'resetPassword', 'method' => 'POST']);

});

// The reader's own things. Top level and on the front, because reading is not
// a sub-feature of the creator dashboard: every account reads, only some write.
// A creator reaches the same pages from the same menu a reader does.
$router->group([
    'prefix' => '/saved',
    'middleware' => ['auth'],
], function (Router $r) {
    $r->add('/', ['controller' => 'ReaderController', 'action' => 'saved', 'method' => 'GET']);
    $r->add('/liked', ['controller' => 'ReaderController', 'action' => 'liked', 'method' => 'GET']);
    // Straight deletes, not the post page's toggles: asked twice, a toggle puts
    // back what the reader just removed.
    $r->add('/{postId:\d+}/remove', ['controller' => 'ReaderController', 'action' => 'removeBookmark', 'method' => 'POST']);
    $r->add('/liked/{postId:\d+}/remove', ['controller' => 'ReaderController', 'action' => 'removeVote', 'method' => 'POST']);
});

$router->group([
    'prefix' => '/replies',
    'middleware' => ['auth'],
], function (Router $r) {
    $r->add('/', ['controller' => 'ReaderController', 'action' => 'replies', 'method' => 'GET']);
    $r->add('/mine', ['controller' => 'ReaderController', 'action' => 'myComments', 'method' => 'GET']);
    // The only writer of read_at on this surface, and never a GET.
    $r->add('/mark-read', ['controller' => 'ReaderController', 'action' => 'markRepliesRead', 'method' => 'POST']);
});

$router->group([
    'prefix' => '/subscriptions',
    'middleware' => ['auth'],
], function (Router $r) {
    $r->add('/', ['controller' => 'ReaderController', 'action' => 'subscriptions', 'method' => 'GET']);
    $r->add('/{blogId:\d+}/unsubscribe', ['controller' => 'ReaderController', 'action' => 'unsubscribe', 'method' => 'POST']);
    $r->add('/{blogId:\d+}/resubscribe', ['controller' => 'ReaderController', 'action' => 'resubscribe', 'method' => 'POST']);
});

// Personal account settings, on the front for every account whatever its role.
// Cut on ownership: anything belonging to the person lives here; anything
// belonging to a blog stays in the dashboard. No namespace key, so these
// controllers sit at App\Controllers and resolve by auto-discovery like
// ReaderController. Deletion keeps its own confirm/destroy pair, reached from
// the foot of Preferences rather than from the rail.
$router->group([
    'prefix' => '/account',
    'middleware' => ['auth'],
], function (Router $r) {
    $r->add('/', ['controller' => 'AccountProfileController', 'action' => 'redirectToProfile', 'method' => 'GET']);

    $r->add('/profile', ['controller' => 'AccountProfileController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/profile/update', ['controller' => 'AccountProfileController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/profile/avatar', ['controller' => 'AccountProfileController', 'action' => 'uploadAvatar', 'method' => 'POST']);
    $r->add('/profile/avatar/crop', ['controller' => 'AccountProfileController', 'action' => 'cropAvatar', 'method' => 'POST']);
    $r->add('/profile/avatar/remove', ['controller' => 'AccountProfileController', 'action' => 'removeAvatar', 'method' => 'POST']);

    $r->add('/preferences', ['controller' => 'AccountPreferencesController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/preferences/update', ['controller' => 'AccountPreferencesController', 'action' => 'update', 'method' => 'POST']);

    $r->add('/notifications', ['controller' => 'AccountNotificationsController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/notifications/update', ['controller' => 'AccountNotificationsController', 'action' => 'update', 'method' => 'POST']);

    $r->add('/security', ['controller' => 'AccountSecurityController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/security/password', ['controller' => 'AccountSecurityController', 'action' => 'updatePassword', 'method' => 'POST']);

    $r->add('/delete', ['controller' => 'AccountDeletionController', 'action' => 'confirm', 'method' => 'GET']);
    $r->add('/delete', ['controller' => 'AccountDeletionController', 'action' => 'destroy', 'method' => 'POST']);
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
    // Personal account settings moved to the front under /account. The old
    // dashboard settings routes are deleted, not redirected.
    $r->add('/blog', ['controller' => 'BlogController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/blog/new', ['controller' => 'BlogController', 'action' => 'new', 'method' => 'GET']);
    // Sectioned blog settings; the old /blogs/{id}/edit URL redirects here.
    $r->add('/blog/{id:\d+}/settings', ['controller' => 'BlogController', 'action' => 'settings', 'method' => 'GET']);

    // Appearance hub: theme browser plus branding and front-page texts.
    $r->add('/blog/{blogId:\d+}/appearance', ['controller' => 'AppearanceController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/blog/{blogId:\d+}/appearance/activate', ['controller' => 'AppearanceController', 'action' => 'activate', 'method' => 'POST']);
    $r->add('/blog/{blogId:\d+}/appearance/update', ['controller' => 'AppearanceController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/post', ['controller' => 'PostController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/post/bulk', ['controller' => 'PostController', 'action' => 'bulk', 'method' => 'POST']);
    $r->add('/post/{id:\d+}/review', ['controller' => 'PostReviewController', 'action' => 'review', 'method' => 'GET']);
    $r->add('/post/{id:\d+}/feature', ['controller' => 'PostController', 'action' => 'feature', 'method' => 'POST']);

    // Post translations (per-locale overlays; offered when the blog enables localized posts).
    $r->add('/post/{id:\d+}/translations/{locale:[a-z]{2}}', ['controller' => 'PostTranslationController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/post/{id:\d+}/translations/{locale:[a-z]{2}}', ['controller' => 'PostTranslationController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/post/{id:\d+}/translations/{locale:[a-z]{2}}/delete', ['controller' => 'PostTranslationController', 'action' => 'destroy', 'method' => 'POST']);
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
    $r->add('/blog/{blogId:\d+}/media/{id:\d+}/details', ['controller' => 'MediaController', 'action' => 'details', 'method' => 'GET']);
    $r->add('/blog/{blogId:\d+}/media/{id:\d+}/process', ['controller' => 'MediaController', 'action' => 'process', 'method' => 'POST']);
    $r->add('/blog/{blogId:\d+}/media/{id:\d+}/meta', ['controller' => 'MediaController', 'action' => 'saveMeta', 'method' => 'POST']);
    $r->add('/blog/{blogId:\d+}/media/optimize', ['controller' => 'MediaController', 'action' => 'optimize', 'method' => 'POST']);
    $r->add('/blog/{blogId:\d+}/media/rescan', ['controller' => 'MediaController', 'action' => 'rescan', 'method' => 'POST']);
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

    // Subscribers (owner-only): audience list with search and removal.
    $r->add('/blog/{blogId:\d+}/subscribers', ['controller' => 'SubscriberController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/blog/{blogId:\d+}/subscribers/{id:\d+}/delete', ['controller' => 'SubscriberController', 'action' => 'destroy', 'method' => 'POST']);
    $r->add('/comment/{id:\d+}/approve', ['controller' => 'CommentController', 'action' => 'approve', 'method' => 'POST']);
    $r->add('/comment/{id:\d+}/spam', ['controller' => 'CommentController', 'action' => 'spam', 'method' => 'POST']);
    $r->add('/comment/{id:\d+}/unapprove', ['controller' => 'CommentController', 'action' => 'unapprove', 'method' => 'POST']);
    $r->add('/comment/{id:\d+}/destroy', ['controller' => 'CommentController', 'action' => 'destroy', 'method' => 'POST']);

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
    $r->add('/notifications/clear-all', ['controller' => 'NotificationController', 'action' => 'clearAll',    'method' => 'POST']);
    $r->add('/notifications/{id:\d+}/delete', ['controller' => 'NotificationController', 'action' => 'destroy',     'method' => 'POST']);
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

    // Explicit routes for everything that used to be served only by the generic
    // wildcards below. Written out so the dashboard route table is enumerable
    // and the wildcards can go back behind APP_DEBUG without 404ing production.
    //
    // The plural spellings are deliberate: several views post to /blogs/... and
    // /posts/..., and Dispatcher::normalizeControllerName() singularizes before
    // appending "Controller", so both spellings reach the same class.
    $r->add('/blog/create', ['controller' => 'BlogController', 'action' => 'create', 'method' => 'POST']);
    $r->add('/blog/{id:\d+}/show', ['controller' => 'BlogController', 'action' => 'show', 'method' => 'GET']);
    $r->add('/blog/{id:\d+}/destroy', ['controller' => 'BlogController', 'action' => 'destroy', 'method' => 'POST']);
    $r->add('/blogs/{id:\d+}/update', ['controller' => 'BlogController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/blogs/{id:\d+}/delete', ['controller' => 'BlogController', 'action' => 'delete', 'method' => 'POST']);

    $r->add('/post/{id:\d+}/edit', ['controller' => 'PostController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/post/{id:\d+}/update', ['controller' => 'PostController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/post/{id:\d+}/delete', ['controller' => 'PostController', 'action' => 'delete', 'method' => 'POST']);
    $r->add('/post/{id:\d+}/destroy', ['controller' => 'PostController', 'action' => 'destroy', 'method' => 'POST']);
    $r->add('/post/{id:\d+}/draft', ['controller' => 'PostController', 'action' => 'draft', 'method' => 'POST']);
    $r->add('/post/{id:\d+}/archive', ['controller' => 'PostController', 'action' => 'archive', 'method' => 'POST']);
    $r->add('/post/{id:\d+}/publish', ['controller' => 'PostController', 'action' => 'publish', 'method' => 'POST']);
    $r->add('/posts/{id:\d+}/destroy', ['controller' => 'PostController', 'action' => 'destroy', 'method' => 'POST']);
    $r->add('/posts/{id:\d+}/publish', ['controller' => 'PostController', 'action' => 'publish', 'method' => 'POST']);

    $r->add('/comment/{id:\d+}/destroy', ['controller' => 'CommentController', 'action' => 'destroy', 'method' => 'POST']);

    // Generic CRUD routes for dashboard. Debug-only: with the explicit routes
    // above, nothing in the app depends on these to resolve.
    if (env('APP_DEBUG', false)) {
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
    }
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

    // Front page content (per-locale overrides of the public site text)
    $r->add('/front-page', ['controller' => 'FrontPageController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/front-page', ['controller' => 'FrontPageController', 'action' => 'update', 'method' => 'POST']);

    // Static pages (about, contact, legal, guides)
    $r->add('/pages', ['controller' => 'PageController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/pages/{id:\d+}/edit', ['controller' => 'PageController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/pages/{id:\d+}/update', ['controller' => 'PageController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/pages/{id:\d+}/translate', ['controller' => 'PageController', 'action' => 'translate', 'method' => 'POST']);

    // Platform surface curation: front page showcase and explore featured
    $r->add('/posts/{id:\d+}/feature-home', ['controller' => 'PostController', 'action' => 'featureHome', 'method' => 'POST']);
    $r->add('/blogs/{id:\d+}/feature-explore', ['controller' => 'BlogController', 'action' => 'featureExplore', 'method' => 'POST']);

    // Audit trail
    $r->add('/audit-log', ['controller' => 'AuditLogController', 'action' => 'index', 'method' => 'GET']);

    // System diagnostics
    $r->add('/system', ['controller' => 'SystemController', 'action' => 'index', 'method' => 'GET']);

    // Outbound mail queue: inspect and recover failed sends
    $r->add('/mail-queue', ['controller' => 'MailQueueController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/mail-queue/{id:\d+}/retry', ['controller' => 'MailQueueController', 'action' => 'retry', 'method' => 'POST']);
    $r->add('/mail-queue/retry-all', ['controller' => 'MailQueueController', 'action' => 'retryAll', 'method' => 'POST']);
    $r->add('/mail-queue/prune', ['controller' => 'MailQueueController', 'action' => 'prune', 'method' => 'POST']);

    // Recurring tasks. Listed before the id routes so the fixed segments are
    // not swallowed by them.
    $r->add('/scheduled-tasks', ['controller' => 'ScheduledTaskController', 'action' => 'index', 'method' => 'GET']);
    $r->add('/scheduled-tasks/statuses', ['controller' => 'ScheduledTaskController', 'action' => 'statuses', 'method' => 'GET']);
    $r->add('/scheduled-tasks/hint', ['controller' => 'ScheduledTaskController', 'action' => 'hint', 'method' => 'GET']);
    $r->add('/scheduled-tasks/create', ['controller' => 'ScheduledTaskController', 'action' => 'create', 'method' => 'GET']);
    $r->add('/scheduled-tasks/store', ['controller' => 'ScheduledTaskController', 'action' => 'store', 'method' => 'POST']);
    $r->add('/scheduled-tasks/{id:\d+}/edit', ['controller' => 'ScheduledTaskController', 'action' => 'edit', 'method' => 'GET']);
    $r->add('/scheduled-tasks/{id:\d+}/history', ['controller' => 'ScheduledTaskController', 'action' => 'history', 'method' => 'GET']);
    $r->add('/scheduled-tasks/{id:\d+}/update', ['controller' => 'ScheduledTaskController', 'action' => 'update', 'method' => 'POST']);
    $r->add('/scheduled-tasks/{id:\d+}/run', ['controller' => 'ScheduledTaskController', 'action' => 'run', 'method' => 'POST']);
    $r->add('/scheduled-tasks/{id:\d+}/toggle', ['controller' => 'ScheduledTaskController', 'action' => 'toggle', 'method' => 'POST']);
    $r->add('/scheduled-tasks/{id:\d+}/delete', ['controller' => 'ScheduledTaskController', 'action' => 'destroy', 'method' => 'POST']);

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
