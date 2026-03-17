<?php

declare(strict_types=1);

/**
 * Service Container Bindings
 *
 * Register all application services, models, repositories, and utilities
 * with the dependency injection container.
 *
 * Organization:
 * - Container Self-Registration
 * - Core Framework Services
 * - Database & Persistence
 * - Authentication & Security
 * - View & Template Services
 * - Application Services
 * - Cache Services
 * - Validation
 * - Models (Factories)
 * - Rate Limiting
 * - Optional Services (commented)
 *
 * Guidelines:
 * - Use set() for Models (factory pattern - fresh instance per resolution)
 * - Use setShared() for Services (singleton pattern - shared instance)
 * - All factories receive container as parameter: function ($c) { ... }
 * - Exception: Factory-of-factories may use 'use ($c)' for inner closures
 *
 * @var Framework\Core\Container $container
 */

// ============================================================================
// CONTAINER INITIALIZATION
// ============================================================================

// Initialize the container (must be first)
$container = new Framework\Core\Container();

// Enable debug mode in development environments
if (env('APP_DEBUG', false)) {
    $container->enableDebug();
}

// In non-debug environments, APP_KEY must be set to a strong, non-default value.
if (!env('APP_DEBUG', false)) {
    $appKey = $_ENV['APP_KEY'] ?? null;

    if ($appKey === null || $appKey === '' || $appKey === 'change-me') {
        throw new \RuntimeException(
            'APP_KEY must be set to a strong, random value in production. '
            . 'Update your environment configuration before running the application.'
        );
    }
}


// ============================================================================
// CONTAINER SELF-REGISTRATION
// ============================================================================

/**
 * Register the container itself as a singleton.
 *
 * We register the container so it can be injected into services that need
 * to resolve dependencies dynamically. This is acceptable in limited cases
 * (e.g., factories, dynamic resolvers) but should not be used as a general
 * service locator pattern.
 *
 * WARNING: Avoid injecting Container into regular services - prefer
 * constructor injection of specific dependencies.
 */
$container->setShared(Framework\Core\Container::class, fn($c) => $c);


// ============================================================================
// CORE FRAMEWORK SERVICES (Shared Singletons)
// ============================================================================

/**
 * Database service provides PDO connection and query builder.
 *
 * We register as singleton to maintain a single connection pool throughout
 * the request lifecycle, improving performance and resource usage.
 */
$container->setShared(Framework\Database::class, function ($c) {
    $dbConfig = require ROOT_PATH . '/config/database.php';
    return new Framework\Database($dbConfig);
});

/**
 * Session service manages user session data.
 *
 * We register as singleton to maintain consistent session state across
 * all services that need session access during the request.
 */
$container->setShared(Framework\Session::class, function ($c) {
    return new Framework\Session();
});


// ============================================================================
// AUTHENTICATION & SECURITY (Shared Singletons)
// ============================================================================

/**
 * Authentication service handles user login state and permissions.
 *
 * We register as singleton to maintain consistent authentication state
 * across the entire request. Dependencies are injected as fresh Models
 * to avoid state leakage.
 */
$container->setShared(App\Auth::class, function ($c) {
    return new App\Auth(
        $c->get(Framework\Session::class),
        $c->get(App\Models\UserModel::class),
        $c->get(App\Models\UserProfileModel::class)
    );
});
$container->setShared(Framework\Interfaces\AuthInterface::class, function ($c) {
    return $c->get(App\Auth::class);
});

/**
 * CSRF protection service generates and validates tokens.
 *
 * We register as singleton to maintain token state and provide consistent
 * CSRF protection across all forms in the request.
 */
$container->setShared(Framework\Security\Csrf::class, function ($c) {
    return new Framework\Security\Csrf(
        $c->get(Framework\Session::class)
    );
});


// ============================================================================
// VIEW & TEMPLATE SERVICES (Shared Singletons)
// ============================================================================

/**
 * Route context tracks current route for view resolution.
 *
 * We register as singleton to maintain consistent route context when
 * rendering multiple views during a single request.
 */
$container->setShared(Framework\View\RouteContext::class, function ($c) {
    return new Framework\View\RouteContext();
});

/**
 * View name resolver maps controller namespaces to view directories.
 *
 * We configure namespace-to-directory mappings for clean separation between
 * public, admin, dashboard, and auth views. Singleton ensures consistent
 * resolution logic across all view rendering.
 */
$container->setShared(Framework\View\ViewNameResolverInterface::class, function ($c) {
    return new Framework\View\DefaultViewNameResolver(
        namespaceAreaMap: [
            'Admin' => 'admin',
            'Dashboard' => 'dashboard',
            'Auth' => 'auth'
        ],
        defaultArea: 'public'
    );
});

/**
 * Theme service manages theme loading and asset resolution.
 *
 * We register as singleton to cache theme metadata and avoid repeated
 * filesystem access when resolving theme assets.
 */
$container->setShared(App\Services\ThemeService::class, function ($c) {
    return new App\Services\ThemeService(ROOT_PATH . '/themes');
});

/**
 * Template renderer orchestrates view rendering with theme support.
 *
 * We inject theme service, view resolver, and route context to provide
 * complete template rendering capabilities. Singleton ensures compiled
 * views can be cached between render calls.
 */
$container->setShared(Framework\Interfaces\TemplateViewerInterface::class, function ($c) {
    return new Framework\View\TemplateRenderer(
        $c->get(App\Services\ThemeService::class),
        $c->get(Framework\View\ViewNameResolverInterface::class),
        $c->get(Framework\View\RouteContext::class)
    );
});


// ============================================================================
// APPLICATION SERVICES (Shared Singletons)
// ============================================================================

/**
 * Navigation service builds menu structures with auth-aware visibility.
 *
 * We load navigation config once per request and inject Auth service for
 * permission checking. Config is loaded fresh each time to reflect any
 * runtime changes.
 */
$container->setShared(App\Services\NavigationService::class, function ($c) {
    $config = require ROOT_PATH . '/config/navigation.php';
    return new App\Services\NavigationService($config, $c->get(App\Auth::class));
});

/**
 * Translation service provides i18n support with locale detection.
 *
 * We register as factory (NOT shared) because each request may have a different
 * locale from session. This ensures proper locale isolation between requests
 * in long-running processes.
 */
$container->set(App\Services\TranslationService::class, function ($c) {
    /** @var Framework\Session $session */
    $session = $c->get(Framework\Session::class);
    $locale = $session->get('locale') ?? 'en';

    return new App\Services\TranslationService($locale);
});

/**
 * Consent service manages GDPR/privacy consent preferences.
 *
 * We use APP_KEY for signing consent cookies to prevent tampering.
 * Singleton ensures consistent consent state throughout request.
 */
$container->setShared(App\Services\ConsentService::class, function ($c) {
    $config = require ROOT_PATH . '/config/consent.php';

    // Use APP_KEY from environment for cookie signing.
    // A non-debug environment will refuse to boot if APP_KEY is missing or weak.
    $secret = $_ENV['APP_KEY'] ?? 'change-me';

    $store = new App\Privacy\ConsentCookieStore(
        cookieName: (string) ($config['cookie_name'] ?? 'app_consent'),
        ttlDays: (int) ($config['cookie_ttl_days'] ?? 180),
        secret: $secret
    );

    return new App\Services\ConsentService($store, $config);
});

/**
 * Mail service handles email sending with template support.
 *
 * We register as singleton to maintain SMTP connection pooling and
 * template caching across multiple email sends in the same request.
 */
$container->setShared(App\Services\MailService::class, function ($c) {
    $config = require ROOT_PATH . '/config/mail.php';
    return new App\Services\MailService($config);
});

/**
 * Email template registry discovers and caches available email templates.
 *
 * We register as singleton to avoid repeated filesystem scans when
 * looking up template keys.
 */
$container->setShared(App\Services\EmailTemplateRegistry::class, function ($c) {
    return new App\Services\EmailTemplateRegistry();
});

/**
 * Breadcrumb service builds navigation breadcrumb trails.
 *
 * We register as singleton to accumulate breadcrumbs across multiple
 * controller actions in the same request.
 */
$container->setShared(App\Services\BreadcrumbService::class, function ($c) {
    return new App\Services\BreadcrumbService();
});

/**
 * Audit service logs user actions for security and compliance.
 *
 * We register as singleton to batch audit log writes and maintain
 * consistent logging context throughout the request.
 */
$container->setShared(App\Services\AuditService::class, function ($c) {
    return new App\Services\AuditService(
        $c->get(Framework\Database::class)
    );
});


// ============================================================================
// CACHE SERVICES (Shared Singletons)
// ============================================================================

/**
 * Cache service provides file-based caching with automatic garbage collection.
 *
 * We register as singleton to maintain cache state and avoid repeated
 * configuration loading. GC runs probabilistically based on config.
 */
$container->setShared(Framework\Cache\CacheService::class, function ($c) {
    $config = require ROOT_PATH . '/config/cache.php';

    return new Framework\Cache\CacheService(
        cachePath: $config['path'],
        enabled: $config['enabled'],
        gcProbability: $config['gc_probability'] ?? 1,
        gcDivisor: $config['gc_divisor'] ?? 100,
        maxFiles: $config['max_files'] ?? 5000
    );
});

/**
 * Cache key generator creates normalized cache keys from requests.
 *
 * We use query parameter whitelisting to ensure consistent cache keys
 * regardless of parameter order or tracking parameters.
 */
$container->setShared(Framework\Cache\CacheKey::class, function ($c) {
    $config = require ROOT_PATH . '/config/cache.php';

    return new Framework\Cache\CacheKey(
        queryWhitelist: $config['query_whitelist']
    );
});

/**
 * Cache middleware handles HTTP response caching.
 *
 * We register as factory (NOT shared) to ensure fresh cache decision-making
 * per request. Each request needs its own instance with clean state.
 */
$container->set(Framework\Cache\CacheMiddleware::class, function ($c) {
    $config = require ROOT_PATH . '/config/cache.php';

    return new Framework\Cache\CacheMiddleware(
        cache: $c->get(Framework\Cache\CacheService::class),
        keyGenerator: $c->get(Framework\Cache\CacheKey::class),
        auth: $c->get(App\Auth::class),
        ttlRules: $config['ttl_rules'],
        debug: $config['debug']
    );
});

/**
 * Fragment cache provides fine-grained view fragment caching.
 *
 * We register as singleton to cache compiled fragments across multiple
 * view renders in the same request.
 */
$container->setShared(Framework\Cache\FragmentCache::class, function ($c) {
    return new Framework\Cache\FragmentCache(
        $c->get(Framework\Cache\CacheService::class)
    );
});

/**
 * Cache management service orchestrates admin cache operations.
 *
 * We register explicitly (not via auto-discovery) because the second
 * dependency is bound under TemplateViewerInterface, not the concrete
 * TemplateRenderer class. Auto-discovery cannot resolve interface bindings.
 */
/*$container->setShared(App\Services\CacheManagementService::class, function ($c) {
    return new App\Services\CacheManagementService(
        cache:    $c->get(Framework\Cache\CacheService::class),
        renderer: $c->get(Framework\Interfaces\TemplateViewerInterface::class),
    );
});*/


// ============================================================================
// VALIDATION SERVICES
// ============================================================================

/**
 * Database validator provides validation rules with database checks.
 *
 * We register as factory (NOT shared) to ensure each validation request
 * gets a fresh validator instance with clean error state.
 */
$container->set(Framework\Validation\DatabaseValidator::class, function ($c) {
    // Initialized with empty data array; controllers will provide data when validating
    return new Framework\Validation\DatabaseValidator(
        [],
        $c->get(Framework\Database::class)
    );
});

/**
 * Validator factory returns a closure that creates validators with user data.
 *
 * Pattern: Factory-of-factories for runtime data binding
 *
 * We return a factory function that user code will call with validation data.
 * The inner closure captures $c via 'use' because it's invoked by user code,
 * not by the container, so it won't receive $c as a parameter.
 *
 * This is the ONLY acceptable use of 'use ($c)' in this file because the
 * inner closure is called by application code, not the container.
 *
 * @example Usage in Controllers:
 * $validatorFactory = $container->get('validator.factory');
 * $validator = $validatorFactory(['email' => 'test@example.com']);
 * if ($validator->validate($rules)) { ... }
 */
$container->set('validator.factory', function ($c) {
    // Outer closure receives $c from container (standard pattern)

    // Inner closure must capture $c via 'use' because user code calls it
    return function (array $data) use ($c): Framework\Validation\DatabaseValidator {
        return new Framework\Validation\DatabaseValidator(
            $data,
            $c->get(Framework\Database::class)
        );
    };
});


// ============================================================================
// MODELS (Factories - Fresh Instance Per Resolution)
// ============================================================================

/**
 * IMPORTANT: Models use set() NOT setShared()
 *
 * We use factory pattern for Models to prevent state leakage between requests.
 * Models often store query results and internal state that should not be
 * shared across multiple resolutions.
 *
 * Using setShared() for Models can cause:
 * - Stale data from previous queries
 * - Memory bloat from accumulated results
 * - State conflicts in long-running processes
 * - Unexpected behavior when the same Model is used multiple times
 */

$container->set(App\Models\UserModel::class, function ($c) {
    return new App\Models\UserModel(
        $c->get(Framework\Database::class)
    );
});

$container->set(App\Models\UserProfileModel::class, function ($c) {
    return new App\Models\UserProfileModel(
        $c->get(Framework\Database::class)
    );
});

$container->set(App\Models\UserPreferencesModel::class, function ($c) {
    return new App\Models\UserPreferencesModel(
        $c->get(Framework\Database::class)
    );
});

$container->set(App\Models\UserSocialLinkModel::class, function ($c) {
    return new App\Models\UserSocialLinkModel(
        $c->get(Framework\Database::class)
    );
});

$container->set(App\Models\BlogModel::class, function ($c) {
    return new App\Models\BlogModel(
        $c->get(Framework\Database::class)
    );
});

$container->set(App\Models\PostModel::class, function ($c) {
    return new App\Models\PostModel(
        $c->get(Framework\Database::class)
    );
});

$container->set(App\Models\BlogSettingsModel::class, function ($c) {
    return new App\Models\BlogSettingsModel(
        $c->get(Framework\Database::class)
    );
});


// ============================================================================
// RATE LIMITING SERVICES
// ============================================================================

/**
 * Rate limiter provides generic rate limiting with cache backend.
 *
 * We register as factory (NOT shared) to ensure each rate-limited action
 * gets its own limiter instance with isolated state.
 */
$container->set(Framework\Helpers\RateLimiter::class, function ($c) {
    /** @var Framework\Cache\CacheService $cache */
    $cache = $c->get(Framework\Cache\CacheService::class);

    // Default window is 15 minutes (900 seconds)
    return new Framework\Helpers\RateLimiter($cache, 900);
});

/**
 * Login rate limiter prevents brute-force login attempts.
 *
 * We register as factory to ensure fresh rate limit state per login attempt,
 * preventing cross-contamination between different user login flows.
 */
$container->set(App\Services\LoginRateLimiter::class, function ($c) {
    return new App\Services\LoginRateLimiter(
        $c->get(Framework\Helpers\RateLimiter::class),
        $c->get(Framework\Cache\CacheService::class)
    );
});

/**
 * Password reset rate limiter prevents reset request abuse.
 *
 * We register as factory to ensure fresh rate limit state per reset attempt,
 * maintaining proper isolation between different users' reset flows.
 */
$container->set(App\Services\PasswordResetRateLimiter::class, function ($c) {
    return new App\Services\PasswordResetRateLimiter(
        $c->get(Framework\Helpers\RateLimiter::class),
        $c->get(Framework\Cache\CacheService::class)
    );
});


// ============================================================================
// RESOURCES & DTOs
// ============================================================================

/**
 * UserResource should NOT be resolved from container.
 *
 * Resources are data transformers that should be instantiated directly
 * with the data they transform. This binding exists only to provide a
 * clear error message if someone tries to inject UserResource.
 *
 * Correct usage:
 *   $resource = new UserResource($userData);
 *   return $resource->toArray();
 *
 * Incorrect usage:
 *   $resource = $container->get(UserResource::class); // Throws exception
 */
$container->set(App\Resources\UserResource::class, function ($c) {
    throw new \LogicException(
        'UserResource should be instantiated directly with data, not resolved from container. '
        . 'Usage: new UserResource($userData)'
    );
});


// ============================================================================
// OPTIONAL SERVICES (Commented - Auto-Discovery via Constructor Injection)
// ============================================================================

/**
 * These services support auto-discovery and will be instantiated on-demand
 * when needed through constructor injection. They are commented out for
 * lazy loading but can be uncommented for explicit control.
 *
 * Benefits of keeping commented (lazy loading):
 * - Only instantiate when actually used
 * - Reduced memory footprint for requests not needing these components
 * - Faster bootstrap time
 *
 * Uncomment when:
 * - Service is used multiple times per request (benefit from sharing)
 * - Need eager initialization for performance profiling
 * - Debugging instantiation order or dependency issues
 * - Want explicit control over dependencies
 */

/*
// ============================================================================
// ADDITIONAL SERVICES
// ============================================================================

$container->setShared(App\Services\UploadService::class, function ($c) {
    return new App\Services\UploadService();
});

$container->setShared(App\Services\PostAutosaveService::class, function ($c) {
    return new App\Services\PostAutosaveService(
        $c->get(App\Models\PostModel::class),
        $c->get(App\Models\UserPreferencesModel::class)
    );
});

$container->setShared(App\Services\ProfileService::class, function ($c) {
    return new App\Services\ProfileService(
        $c->get(App\Models\UserProfileModel::class),
        $c->get(App\Models\UserSocialLinkModel::class),
        $c->get(App\Models\PostModel::class),
        $c->get(App\Models\BlogModel::class)
    );
});

$container->setShared(App\Services\UserDeletionService::class, function ($c) {
    return new App\Services\UserDeletionService(
        $c->get(App\Models\UserModel::class),
        $c->get(App\Models\UserProfileModel::class),
        $c->get(App\Models\UserSocialLinkModel::class),
        $c->get(App\Models\UserPreferencesModel::class),
        $c->get(App\Services\UploadService::class)
    );
});

$container->setShared(App\Services\BlogDeletionService::class, function ($c) {
    return new App\Services\BlogDeletionService(
        $c->get(App\Models\BlogModel::class),
        $c->get(App\Models\PostModel::class),
        $c->get(App\Models\BlogSettingsModel::class),
        $c->get(App\Models\UserPreferencesModel::class),
        $c->get(App\Services\UploadService::class)
    );
});


// ============================================================================
// CONSOLE COMMANDS (Factories - Fresh Instance Per Execution)
// ============================================================================

$container->set(App\Console\Kernel::class, function ($c) {
    return new App\Console\Kernel($c);
});


// ============================================================================
// CONTROLLERS (Factories - Fresh Instance Per Request)
// ============================================================================

// Controllers should generally use auto-discovery, but can be registered
// explicitly if they need special configuration or dependency resolution

$container->set(App\Controllers\Dashboard\AccountDeletionController::class, function ($c) {
    return new App\Controllers\Dashboard\AccountDeletionController(
        $c->get(App\Models\UserModel::class),
        $c->get(App\Services\UploadService::class)
    );
});
*/


// ============================================================================
// RETURN CONFIGURED CONTAINER
// ============================================================================

return $container;