<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth;
use App\Models\UserModel;
use App\Services\TwoFactor\TwoFactorPolicy;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Holds accounts that must use a second step out of the control panel until they have set it up.
 *
 * Used via the "two-factor" alias, after "auth". The setup page itself lives
 * under /account, so there is always a way forward.
 */
final class TwoFactorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Auth $auth,
        private UserModel $users,
        private TwoFactorPolicy $policy,
    ) {}

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $sessionUser = $this->auth->user();
        $user = $sessionUser !== null ? $this->users->findById((int) $sessionUser['id']) : null;

        if ($user === null || !$this->policy->mustSetUp($user)) {
            return $next->handle($request);
        }

        $message = chrome_translate('account.twoFactor.requiredToContinue');
        $response = new Response();

        if ($request->isAjax() || stripos($request->header('accept') ?? '', 'application/json') !== false) {
            $response->setStatusCode(403);
            $response->setJson(['success' => false, 'error' => $message]);

            return $response;
        }

        $session = app()->get(\Framework\Session::class);
        $flash = $session->get('_flash', []);
        $flash['error'][] = $message;
        $session->set('_flash', $flash);

        return $response->redirect(lurl('/account/security/two-factor'));
    }
}
