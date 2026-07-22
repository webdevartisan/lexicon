<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Exceptions\CsrfTokenException;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Session;

/**
 * Verify a CSRF token on every state-changing request.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Methods defined as safe by RFC 9110: they must not change state, so they
     * carry no token.
     */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private Csrf $csrf,
        private Session $session
    ) {}

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (in_array(strtoupper($request->method), self::SAFE_METHODS, true)) {
            return $next->handle($request);
        }

        if ($this->csrf->isTokenValid($this->tokenFrom($request))) {
            return $next->handle($request);
        }

        return $this->reject($request);
    }

    /**
     * Read the token from either convention already used in this codebase:
     * a _token form field, or an X-CSRF-TOKEN header for fetch/XHR callers.
     */
    private function tokenFrom(Request $request): ?string
    {
        $token = $request->postParam('_token');

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $header = $request->header('X-CSRF-TOKEN');

        return is_string($header) && $header !== '' ? $header : null;
    }

    /**
     * A rejected token is usually an expired session, not an attack, so the
     * response has to be intelligible rather than a bare 500.
     *
     * @throws CsrfTokenException When there is no safe page to send the user back to.
     */
    private function reject(Request $request): Response
    {
        if ($this->wantsJson($request)) {
            $response = new Response();
            $response->setStatusCode(419);
            $response->addHeader('Content-Type', 'application/json; charset=utf-8');
            $response->setBody((string) json_encode([
                'success' => false,
                'error' => 'Invalid CSRF token.',
            ]));

            return $response;
        }

        $back = safe_return_to($this->refererPath($request));

        // No trustworthy page to return to: let the 419 error page explain it.
        if ($back === null) {
            throw new CsrfTokenException();
        }

        // Same treatment a validation failure gets, so the user lands back on
        // their form with what they typed still in it.
        $oldInput = $request->all();
        unset($oldInput['password'], $oldInput['confirm_password'], $oldInput['_token']);
        $this->session->set('_old_input', $oldInput);

        $flash = $this->session->get('_flash', []);
        $flash['error'] ??= [];
        $flash['error'][] = 'Your session expired before that was submitted. Nothing was changed - please try again.';
        $this->session->set('_flash', $flash);

        $response = new Response();
        $response->redirect($back);

        return $response;
    }

    private function wantsJson(Request $request): bool
    {
        if ($request->isAjax()) {
            return true;
        }

        // Several theme fetches omit X-Requested-With but do ask for JSON.
        return str_contains((string) $request->header('Accept'), 'application/json');
    }

    /**
     * Reduce the Referer to a path so safe_return_to() can vet it; a full URL
     * would always be rejected as off-site.
     */
    private function refererPath(Request $request): ?string
    {
        $referer = $request->header('referer');

        if (!is_string($referer) || $referer === '') {
            return null;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);

        // Cross-origin referer means this really is a forged request.
        // parse_url() yields a bare host while the Host header carries the port,
        // so the port has to come off or nothing matches outside :80 and :443.
        if ($refererHost !== null && $refererHost !== $this->requestHost($request)) {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH) ?: '/';
        $query = parse_url($referer, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }

    /**
     * The current host with any port stripped, for comparison against a
     * Referer's host.
     */
    private function requestHost(Request $request): ?string
    {
        $host = $request->header('host') ?? ($request->server['HTTP_HOST'] ?? null);

        if (!is_string($host) || $host === '') {
            return null;
        }

        // IPv6 literals arrive bracketed ("[::1]:8001"); keep the brackets intact.
        if (str_starts_with($host, '[')) {
            $close = strpos($host, ']');

            return $close === false ? $host : substr($host, 0, $close + 1);
        }

        $colon = strpos($host, ':');

        return $colon === false ? $host : substr($host, 0, $colon);
    }
}
