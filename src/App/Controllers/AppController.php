<?php

declare(strict_types=1);

namespace App\Controllers;

use Framework\BaseController;
use Framework\Core\Response;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\SessionAwareInterface;
use Framework\Session;
use Framework\Validation\DatabaseValidator;

/**
 * Application-level controller for blog-specific helpers.
 *
 * Provides conveniences that all blog controllers need:
 * - Authentication shortcuts
 * - Flash message handling
 * - Validation helpers
 *
 * avoid putting business logic here; this is purely for reducing
 * boilerplate in feature controllers.
 */
abstract class AppController extends BaseController implements SessionAwareInterface
{
    protected Session $session;

    /**
     * Control panel ability every action in this controller requires.
     *
     * Set to a SystemPolicy ability (e.g. 'manageUsers') in admin
     * controllers; enforced by beforeAction() for every action so a newly
     * added action can never ship without an authorization check. Leave
     * null outside the admin area.
     */
    protected ?string $areaAbility = null;

    /**
     * Inject Session service via setter (called by Dispatcher).
     */
    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    /**
     * Cross-cutting checkpoint invoked by ControllerRequestHandler after
     * middleware has run and before the action executes.
     */
    public function beforeAction(string $action): void
    {
        if ($this->areaAbility !== null) {
            \App\Gate::authorize($this->areaAbility, \App\Resources\SystemResource::class, auth()->user() ?? []);
        }
    }

    // ============== Authentication Helpers ==============

    /**
     * Require specific permission or throw 403.
     *
     * @deprecated Use Gate::authorize() instead for proper policy-based authorization
     * @see Gate::authorize()
     *
     * @param  string  $permission  Permission name to check
     *
     * @throws UnauthorizedException If user lacks the permission
     */
    protected function requirePermission(string $permission): void
    {
        if (!auth()->hasPermission($permission)) {
            throw new UnauthorizedException(
                "Permission '{$permission}' is required for this action."
            );
        }
    }

    /**
     * Require at least one of the specified roles.
     *
     * We check if user has ANY of the provided roles (OR logic).
     *
     * @deprecated Use Gate::authorize() with policies or 'role:name' middleware instead
     * @see Gate::authorize()
     *
     * @param  string|string[]  $roles  Role name(s) to check
     *
     * @throws UnauthorizedException If user has none of the specified roles
     */
    protected function requireRole(string|array $roles): void
    {
        $rolesToCheck = is_array($roles) ? $roles : [$roles];

        foreach ($rolesToCheck as $role) {
            if (auth()->hasRole($role)) {
                return; // One match is enough
            }
        }

        throw new UnauthorizedException('You do not have permission to access this resource.');
    }

    // ============== Flash Message Helpers ==============

    /**
     * Store a flash message for the next request.
     *
     * We use the Session service to ensure proper session handling.
     *
     * @param  string  $type  Message type: 'success', 'error', 'warning', 'info'
     * @param  string  $message  The message content
     */
    protected function flash(string $type, string $message): void
    {
        $messages = $this->session->get('_flash', []);

        if (!isset($messages[$type])) {
            $messages[$type] = [];
        }

        $messages[$type][] = $message;
        $this->session->set('_flash', $messages);
    }

    /**
     * Get all flash messages and clear them.
     *
     * @return array<string, array<string>>
     */
    protected function getFlashMessages(): array
    {
        $messages = $this->session->get('_flash', []);
        $this->session->remove('_flash');

        return $messages;
    }

    // ============== Validation Helpers ==============

    /**
     * Validate request and return to previous page with errors on failure.
     *
     * @param  array<string,string|array<string>>  $rules
     * @param  array<string,string>  $messages  Custom error messages
     * @return DatabaseValidator|Response Validator instance if passes, Response if fails
     */
    protected function validateOrFail(array $rules, array $messages = []): DatabaseValidator|Response
    {
        $validator = $this->validate($rules, $messages);

        if ($validator->fails()) {
            // Just throw - middleware will catch and redirect
            throw new \Framework\Exceptions\ValidationException($validator);
        }

        return $validator;
    }

    /**
     * Flatten validator errors into a single sentence for JSON responses
     * and inline form errors, where the per-field structure has no home.
     *
     * @param  array<string, array<string>>  $errors  Validator::errors() output
     */
    protected function validationErrorLine(array $errors): string
    {
        $flat = [];
        foreach ($errors as $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $flat[] = $message;
            }
        }

        return implode(' ', $flat);
    }

    /**
     * Build a relative return URL from the current request.
     *
     * The resulting URL contains the current path and its query string after
     * removing transient parameters that should not be propagated between requests.
     */
    protected function buildReturnUrl(string $fallback): string
    {
        $path = $this->request->path() ?: $fallback;
        $query = $this->request->queryParams();

        unset(
            $query['return'],
            $query['_token'],
            $query['success'],
            $query['error']
        );

        $queryString = http_build_query($query);
        $url = $path.($queryString !== '' ? '?'.$queryString : '');

        return $this->isSafeReturnUrl($url) ? $url : $fallback;
    }

    /**
     * Determine whether a return URL is safe for internal redirects.
     *
     * Only relative application URLs are accepted. Protocol-relative URLs and
     * external destinations are rejected to prevent open redirect vulnerabilities.
     *
     * @param  array<int, string>  $allowedPrefixes
     */
    protected function isSafeReturnUrl(string $url, array $allowedPrefixes = ['/dashboard']): bool
    {
        if ($url === '' || $url[0] !== '/') {
            return false;
        }

        if (str_starts_with($url, '//')) {
            return false;
        }

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($url, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a return URL from request input.
     *
     * Returns the supplied URL when it is safe and allowed; otherwise returns the
     * provided fallback path.
     *
     * @param  array<int, string>  $allowedPrefixes
     */
    protected function resolveReturnUrl(?string $return, string $fallback, array $allowedPrefixes = ['/dashboard']): string
    {
        if ($return === null || $return === '') {
            return $fallback;
        }

        return $this->isSafeReturnUrl($return, $allowedPrefixes)
            ? $return
            : $fallback;
    }

    /**
     * Create and store a one-time return token for a safe local URL.
     *
     * @param  array<int, string>  $allowedPrefixes
     */
    protected function issueReturnToken(string $url, array $allowedPrefixes = ['/dashboard']): ?string
    {
        if (!$this->isSafeReturnUrl($url, $allowedPrefixes)) {
            return null;
        }

        $token = bin2hex(random_bytes(8));
        $returnUrls = $this->session->get('return_urls', []);

        if (!is_array($returnUrls)) {
            $returnUrls = [];
        }

        $returnUrls[$token] = [
            'url' => $url,
            'expires_at' => time() + 1800,
        ];

        $this->session->set('return_urls', $returnUrls);

        return $token;
    }

    /**
     * Resolve a one-time return token to a safe local URL.
     *
     * @param  array<int, string>  $allowedPrefixes
     */
    protected function consumeReturnToken(
        ?string $token,
        string $fallback,
        array $allowedPrefixes = ['/dashboard']
    ): string {
        if (!is_string($token) || $token === '') {
            return $fallback;
        }

        $returnUrls = $this->session->get('return_urls', []);

        if (!is_array($returnUrls) || !isset($returnUrls[$token])) {
            return $fallback;
        }

        $entry = $returnUrls[$token];
        unset($returnUrls[$token]);
        $this->session->set('return_urls', $returnUrls);

        if (!is_array($entry)) {
            return $fallback;
        }

        $url = $entry['url'] ?? null;
        $expiresAt = $entry['expires_at'] ?? 0;

        if (!is_string($url) || !is_int($expiresAt) || $expiresAt < time()) {
            return $fallback;
        }

        return $this->isSafeReturnUrl($url, $allowedPrefixes)
            ? $url
            : $fallback;
    }

    /**
     * Redirect back to previous page with old input preserved.
     */
    protected function redirectBack(): Response
    {
        // Preserve old input for form repopulation
        $this->session->set('_old_input', $this->request->all());

        return $this->redirect($this->backUrlPath());
    }

    /**
     * Send the operator back to a list page they were just acting from, keeping
     * the filters, sort and page number they had.
     *
     * Row actions (feature toggles, retries) post from a list that is usually
     * filtered, sorted and paginated. Redirecting to the bare path throws all of
     * that away and drops them back on page one of an unfiltered table. The
     * Referer carries the state, but it is client-supplied, so it is only
     * honoured when it points at the very list we were going to send them to
     * anyway -- which makes it useless as a redirect gadget.
     *
     * @param  string  $listPath  Unlocalised list path, e.g. `/admin/blogs`
     */
    protected function redirectToList(string $listPath): Response
    {
        $referer = (string) ($this->request->header('Referer') ?? '');

        if ($referer !== '' && str_starts_with($referer, base_url())) {
            $path = (string) (parse_url($referer, PHP_URL_PATH) ?? '');
            $quoted = preg_quote($listPath, '#');

            // Same path, with or without a /{locale} prefix in front of it.
            if (preg_match('#^(/[a-z]{2,5})?'.$quoted.'/?$#', $path) === 1) {
                $query = (string) (parse_url($referer, PHP_URL_QUERY) ?? '');

                return $this->redirect($path.($query !== '' ? '?'.$query : ''));
            }
        }

        return $this->redirect($listPath);
    }

    /**
     * The local path (plus query) the request came from, or `/` when there is
     * no usable Referer.
     *
     * Two deliberate properties, because the Referer is attacker-controlled and
     * this feeds a redirect:
     *
     * 1. The origin is compared by scheme+host+port, not with a prefix match.
     *    `str_starts_with($referer, base_url())` accepted
     *    `http://lexicon.test.evil.com/...` for a base URL of `http://lexicon.test`,
     *    which was an open redirect on every caller.
     * 2. Only the path and query are returned, never the full URL, so even a
     *    mistake in the origin check cannot send anyone off-site.
     */
    protected function backUrlPath(): string
    {
        $referer = (string) ($this->request->header('Referer') ?? '');

        if ($referer === '') {
            return '/';
        }

        $refererParts = parse_url($referer);
        $baseParts = parse_url(base_url());

        if ($refererParts === false || $baseParts === false) {
            return '/';
        }

        $origin = static fn (array $p): string => strtolower((string) ($p['scheme'] ?? ''))
            .'://'.strtolower((string) ($p['host'] ?? ''))
            .':'.(string) ($p['port'] ?? '');

        if ($origin($refererParts) !== $origin($baseParts)) {
            return '/';
        }

        $path = (string) ($refererParts['path'] ?? '/');

        // A protocol-relative or scheme-bearing path would escape the origin the
        // check above just pinned down.
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '/';
        }

        $query = (string) ($refererParts['query'] ?? '');

        return $path.($query !== '' ? '?'.$query : '');
    }

    // ============== Error Response Helpers ==============

    /**
     * Render 404 Not Found page.
     *
     * @param  string  $message  Error message to display
     */
    protected function notFound(string $message = 'Page not found'): Response
    {
        $this->response->setStatusCode(404);

        return $this->view('errors.404', ['message' => $message]);
    }

    /**
     * Render 403 Forbidden page.
     *
     * @param  string  $message  Error message to display
     */
    protected function forbidden(string $message = 'Access denied'): Response
    {
        $this->response->setStatusCode(403);

        return $this->view('errors/403', ['message' => $message]);
    }
}
