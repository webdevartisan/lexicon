<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Ensures that the current request is made by an authenticated user.
 *
 * Used via the "auth" middleware alias in config/middleware.php.
 * For unauthenticated users:
 *   - HTML requests are redirected to the login page.
 *   - Non-HTML (e.g. API/AJAX) requests receive an UnauthorizedException.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth) {}

    /**
     * @throws UnauthorizedException
     */
    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (!$this->auth->check()) {
            // Header keys in Request are stored lowercase, so use 'accept'
            $accept = $request->header('accept') ?? '';

            if (stripos($accept, 'application/json') !== false || stripos($accept, 'text/json') !== false) {
                // 401: unauthenticated (not logged in)
                throw new UnauthorizedException('Authentication required to access this resource.', 401);
            }

            $session = app()->get(\Framework\Session::class);

            // The path only: fullUrl() takes its host from the Host header,
            // which the client controls.
            $returnTo = safe_return_to($request->uri);

            if ($returnTo !== null) {
                $session->set('intended_url', $returnTo);
            }

            if ($session->wasSignedOutElsewhere()) {
                $flash = $session->get('_flash', []);
                $flash['info'][] = chrome_translate('auth.signedOutElsewhere');
                $session->set('_flash', $flash);
            }

            // And put it on the login URL as well. The session copy is the one
            // that survives a modal login, but a query parameter survives a
            // session the visitor loses on the way -- a browser that drops the
            // cookie, or a login opened in a second tab -- and it is what the
            // login form itself reads to carry the destination through a POST.
            $login = '/login'.($returnTo !== null ? '?return_to='.urlencode($returnTo) : '');

            // Default for browser/HTML: redirect to login
            $response = new Response();
            $response->redirect($login);

            return $response;
        }

        // Delegate to the next handler in the pipeline
        return $next->handle($request);
    }
}
