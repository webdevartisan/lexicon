<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Keeps accounts that have not confirmed their address out of actions that take part.
 *
 * Used via the "verified" alias, after "auth". Reading stays open; voting,
 * saving and reporting wait for the confirmation link, so a throwaway sign-up
 * with an address nobody owns cannot do anything at scale.
 */
final class VerifiedMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth) {}

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (!email_unverified($this->auth->user())) {
            return $next->handle($request);
        }

        $message = chrome_translate('auth.verifyNeeded');
        $accept = $request->header('accept') ?? '';
        $response = new Response;

        if ($request->isAjax() || stripos($accept, 'application/json') !== false) {
            $response->setStatusCode(403);
            $response->setJson(['success' => false, 'error' => $message]);

            return $response;
        }

        $session = app()->get(\Framework\Session::class);
        $flash = $session->get('_flash', []);
        $flash['error'][] = $message;
        $session->set('_flash', $flash);

        // Back to the page they were on; the path only, since the Referer host is client-controlled
        $back = safe_return_to((string) parse_url((string) $request->header('referer'), PHP_URL_PATH));

        return $response->redirect($back ?? lurl('/'));
    }
}
