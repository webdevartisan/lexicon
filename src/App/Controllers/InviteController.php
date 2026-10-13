<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogInvitationModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\InvitationService;
use Framework\Core\Response;

/**
 * Public invite accept/decline landing — reached from the email link.
 *
 * Routes guests based on whether the invitee's email already has an account:
 * existing users go to login (and return here after auth), new users go to
 * registration with the email pre-filled and the invite token stashed for
 * auto-acceptance after sign-up. Authed users see a confirmation page in the
 * dashboard layout.
 */
final class InviteController extends AppController
{
    public function __construct(
        private BlogInvitationModel $invitationModel,
        private InvitationService $invitationService,
        private UserModel $userModel,
        private UserPreferencesModel $preferences,
    ) {}

    /**
     * Show the invite landing page (GET /invite/{token}).
     *
     * @param  string  $token  Raw token from the email link
     */
    public function show(string $token): Response
    {
        $invite = $this->invitationModel->findValidByToken(hash('sha256', $token));

        if (!$invite) {
            return $this->view('invite.expired');
        }

        // Stash the token so login/registration can resume the invite flow.
        $this->session->set('pending_invite_token', $token);

        if (!auth()->check()) {
            $existing = $this->userModel->findByEmail($invite['email']);

            if ($existing !== null) {
                // After login, return here to confirm accept/decline.
                $this->session->set('intended_url', lurl('/invite/'.$token));

                return $this->redirect(lurl('/login'));
            }

            // New user: pre-fill the registration email and route there.
            $oldInput = (array) $this->session->get('_old_input', []);
            $oldInput['email'] = $invite['email'];
            $this->session->set('_old_input', $oldInput);

            return $this->redirect(lurl('/register'));
        }

        // Authed: the confirmation lives in the dashboard so it gets the control panel menu.
        return $this->redirect(lurl('/dashboard/invite/'.$token));
    }

    /**
     * Accept an invitation (POST /invite/{token}/accept).
     *
     * @param  string  $token  Raw token
     */
    public function accept(string $token): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        if (!auth()->check()) {
            $this->session->set('pending_invite_token', $token);

            return $this->redirect(lurl('/login'));
        }

        // Resolve the invite up-front so we know which blog to send them to.
        $invite = $this->invitationModel->findValidByToken(hash('sha256', $token));

        if ($invite !== false && !$this->isAddressedToSignedInUser($invite)) {
            return $this->wrongAccount($token);
        }

        try {
            $this->invitationService->accept($token, (int) auth()->user()['id']);
        } catch (\RuntimeException) {
            $this->flash('error', 'This invitation is no longer valid.');

            return $this->redirect(lurl('/dashboard'));
        }

        $this->session->remove('pending_invite_token');

        if ($invite !== false) {
            $this->flash('success', "You've joined the blog as {$invite['role']}.");

            // The invited blog only becomes active when none was; otherwise it waits on Blogs, under Shared.
            $active = $this->preferences->getDefaultBlogId((int) auth()->user()['id']);

            return $this->redirect(lurl($active === (int) $invite['blog_id'] ? '/dashboard' : '/dashboard/blog'));
        }

        $this->flash('success', "You've joined the blog.");

        return $this->redirect(lurl('/dashboard'));
    }

    /**
     * Decline an invitation (POST /invite/{token}/decline).
     *
     * @param  string  $token  Raw token
     */
    public function decline(string $token): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = auth()->check() ? (int) auth()->user()['id'] : 0;

        // A guest may decline from the email link; a signed-in user only for their own invite.
        $invite = $this->invitationModel->findValidByToken(hash('sha256', $token));
        if ($userId > 0 && $invite !== false && !$this->isAddressedToSignedInUser($invite)) {
            return $this->wrongAccount($token);
        }

        try {
            $this->invitationService->decline($token, $userId);
        } catch (\RuntimeException) {
            // Token already invalid — fall through to the declined confirmation.
        }

        $this->session->remove('pending_invite_token');

        // Authed users get a flash and head back to the dashboard; guests see
        // the public declined page.
        if (auth()->check()) {
            $this->flash('info', 'Invitation declined.');

            return $this->redirect(lurl('/dashboard'));
        }

        return $this->view('invite.declined');
    }

    /**
     * Whether the signed-in account is the address the invitation was sent to.
     *
     * @param  array{email: string}  $invite
     */
    private function isAddressedToSignedInUser(array $invite): bool
    {
        return strcasecmp((string) auth()->user()['email'], (string) $invite['email']) === 0;
    }

    /**
     * Send a mismatched account back to the page that explains the mismatch.
     */
    private function wrongAccount(string $token): Response
    {
        $this->flash('error', 'This invitation was sent to a different account.');

        return $this->redirect(lurl('/dashboard/invite/'.$token));
    }
}
