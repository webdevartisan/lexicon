<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Sessions\RecentPasswordConfirmation;
use App\Services\Sessions\SessionRevoker;
use Framework\Core\Response;

/**
 * Signing out devices from the account security page.
 *
 * Both actions ask for the password unless it was confirmed in the last few
 * minutes: without that, someone holding a stolen session could sign the real
 * owner out and keep the account to themselves.
 */
final class AccountSessionsController extends AppController
{
    public function __construct(
        private SessionRevoker $revoker,
        private RecentPasswordConfirmation $confirmation,
    ) {}

    /**
     * Sign out one device. Picking this device is the same as signing out.
     */
    public function signOut(string $hash): Response
    {
        $userId = (int) auth()->user()['id'];

        if (!$this->confirmation->attempt($userId, $this->password())) {
            return $this->refuse();
        }

        if (hash_equals($this->session->hashedId(), $hash)) {
            auth()->logout();
            app(\App\Services\SessionLocaleSync::class)->forget();

            return $this->redirect(lurl('/login'));
        }

        if (!$this->revoker->endOne($userId, $hash, 'signed_out_by_user')) {
            // Unknown, already gone, or not this person's: all look the same from here.
            error_log("Account {$userId} asked to sign out a session that is not theirs or no longer exists.");
            $this->flash('error', chrome_translate('account.sessions.notFound'));

            return $this->redirect(lurl('/account/security'));
        }

        $this->flash('success', chrome_translate('account.sessions.signedOutOne'));

        return $this->redirect(lurl('/account/security'));
    }

    /**
     * Sign out every device except this one.
     */
    public function signOutOthers(): Response
    {
        $userId = (int) auth()->user()['id'];

        if (!$this->confirmation->attempt($userId, $this->password())) {
            return $this->refuse();
        }

        $ended = $this->revoker->endOthers($userId, 'signed_out_others_by_user');

        $this->flash('success', chrome_translate('account.sessions.signedOutOthers', ['count' => $ended]));

        return $this->redirect(lurl('/account/security'));
    }

    private function password(): ?string
    {
        $password = $this->request->postParam('current_password');

        return is_string($password) ? $password : null;
    }

    /**
     * Same wording for a wrong password and a throttled one, so the form
     * tells a guesser nothing.
     */
    private function refuse(): Response
    {
        $this->flash('error', chrome_translate('account.sessions.passwordRefused'));

        return $this->redirect(lurl('/account/security'));
    }
}
