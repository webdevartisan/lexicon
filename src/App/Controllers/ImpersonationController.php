<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ImpersonationService;
use Framework\Core\Response;

/**
 * Ends an impersonation session from wherever the admin happens to be.
 *
 * Lives outside /admin because the session making the request is the
 * impersonated account, which has no access to the control panel.
 */
final class ImpersonationController extends AppController
{
    public function __construct(
        private ImpersonationService $impersonation,
    ) {}

    public function exit(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $adminId = $this->impersonation->stop('exited', $this->request->ip());

        // Nothing to exit is not an error worth a page: the session may simply
        // have hit its time box between the banner rendering and the click.
        if ($adminId === null) {
            $this->flash('info', 'That session had already ended. You are signed in as yourself.');

            return $this->redirect('/');
        }

        $this->flash('success', 'You are signed in as yourself again.');

        return $this->redirect('/admin/users');
    }
}
