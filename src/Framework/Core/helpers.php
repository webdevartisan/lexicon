<?php

declare(strict_types=1);

/**
 * Global helper functions for the framework.
 *
 * Provides convenient access to services, security utilities, localization,
 * debugging tools, and common operations used throughout the application.
 *
 * Categories:
 * - Debugging: dd(), dump(), d()
 * - Security: e(), csrf(), csrf_token(), csrf_field()
 * - Services: auth(), cache(), mailer(), breadcrumbs(), audit()
 * - Localization: locale(), lurl(), buildLocalizedUrl()
 * - Forms: flash(), errors(), old()
 * - Utilities: truncate(), snakeToCamel(), changedFields(), formatAcceptedTypes()
 */

/**
 * Extract namespace declaration from PHP source file.
 *
 * @param  string  $file  File path
 * @return string|null Namespace or null if not found
 */
function getNamespaceFromFile(string $file): ?string
{
    $src = file_get_contents($file);
    if ($src === false) {
        return null;
    }

    // Extract "namespace Foo\Bar;" declaration
    if (preg_match('/namespace\s+([^;]+);/', $src, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

/**
 * Output formatted variable dump with file/line context.
 *
 * Shows caller context (class/method), file location, and line number
 * for easier debugging. Used by dd(), dump(), and d() helpers.
 *
 * @param  array<mixed>  $vars  Variables to dump
 */
function dumpVars(array $vars): void
{
    echo '<pre>';

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

    // frame 0 = dumpVars
    // frame 1 = dd (file/line where dd() was called)
    // frame 2 = caller (class/method context)
    $ddFrame = $trace[1] ?? null;
    $callerFrame = $trace[2] ?? null;

    $file = isset($ddFrame['file']) ? basename($ddFrame['file']) : '';
    $line = $ddFrame['line'] ?? '';

    $context = '';
    if (isset($callerFrame['class'])) {
        $context = $callerFrame['class'];
        if (isset($callerFrame['type'])) {
            $context .= $callerFrame['type'];
        }
    }
    if (isset($callerFrame['function'])) {
        $context .= $callerFrame['function'].'()';
    }

    echo "{$context} - {$file}:{$line} \n";

    foreach ($vars as $var) {
        print_r($var);
        echo "\n";
    }

    echo '</pre>';
}

/**
 * Truncate string to specified length with ellipsis.
 *
 * @param  string  $string  String to truncate
 * @param  int  $limit  Maximum length (default: 50)
 * @return string Truncated string with ellipsis if needed
 */
function truncate(string $string, int $limit = 50): string
{
    return strlen($string) > $limit
        ? mb_substr($string, 0, $limit, 'UTF-8').'...'
        : $string;
}

/**
 * Render an author name, linked to their public profile when they have one.
 *
 * Returns the escaped name alone when no slug is given, so private profiles
 * and guest commenters degrade to plain text instead of a dead link. The
 * return value is already escaped — echo it raw, never through e().
 *
 * @param  string|null  $name  Display name to render
 * @param  string|null  $slug  Public profile slug, or null when unavailable
 * @param  string  $class  Optional CSS class for the anchor
 * @return string Escaped HTML: an anchor, or the bare name
 */
function profile_link(?string $name, ?string $slug, string $class = ''): string
{
    $label = e((string) $name);

    if ($label === '' || $slug === null || $slug === '') {
        return $label;
    }

    $href = e(lurl('/profile/'.rawurlencode($slug)));
    $attr = $class !== '' ? ' class="'.e($class).'"' : '';

    return '<a href="'.$href.'"'.$attr.' rel="author">'.$label.'</a>';
}

/**
 * Turn a label into a URL-friendly slug.
 *
 * Lowercases, strips accents where possible, and collapses anything that
 * isn't a letter/number into single hyphens.
 */
function slugify(string $value): string
{
    $value = trim($value);

    // Drop accents when the intl/iconv path is available, otherwise carry on.
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

/**
 * Estimated reading time in minutes for a chunk of HTML or text.
 *
 * @param  string|null  $content  Raw HTML or text
 * @param  int  $wpm  Words per minute (default 220)
 */
function reading_time(?string $content, int $wpm = 220): int
{
    $words = str_word_count(strip_tags((string) $content));

    return max(1, (int) ceil($words / $wpm));
}

if (!function_exists('dd')) {
    /**
     * Dump variables and die.
     *
     * Output formatted variable dump with context and terminate execution.
     * Primary debugging tool - use liberally during development.
     *
     * @param  mixed  ...$vars  Variables to dump
     */
    function dd(...$vars): void
    {
        dumpVars($vars);
        exit;
    }
}

if (!function_exists('dump')) {
    /**
     * Dump variables without dying.
     *
     * Output formatted variable dump with context and continue execution.
     * Use when you need to inspect variables mid-request.
     *
     * @param  mixed  ...$vars  Variables to dump
     */
    function dump(...$vars): void
    {
        dumpVars($vars);
    }
}

if (!function_exists('d')) {
    /**
     * Dump variables (short alias).
     *
     * Shorter alternative to dump() for quick debugging.
     *
     * @param  mixed  ...$vars  Variables to dump
     */
    function d(...$vars): void
    {
        dumpVars($vars);
    }
}

/**
 * Get the shared Auth service.
 */
function auth(): \App\Auth
{
    /** @var \App\Auth $auth */
    $auth = \Framework\Core\App::container()->get(\App\Auth::class);

    return $auth;
}

/**
 * Get the shared CSRF service.
 */
function csrf(): \Framework\Security\Csrf
{
    /** @var \Framework\Security\Csrf $csrf */
    $csrf = \Framework\Core\App::container()->get(\Framework\Security\Csrf::class);

    return $csrf;
}

/**
 * Get current CSRF token for forms.
 *
 * @return string CSRF token
 */
function csrf_token(): string
{
    return csrf()->getToken();
}

/**
 * Generate hidden CSRF input field.
 *
 * @return string HTML input field with CSRF token
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';
}

/**
 * Format DateTime with English ordinal suffix.
 *
 * Examples: "January 3rd, 2025", "December 21st, 2025"
 *
 * @param  DateTime  $date  Date to format
 * @return string Formatted date with ordinal suffix
 */
function formatWithOrdinal(DateTime $date): string
{
    $day = (int) $date->format('j');
    $suffix = 'th';

    // Handle special cases (11th, 12th, 13th)
    if (!in_array($day % 100, [11, 12, 13], true)) {
        switch ($day % 10) {
            case 1:
                $suffix = 'st';
                break;
            case 2:
                $suffix = 'nd';
                break;
            case 3:
                $suffix = 'rd';
                break;
        }
    }

    $month = $date->format('F');
    $year = $date->format('Y');

    return "{$month} {$day}{$suffix}, {$year}";
}

/**
 * Build locale-prefixed URL for GET navigation.
 *
 * Converts '/blogs' to '/{locale}/blogs' using current or specified locale.
 * Respects supported/default locales enforced by LocalePrefixIntake.
 *
 * @param  string  $path  Path to prefix (e.g., '/blogs', 'blogs')
 * @param  string|null  $locale  Locale code (defaults to current)
 * @return string Locale-prefixed URL
 */
function lurl(string $path, ?string $locale = null): string
{
    $path = '/'.ltrim($path, '/');

    // URLs point at content, so they follow the content locale rather than the
    // viewer's interface preference. The explicit argument stays for callers
    // like the sitemap, which emits one URL per locale a page exists in.
    $current = $locale ?? App\Services\LocaleState::get()->contentLocale;

    return '/'.$current.$path;
}

/**
 * Build localized URL based on HTTP method.
 *
 * - GET/HEAD: prefix with current locale unless already prefixed or absolute
 * - POST/PUT/PATCH/DELETE: unprefixed (API-style)
 *
 * @param  string  $target  Target URL
 * @param  bool  $skipMethodCheck  Skip HTTP method check (force localization)
 * @return string Localized URL
 */
function buildLocalizedUrl(string $target, bool $skipMethodCheck = false): string
{
    // Absolute or external URLs not modified
    if (preg_match('#^https?://#i', $target)) {
        return $target;
    }

    $target = '/'.ltrim($target, '/');

    if (!$skipMethodCheck) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $isUnsafe = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        // Unsafe methods use unprefixed URLs for API endpoints
        if ($isUnsafe) {
            return $target;
        }
    }

    // Already carries a supported locale. Checking against the real list rather
    // than a generic two-letter pattern keeps future routes such as /go/ or /my/
    // from silently losing localization.
    $registry = App\Services\LocaleRegistry::instance();
    $firstSegment = strtolower(explode('/', ltrim($target, '/'))[0] ?? '');

    if ($registry->isSupported($firstSegment)) {
        return $target;
    }

    $locale = $registry->normalize($_SESSION['locale'] ?? $_COOKIE['locale'] ?? null)
        ?? $registry->default();

    // Avoid double slashes when target is '/'
    return '/'.$locale.($target === '/' ? '' : $target);
}

/**
 * Validate a user-supplied return path for post-auth redirects.
 *
 * Accepts only site-local paths so login/register return_to values can never
 * turn into open redirects: single leading slash, no scheme, no host.
 *
 * @param  string|null  $url  Raw return_to value from query string or form
 * @return string|null The path when safe to redirect to, null otherwise
 */
function safe_return_to(?string $url): ?string
{
    if ($url === null) {
        return null;
    }

    $url = trim($url);

    if ($url === '' || strlen($url) > 2048 || $url[0] !== '/') {
        return null;
    }

    // '//host' and '/\host' are protocol-relative redirects in browsers
    if (isset($url[1]) && ($url[1] === '/' || $url[1] === '\\')) {
        return null;
    }

    // No control characters, no embedded scheme anywhere in the value
    if (preg_match('#[\x00-\x1f]#', $url) || stripos($url, '://') !== false) {
        return null;
    }

    return $url;
}

/**
 * Get current locale code.
 *
 * @return string Locale code (e.g., 'en', 'fr', 'de')
 */
function locale(): string
{
    return App\Services\LocaleState::get()->contentLocale;
}

/**
 * Escape string for safe HTML output.
 *
 * Convert special characters to HTML entities to prevent XSS attacks.
 * Use for all user-controlled text displayed in HTML.
 *
 * @param  string|null  $value  String to escape
 * @return string Escaped string
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Get flash messages and clear from session.
 *
 * Flash messages are stored by controllers after validation failures or
 * success operations. Loaded once per request and cached in memory.
 *
 * @return array<string, string[]> Flash messages by type (e.g., ['success' => ['Message']])
 */
function flash(): array
{
    static $messages = null;
    static $loaded = false;

    // Load and clear from session only once per request
    if (!$loaded) {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        $loaded = true;
    }

    return $messages;
}

/**
 * Get validation errors from session.
 *
 * Errors stored by validateOrFail() for form repopulation.
 * Loaded once per request and cached in memory.
 *
 * @return array<string, string[]> Errors by field (e.g., ['email' => ['Invalid email']])
 */
function errors(): array
{
    static $errors = null;
    static $loaded = false;

    // Load and clear from session only once per request
    if (!$loaded) {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        $loaded = true;
    }

    return $errors;
}

/**
 * Get old input for form repopulation.
 *
 * Retrieve previous form input after validation failures so users
 * don't re-enter data. Loaded once per request and cached in memory.
 *
 * @param  string|null  $key  Specific field name, or null for all input
 * @param  mixed  $default  Default value if key doesn't exist
 * @return mixed Old input value or default
 */
function old(?string $key = null, mixed $default = null): mixed
{
    static $oldInput = null;
    static $loaded = false;

    // Load and clear from session only once per request
    if (!$loaded) {
        $oldInput = $_SESSION['_old_input'] ?? [];
        unset($_SESSION['_old_input']);
        $loaded = true;
    }

    if ($key === null) {
        return $oldInput;
    }

    return $oldInput[$key] ?? $default;
}

/**
 * Convert snake_case to CamelCase.
 *
 * Used for converting validation rule names to method names and
 * transforming naming conventions.
 *
 * Examples:
 * - 'current_password' = 'CurrentPassword'
 * - 'alpha_num' = 'AlphaNum'
 * - 'email' = 'Email'
 *
 * @param  string  $value  Snake_case string
 * @return string CamelCase string
 */
function snakeToCamel(string $value): string
{
    return str_replace('_', '', ucwords($value, '_'));
}

/**
 * Get the shared MailService instance.
 */
function mailer(): \App\Services\MailService
{
    /** @var \App\Services\MailService $mail */
    $mail = \Framework\Core\App::container()->get(\App\Services\MailService::class);

    return $mail;
}

/**
 * Get the shared MailQueueService instance.
 *
 * Prefer this over mailer() for anything the application sends. Queued mail
 * gets retries and shows up in the control panel, where a send straight down
 * mailer() is gone for good the moment the transport refuses it.
 */
function mail_queue(): \App\Services\MailQueueService
{
    /** @var \App\Services\MailQueueService $queue */
    $queue = \Framework\Core\App::container()->get(\App\Services\MailQueueService::class);

    return $queue;
}

/**
 * Get the shared BreadcrumbService instance.
 */
function breadcrumbs(): \App\Services\BreadcrumbService
{
    /** @var \App\Services\BreadcrumbService $breadcrumbs */
    $breadcrumbs = \Framework\Core\App::container()->get(\App\Services\BreadcrumbService::class);

    return $breadcrumbs;
}

/**
 * Get application base URL.
 *
 * @return string Base URL from environment
 */
function base_url(): string
{
    return (string) env('APP_URL', 'http://localhost');
}

/**
 * Detect changed fields between new and existing data.
 *
 * Compare arrays and return only fields with different values.
 * Used for efficient database updates (update only changed fields).
 *
 * @param  array<string, mixed>  $newData  New data
 * @param  array<string, mixed>  $existing  Existing data
 * @return array<string, mixed> Changed fields only
 */
function changedFields(array $newData, array $existing): array
{
    $updateData = [];
    foreach ($newData as $key => $newValue) {
        if ($newValue !== ($existing[$key] ?? null)) {
            $updateData[$key] = $newValue;
        }
    }

    return $updateData;
}

/**
 * Format MIME type list for human-readable display.
 *
 * Converts 'image/jpeg,image/png,image/gif' to 'JPEG/PNG/GIF'.
 *
 * @param  string  $accepts  Comma-separated MIME types
 * @return string Formatted type list
 */
function formatAcceptedTypes(string $accepts): string
{
    $types = explode(',', $accepts);
    $readable = array_map(function (string $type): string {
        $parts = explode('/', $type);

        return strtoupper(end($parts));
    }, $types);

    return implode('/', $readable);
}

/**
 * Get the shared CacheService instance.
 *
 * Provides manual cache operations beyond automatic page caching.
 *
 * Examples:
 * - cache()->get('expensive_query')
 * - cache()->set('expensive_query', $data, 3600)
 * - cache()->delete('user_profile_' . $userId)
 * - cache()->deletePattern('blog/*')
 * - cache()->clear()
 */
function cache(): \Framework\Cache\CacheService
{
    /** @var \Framework\Cache\CacheService $cache */
    $cache = \Framework\Core\App::container()->get(\Framework\Cache\CacheService::class);

    return $cache;
}

/**
 * Get the fragment cache instance.
 *
 * Used for caching parts of pages (widgets, sidebars, expensive queries).
 */
function fragment(): \Framework\Cache\FragmentCache
{
    return \Framework\Core\App::container()->get(\Framework\Cache\FragmentCache::class);
}

/**
 * Get container instance or resolve a service.
 *
 * Shorthand for accessing services from dependency injection container.
 *
 * @param  string|null  $service  Service class name
 * @return mixed Container instance or resolved service
 */
function app(?string $service = null): mixed
{
    $container = \Framework\Core\App::container();

    if ($service === null) {
        return $container;
    }

    return $container->get($service);
}

/**
 * Get full path to view file.
 *
 * Resolve view paths relative to views directory.
 * Supports both .php and .lex.php extensions.
 *
 * @param  string  $view  View name (e.g., 'layouts/main')
 * @return string Full path to view file
 *
 * @throws \RuntimeException If view file not found
 */
function view_path(string $view): string
{
    $basePath = dirname(__DIR__, 2).'/views';
    $view = str_replace('.', '/', $view);

    // Try .php extension first, then .lex.php
    if (file_exists($basePath.'/'.$view.'.php')) {
        return $basePath.'/'.$view.'.php';
    }

    if (file_exists($basePath.'/'.$view.'.lex.php')) {
        return $basePath.'/'.$view.'.lex.php';
    }

    throw new \RuntimeException("View file not found: {$view}");
}

/**
 * Get the AuditService instance.
 */
function audit(): \App\Services\AuditService
{
    return app(\App\Services\AuditService::class);
}

/**
 * Get the geolocation service instance.
 */
function geo(): App\Services\GeoLocationService
{
    return app(App\Services\GeoLocationService::class);
}

function rateLimiter(): \Framework\Helpers\RateLimiter
{
    return app(\Framework\Helpers\RateLimiter::class);
}

if (!function_exists('env')) {
    /**
     * Get an environment variable with type conversion and optional default.
     *
     * Convenience helper for Dotenv::get(). Supports type conversion:
     * - 'true', '(true)' → true
     * - 'false', '(false)' → false
     * - 'null', '(null)' → null
     * - Numeric strings → int/float
     *
     * @param  string  $key  Variable name
     * @param  mixed  $default  Default value if variable doesn't exist
     * @return mixed Variable value with type conversion or default
     */
    function env(string $key, mixed $default = null): mixed
    {
        return \Framework\Core\Dotenv::get($key, $default);
    }
}
