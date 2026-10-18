<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\LocaleState;
use App\Services\TranslationService;
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
    private ?TranslationService $translator = null;

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
     * Per-action exceptions to $areaAbility, as action => ability. An action
     * not listed here still needs $areaAbility, so a new action fails closed.
     *
     * @var array<string, string>
     */
    protected array $actionAbilities = [];

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
        $ability = $this->actionAbilities[$action] ?? $this->areaAbility;

        if ($ability !== null) {
            \App\Gate::authorize($ability, \App\Resources\SystemResource::class, auth()->user() ?? []);
        }
    }

    // ============== Authentication Helpers ==============

    /**
     * Turn away a signed-in account whose email address is still unconfirmed.
     *
     * @return Response|null The redirect to send, or null when the account is confirmed
     */
    protected function refuseUnverified(): ?Response
    {
        if (!email_unverified(auth()->user())) {
            return null;
        }

        $this->flash('error', chrome_translate('auth.verifyNeeded'));

        return $this->redirect(lurl('/'));
    }

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
     * @deprecated Use Gate::authorize() with policies instead
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
     * Send the user back to the form with one field's problem spelled out.
     */
    protected function rejectField(string $field, string $message): Response
    {
        $oldInput = $this->request->all();
        unset($oldInput['_token']);
        $this->session->set('_old_input', $oldInput);
        $this->session->set('_errors', [$field => [$message]]);
        $this->flash('error', $message);

        return $this->redirectBack();
    }

    /**
     * Translate a key into the chrome locale, for flash messages and other
     * text controllers hand back.
     *
     * @param  array<string, string|int>  $params
     */
    protected function t(string $key, array $params = []): string
    {
        $this->translator ??= new TranslationService(LocaleState::get()->chromeLocale);

        return $this->translator->translate($key, $params);
    }

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
     * Create and store a short-lived return token for a safe local URL.
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

        // Every list view issues one, so expired tokens are dropped here or the session keeps growing.
        $now = time();
        $returnUrls = array_filter(
            $returnUrls,
            static fn (mixed $entry): bool => is_array($entry) && ($entry['expires_at'] ?? 0) >= $now
        );

        $returnUrls[$token] = [
            'url' => $url,
            'expires_at' => $now + 7200,
        ];

        $this->session->set('return_urls', $returnUrls);

        return $token;
    }

    /**
     * Resolve a return token to a safe local URL.
     *
     * The token stays valid until it expires, so an editor that saves and
     * reloads itself can still offer the same way back.
     *
     * @param  array<int, string>  $allowedPrefixes
     */
    protected function returnTokenUrl(
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
        $this->session->set('_old_input', sanitize_old_input($this->request->all()));

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
     * no usable Referer. See referer_path() for why the Referer is trusted this far.
     */
    protected function backUrlPath(): string
    {
        return referer_path($this->request->header('Referer')) ?? '/';
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

    // ============== Download Helpers ==============

    /**
     * Send rows as a CSV download that is never cached.
     *
     * @param  list<string>  $header
     * @param  list<list<string|int|float>>  $rows
     */
    protected function csv(string $filename, array $header, array $rows): Response
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $header);

        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($cell) => is_string($cell) ? self::csvCell($cell) : $cell, $row));
        }

        rewind($out);
        $body = (string) stream_get_contents($out);
        fclose($out);

        $this->response->addHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->response->addHeader('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $this->response->addHeader('Cache-Control', 'private, no-store');
        $this->response->setBody($body);

        return $this->response;
    }

    /**
     * Text that reached us from outside (campaign names, referrers, titles) could
     * start with =, +, - or @ and run as a formula in a spreadsheet.
     */
    protected static function csvCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
