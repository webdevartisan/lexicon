<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogInvitationModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\AccountRegistrar;
use App\Services\BlogRoleLabels;
use App\Services\InvitationService;
use App\Services\InviteSummary;
use Framework\Core\Response;

/**
 * Public invite accept/decline landing — reached from the email link.
 *
 * Routes guests based on whether the invitee's email already has an account:
 * existing users go to login (and return here after auth), new users get a
 * three-step wizard (invitation, create account, joined) that signs them up
 * and accepts the invite in one go. Authed users are sent to the confirmation
 * page in the dashboard layout.
 */
final class InviteController extends AppController
{
    public function __construct(
        private BlogInvitationModel $invitationModel,
        private InvitationService $invitationService,
        private UserModel $userModel,
        private UserPreferencesModel $preferences,
        private InviteSummary $summary,
        private AccountRegistrar $registrar,
        private NotificationModel $notifications,
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

            $details = $this->summary->describe($invite);
            if ($details === null) {
                return $this->view('invite.expired');
            }

            return $this->view('invite.wizard', ['token' => $token] + $details);
        }

        // Authed: the confirmation lives in the dashboard so it gets the control panel menu.
        return $this->redirect(lurl('/dashboard/invite/'.$token));
    }

    /**
     * Step two of the wizard: create the account for the invited address and accept (POST /invite/{token}/register).
     *
     * The email comes from the invitation, never from the form, so the invite
     * cannot be attached to a different address.
     *
     * @param  string  $token  Raw token
     */
    public function register(string $token): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        if (auth()->check()) {
            return $this->redirect(lurl('/dashboard/invite/'.$token));
        }

        $invite = $this->invitationModel->findValidByToken(hash('sha256', $token));
        $details = $invite ? $this->summary->describe($invite) : null;

        if ($details === null) {
            return $this->view('invite.expired');
        }

        // They registered between opening the link and submitting: sign in instead.
        if ($this->userModel->findByEmail($invite['email']) !== null) {
            $this->session->set('intended_url', lurl('/invite/'.$token));

            return $this->redirect(lurl('/login'));
        }

        $ageRequired = chrome_translate('auth.ageRequired', ['age' => minimum_signup_age()]);
        $validated = $this->validateOrFail([
            'password' => 'required|password:'.password_policy_preset(),
            'age_confirmed' => 'required|in:1',
        ], [
            'age_confirmed.required' => $ageRequired,
            'age_confirmed.in' => $ageRequired,
        ])->validated();

        $userId = $this->registrar->register((string) $invite['email'], $validated['password'], $this->request, true);
        if ($userId === null) {
            $this->flash('error', 'We could not create your account. Please try again.');

            return $this->redirect(lurl('/invite/'.$token));
        }

        try {
            $this->invitationService->accept($token, $userId, $this->request->ip());
        } catch (\RuntimeException) {
            // The invite lapsed while they typed; the account exists, so land them on the dashboard.
            $this->flash('info', 'Your account is ready, but the invitation is no longer valid.');

            return $this->redirect(lurl('/dashboard'));
        }

        $this->session->remove('pending_invite_token');
        $this->session->set('invite_joined', [
            'blogName' => $details['blogName'],
            'roleLabel' => $details['roleLabel'],
        ]);

        return $this->redirect(lurl('/invite/joined'));
    }

    /**
     * Step three of the wizard: confirm the new member is in (GET /invite/joined).
     */
    public function joined(): Response
    {
        $joined = $this->session->get('invite_joined');
        $this->session->remove('invite_joined');

        if (!auth()->check() || !is_array($joined)) {
            return $this->redirect(lurl('/dashboard'));
        }

        return $this->view('invite.joined', $joined + ['accountEmail' => (string) auth()->user()['email']]);
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
            $this->invitationService->accept($token, (int) auth()->user()['id'], $this->request->ip());
        } catch (\RuntimeException) {
            $this->flash('error', 'This invitation is no longer valid.');

            return $this->redirect(lurl('/dashboard'));
        }

        $this->session->remove('pending_invite_token');
        $this->markAnsweredNotificationRead();

        if ($invite !== false) {
            $this->flash('success', "You've joined the blog as ".app(BlogRoleLabels::class)->label($invite['role']).'.');

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
            $this->invitationService->decline($token, $userId, $this->request->ip());
        } catch (\RuntimeException) {
            // Token already invalid — fall through to the declined confirmation.
        }

        $this->session->remove('pending_invite_token');
        $this->markAnsweredNotificationRead();

        // Authed users get a flash and head back to the dashboard; guests see
        // the public declined page.
        if (auth()->check()) {
            $this->flash('info', 'Invitation declined.');

            return $this->redirect(lurl('/dashboard'));
        }

        return $this->view('invite.declined');
    }

    /**
     * Answered from the notification's own Accept or Decline, so that row
     * should stop counting as unread. Scoped to the caller's inbox like every
     * other notification write; a forged id simply matches nothing.
     */
    private function markAnsweredNotificationRead(): void
    {
        $id = (int) $this->request->postParam('notification', 0);

        if ($id > 0 && auth()->check()) {
            $this->notifications->markRead($id, (int) auth()->user()['id'], NotificationModel::INBOX);
        }
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
